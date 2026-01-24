import json

from fastapi import APIRouter, HTTPException
from fastapi.responses import StreamingResponse
from pydantic import BaseModel

from app.api.deps import CurrentUser
from app.services.chat import get_chat_service, ChatSource

router = APIRouter()


class ChatMessage(BaseModel):
    role: str  # "user" or "assistant"
    content: str


class ChatRequest(BaseModel):
    message: str
    source_ids: list[int] | None = None
    conversation_history: list[ChatMessage] | None = None
    num_chunks: int = 5
    temperature: float = 0.7
    stream: bool = False


class ChatSourceResponse(BaseModel):
    document_id: int
    source_id: int
    title: str | None
    url: str | None
    content_preview: str
    score: float


class ChatResponse(BaseModel):
    answer: str
    sources: list[ChatSourceResponse]


@router.post("", response_model=ChatResponse)
async def chat(request: ChatRequest, current_user: CurrentUser):
    """Send a chat message and get a response."""
    import traceback

    if request.stream:
        raise HTTPException(
            status_code=400,
            detail="Use /chat/stream endpoint for streaming responses",
        )

    try:
        print(f"[Chat] Getting chat service...")
        chat_service = get_chat_service()
        print(f"[Chat] Chat service initialized")
    except Exception as e:
        print(f"[Chat] Error initializing chat service: {e}")
        traceback.print_exc()
        raise HTTPException(status_code=500, detail=f"Failed to initialize chat service: {str(e)}")

    # Convert conversation history
    history = None
    if request.conversation_history:
        history = [{"role": msg.role, "content": msg.content} for msg in request.conversation_history]

    try:
        print(f"[Chat] Calling chat with query: {request.message[:50]}...")
        response = chat_service.chat(
            query=request.message,
            source_ids=request.source_ids,
            conversation_history=history,
            num_chunks=request.num_chunks,
            temperature=request.temperature,
        )
        print(f"[Chat] Got response with {len(response.sources)} sources")

        return ChatResponse(
            answer=response.answer,
            sources=[
                ChatSourceResponse(
                    document_id=s.document_id,
                    source_id=s.source_id,
                    title=s.title,
                    url=s.url,
                    content_preview=s.content_preview,
                    score=s.score,
                )
                for s in response.sources
            ],
        )
    except Exception as e:
        print(f"[Chat] Error during chat: {e}")
        traceback.print_exc()
        raise HTTPException(status_code=500, detail=f"Chat error: {str(e)}")


@router.post("/stream")
async def chat_stream(request: ChatRequest, current_user: CurrentUser):
    """Send a chat message and get a streaming response.

    Returns Server-Sent Events (SSE) stream with:
    - data: {"type": "chunk", "content": "..."} for answer chunks
    - data: {"type": "sources", "sources": [...]} for sources at the end
    - data: {"type": "done"} when complete
    """
    chat_service = get_chat_service()

    # Convert conversation history
    history = None
    if request.conversation_history:
        history = [{"role": msg.role, "content": msg.content} for msg in request.conversation_history]

    async def generate():
        try:
            async for item in chat_service.chat_stream(
                query=request.message,
                source_ids=request.source_ids,
                conversation_history=history,
                num_chunks=request.num_chunks,
                temperature=request.temperature,
            ):
                if isinstance(item, str):
                    # Text chunk
                    data = json.dumps({"type": "chunk", "content": item})
                    yield f"data: {data}\n\n"
                elif isinstance(item, list):
                    # Sources list
                    sources_data = [
                        {
                            "document_id": s.document_id,
                            "source_id": s.source_id,
                            "title": s.title,
                            "url": s.url,
                            "content_preview": s.content_preview,
                            "score": s.score,
                        }
                        for s in item
                    ]
                    data = json.dumps({"type": "sources", "sources": sources_data})
                    yield f"data: {data}\n\n"

            yield f"data: {json.dumps({'type': 'done'})}\n\n"
        except Exception as e:
            error_data = json.dumps({"type": "error", "message": str(e)})
            yield f"data: {error_data}\n\n"

    return StreamingResponse(
        generate(),
        media_type="text/event-stream",
        headers={
            "Cache-Control": "no-cache",
            "Connection": "keep-alive",
        },
    )


@router.get("/search")
async def search(
    query: str,
    limit: int = 5,
    source_ids: str | None = None,
    current_user: CurrentUser = None,
):
    """Search for relevant document chunks without generating a response."""
    from app.services.vector_store import get_vector_store

    vector_store = get_vector_store()

    # Parse source_ids
    parsed_source_ids = None
    if source_ids:
        try:
            parsed_source_ids = [int(id.strip()) for id in source_ids.split(",")]
        except ValueError:
            raise HTTPException(status_code=400, detail="Invalid source_ids format")

    results = vector_store.search(
        query=query,
        limit=limit,
        source_ids=parsed_source_ids,
    )

    return [
        {
            "chunk_id": r.chunk_id,
            "document_id": r.document_id,
            "source_id": r.source_id,
            "content": r.content,
            "score": r.score,
            "metadata": r.metadata,
        }
        for r in results
    ]
