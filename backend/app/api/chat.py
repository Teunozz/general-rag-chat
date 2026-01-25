import json
from datetime import datetime

from fastapi import APIRouter, HTTPException
from fastapi.responses import StreamingResponse
from pydantic import BaseModel

from app.api.deps import CurrentUser, DbSession
from app.models.settings import AppSettings
from app.models.conversation import Conversation, Message
from app.services.chat import get_chat_service, ChatSource
from app.services.llm import get_llm_service

router = APIRouter()

# Conversation summarization settings
SUMMARIZE_THRESHOLD = 20  # Start summarizing when messages exceed this count
RECENT_MESSAGES_TO_KEEP = 10  # Number of recent messages to keep in full


class ChatMessage(BaseModel):
    role: str  # "user" or "assistant"
    content: str


class ChatRequest(BaseModel):
    message: str
    source_ids: list[int] | None = None
    conversation_history: list[ChatMessage] | None = None
    conversation_id: int | None = None  # If provided, persists messages to this conversation
    num_chunks: int | None = None  # Uses db setting if not specified
    temperature: float | None = None  # Uses db setting if not specified
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
    conversation_id: int | None = None  # Returned when using conversation persistence


def get_chat_settings(db) -> tuple[int, float, str | None]:
    """Get chat settings from database, with fallback defaults."""
    settings = db.query(AppSettings).first()
    if settings:
        return (
            settings.chat_context_chunks,
            settings.chat_temperature,
            settings.chat_system_prompt,
        )
    return 5, 0.7, None  # Fallback defaults


def generate_title(message: str, max_length: int = 50) -> str:
    """Generate a conversation title from the first message."""
    # Clean up and truncate the message
    title = message.strip()
    if len(title) > max_length:
        title = title[:max_length].rsplit(" ", 1)[0] + "..."
    return title


def generate_conversation_summary(messages: list[dict]) -> str:
    """Generate a summary of conversation messages using the LLM."""
    if not messages:
        return ""

    llm_service = get_llm_service()

    # Format messages for the summary prompt
    conversation_text = "\n".join(
        f"{msg['role'].upper()}: {msg['content']}" for msg in messages
    )

    summary_prompt = [
        {
            "role": "system",
            "content": (
                "You are a helpful assistant that summarizes conversations. "
                "Create a concise summary that captures the key topics discussed, "
                "important information shared, decisions made, and any relevant context "
                "that would help continue the conversation. Keep it under 500 words."
            ),
        },
        {
            "role": "user",
            "content": f"Please summarize this conversation:\n\n{conversation_text}",
        },
    ]

    return llm_service.chat(summary_prompt, temperature=0.3)


def build_conversation_history(
    conversation: Conversation,
    messages: list,
) -> list[dict]:
    """Build optimized conversation history using summary for older messages.

    Returns a list of message dicts ready to send to the LLM.
    """
    total_messages = len(messages)

    # If under threshold, return all messages as-is
    if total_messages <= SUMMARIZE_THRESHOLD:
        return [{"role": msg.role, "content": msg.content} for msg in messages]

    # Check if we need to generate/update the summary
    messages_to_summarize = messages[:-RECENT_MESSAGES_TO_KEEP]
    last_message_to_summarize = messages_to_summarize[-1] if messages_to_summarize else None

    needs_summary_update = (
        not conversation.summary
        or not conversation.summary_up_to_message_id
        or (last_message_to_summarize and conversation.summary_up_to_message_id < last_message_to_summarize.id)
    )

    if needs_summary_update and messages_to_summarize:
        # Generate summary for older messages
        messages_for_summary = [
            {"role": msg.role, "content": msg.content} for msg in messages_to_summarize
        ]
        conversation.summary = generate_conversation_summary(messages_for_summary)
        conversation.summary_up_to_message_id = last_message_to_summarize.id
        # Note: caller should commit the transaction

    # Build history: summary (as system context) + recent messages
    history = []

    if conversation.summary:
        history.append({
            "role": "user",
            "content": f"[Previous conversation summary: {conversation.summary}]",
        })
        history.append({
            "role": "assistant",
            "content": "I understand. I have the context from our previous conversation. How can I help you continue?",
        })

    # Add recent messages
    recent_messages = messages[-RECENT_MESSAGES_TO_KEEP:]
    for msg in recent_messages:
        history.append({"role": msg.role, "content": msg.content})

    return history


@router.post("", response_model=ChatResponse)
async def chat(request: ChatRequest, current_user: CurrentUser, db: DbSession):
    """Send a chat message and get a response."""
    import traceback

    if request.stream:
        raise HTTPException(
            status_code=400,
            detail="Use /chat/stream endpoint for streaming responses",
        )

    # Get settings from database
    db_chunks, db_temperature, db_system_prompt = get_chat_settings(db)
    num_chunks = request.num_chunks if request.num_chunks is not None else db_chunks
    temperature = request.temperature if request.temperature is not None else db_temperature

    # Load or create conversation for persistence
    conversation = None
    if request.conversation_id:
        conversation = (
            db.query(Conversation)
            .filter(Conversation.id == request.conversation_id, Conversation.user_id == current_user.id)
            .first()
        )
        if not conversation:
            raise HTTPException(status_code=404, detail="Conversation not found")
    elif request.conversation_history is None:
        # Auto-create a new conversation if no history provided (new conversation flow)
        conversation = Conversation(
            user_id=current_user.id,
            source_ids=request.source_ids or [],
        )
        db.add(conversation)
        db.flush()  # Get the ID without committing

    try:
        print(f"[Chat] Getting chat service...")
        chat_service = get_chat_service()
        print(f"[Chat] Chat service initialized")
    except Exception as e:
        print(f"[Chat] Error initializing chat service: {e}")
        traceback.print_exc()
        raise HTTPException(status_code=500, detail=f"Failed to initialize chat service: {str(e)}")

    # Build conversation history (with summarization for long conversations)
    history = None
    if conversation and conversation.messages:
        history = build_conversation_history(conversation, list(conversation.messages))
        # Commit any summary updates
        db.commit()
    elif request.conversation_history:
        # Use history from request (backward compatibility)
        history = [{"role": msg.role, "content": msg.content} for msg in request.conversation_history]

    # Use conversation's source_ids if set, otherwise use request's
    source_ids = request.source_ids
    if conversation and conversation.source_ids:
        source_ids = conversation.source_ids

    try:
        print(f"[Chat] Calling chat with query: {request.message[:50]}... (history: {len(history) if history else 0} messages)")
        response = chat_service.chat(
            query=request.message,
            source_ids=source_ids,
            conversation_history=history,
            num_chunks=num_chunks,
            temperature=temperature,
            system_prompt=db_system_prompt,
        )
        print(f"[Chat] Got response with {len(response.sources)} sources")

        # Save messages to database if using conversation persistence
        if conversation:
            # Save user message
            user_msg = Message(
                conversation_id=conversation.id,
                role="user",
                content=request.message,
            )
            db.add(user_msg)

            # Save assistant message with sources
            sources_data = [
                {
                    "document_id": s.document_id,
                    "source_id": s.source_id,
                    "title": s.title,
                    "url": s.url,
                    "content_preview": s.content_preview,
                    "score": s.score,
                }
                for s in response.sources
            ]
            assistant_msg = Message(
                conversation_id=conversation.id,
                role="assistant",
                content=response.answer,
                sources=sources_data,
            )
            db.add(assistant_msg)

            # Auto-generate title from first message if not set
            if not conversation.title and len(conversation.messages) == 0:
                conversation.title = generate_title(request.message)

            # Update conversation timestamp
            conversation.updated_at = datetime.utcnow()
            db.commit()

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
            conversation_id=conversation.id if conversation else None,
        )
    except Exception as e:
        print(f"[Chat] Error during chat: {e}")
        traceback.print_exc()
        raise HTTPException(status_code=500, detail=f"Chat error: {str(e)}")


@router.post("/stream")
async def chat_stream(request: ChatRequest, current_user: CurrentUser, db: DbSession):
    """Send a chat message and get a streaming response.

    Returns Server-Sent Events (SSE) stream with:
    - data: {"type": "chunk", "content": "..."} for answer chunks
    - data: {"type": "sources", "sources": [...]} for sources at the end
    - data: {"type": "done"} when complete
    """
    # Get settings from database
    db_chunks, db_temperature, db_system_prompt = get_chat_settings(db)
    num_chunks = request.num_chunks if request.num_chunks is not None else db_chunks
    temperature = request.temperature if request.temperature is not None else db_temperature

    # Load or create conversation for persistence
    conversation = None
    if request.conversation_id:
        conversation = (
            db.query(Conversation)
            .filter(Conversation.id == request.conversation_id, Conversation.user_id == current_user.id)
            .first()
        )
        if not conversation:
            raise HTTPException(status_code=404, detail="Conversation not found")
    elif request.conversation_history is None:
        # Auto-create a new conversation if no history provided
        conversation = Conversation(
            user_id=current_user.id,
            source_ids=request.source_ids or [],
        )
        db.add(conversation)
        db.flush()

    chat_service = get_chat_service()

    # Build conversation history (with summarization for long conversations)
    history = None
    if conversation and conversation.messages:
        history = build_conversation_history(conversation, list(conversation.messages))
        db.commit()  # Commit any summary updates
    elif request.conversation_history:
        history = [{"role": msg.role, "content": msg.content} for msg in request.conversation_history]

    # Use conversation's source_ids if set, otherwise use request's
    source_ids = request.source_ids
    if conversation and conversation.source_ids:
        source_ids = conversation.source_ids

    # Track if this is the first message for title generation
    is_first_message = conversation and (not conversation.title or len(conversation.messages) == 0)

    async def generate():
        full_response = ""
        sources_data = []
        try:
            async for item in chat_service.chat_stream(
                query=request.message,
                source_ids=source_ids,
                conversation_history=history,
                num_chunks=num_chunks,
                temperature=temperature,
                system_prompt=db_system_prompt,
            ):
                if isinstance(item, str):
                    # Text chunk
                    full_response += item
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

            # Save messages to database if using conversation persistence
            if conversation:
                # Save user message
                user_msg = Message(
                    conversation_id=conversation.id,
                    role="user",
                    content=request.message,
                )
                db.add(user_msg)

                # Save assistant message with sources
                assistant_msg = Message(
                    conversation_id=conversation.id,
                    role="assistant",
                    content=full_response,
                    sources=sources_data,
                )
                db.add(assistant_msg)

                # Auto-generate title from first message if not set
                if is_first_message:
                    conversation.title = generate_title(request.message)

                # Update conversation timestamp
                conversation.updated_at = datetime.utcnow()
                db.commit()

            done_data = {"type": "done"}
            if conversation:
                done_data["conversation_id"] = conversation.id
            yield f"data: {json.dumps(done_data)}\n\n"
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
