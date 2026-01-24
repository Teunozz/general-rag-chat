from collections.abc import AsyncGenerator
from dataclasses import dataclass
from functools import lru_cache

from app.config import get_settings
from app.services.llm import LLMService, get_llm_service
from app.services.vector_store import VectorStoreService, SearchResult, get_vector_store

settings = get_settings()

DEFAULT_SYSTEM_INSTRUCTIONS = """You are a helpful assistant that answers questions based on the provided context.

Instructions:
1. Answer the question based ONLY on the provided context
2. If the context doesn't contain enough information to answer, say so clearly
3. Cite your sources by mentioning the document title or URL when referencing information
4. Be concise but thorough in your answers
5. If asked about something not in the context, explain that you can only answer based on the available documents"""


def build_system_prompt(instructions: str, context: str) -> str:
    """Build the full system prompt with instructions and context.

    If the instructions contain {context}, substitute it.
    Otherwise, append the context section.
    """
    if "{context}" in instructions:
        return instructions.format(context=context)
    else:
        return f"{instructions}\n\nContext:\n{context}"


@dataclass
class ChatSource:
    """Source citation for a chat response."""

    document_id: int
    source_id: int
    title: str | None
    url: str | None
    content_preview: str
    score: float


@dataclass
class ChatResponse:
    """Chat response with sources."""

    answer: str
    sources: list[ChatSource]


class ChatService:
    """RAG-based chat service."""

    def __init__(
        self,
        llm_service: LLMService | None = None,
        vector_store: VectorStoreService | None = None,
    ):
        self.llm = llm_service or get_llm_service()
        self.vector_store = vector_store or get_vector_store()

    def _build_context(self, results: list[SearchResult]) -> str:
        """Build context string from search results."""
        context_parts = []
        for i, result in enumerate(results, 1):
            source_info = []
            if result.metadata.get("title"):
                source_info.append(f"Title: {result.metadata['title']}")
            if result.metadata.get("url"):
                source_info.append(f"URL: {result.metadata['url']}")

            source_header = f"[Source {i}]"
            if source_info:
                source_header += f" ({', '.join(source_info)})"

            context_parts.append(f"{source_header}\n{result.content}")

        return "\n\n---\n\n".join(context_parts)

    def _build_sources(self, results: list[SearchResult]) -> list[ChatSource]:
        """Build source citations from search results."""
        seen = set()
        sources = []

        for result in results:
            # Deduplicate by document_id
            if result.document_id in seen:
                continue
            seen.add(result.document_id)

            sources.append(
                ChatSource(
                    document_id=result.document_id,
                    source_id=result.source_id,
                    title=result.metadata.get("title"),
                    url=result.metadata.get("url"),
                    content_preview=result.content[:200] + "..."
                    if len(result.content) > 200
                    else result.content,
                    score=result.score,
                )
            )

        return sources

    def chat(
        self,
        query: str,
        source_ids: list[int] | None = None,
        conversation_history: list[dict] | None = None,
        num_chunks: int = 5,
        temperature: float = 0.7,
        system_prompt: str | None = None,
    ) -> ChatResponse:
        """Generate a chat response using RAG."""
        # Search for relevant chunks
        results = self.vector_store.search(
            query=query,
            limit=num_chunks,
            source_ids=source_ids,
        )

        # Build context
        context = self._build_context(results) if results else "No relevant context found."

        # Build messages
        instructions = system_prompt or DEFAULT_SYSTEM_INSTRUCTIONS
        system = build_system_prompt(instructions, context)
        messages = [{"role": "system", "content": system}]

        # Add conversation history
        if conversation_history:
            messages.extend(conversation_history)

        # Add current query
        messages.append({"role": "user", "content": query})

        # Generate response
        answer = self.llm.chat(messages, temperature=temperature)

        # Build sources
        sources = self._build_sources(results)

        return ChatResponse(answer=answer, sources=sources)

    async def chat_stream(
        self,
        query: str,
        source_ids: list[int] | None = None,
        conversation_history: list[dict] | None = None,
        num_chunks: int = 5,
        temperature: float = 0.7,
        system_prompt: str | None = None,
    ) -> AsyncGenerator[str | list[ChatSource], None]:
        """Generate a streaming chat response using RAG.

        Yields chunks of the answer, then yields the sources list at the end.
        """
        # Search for relevant chunks
        results = self.vector_store.search(
            query=query,
            limit=num_chunks,
            source_ids=source_ids,
        )

        # Build context
        context = self._build_context(results) if results else "No relevant context found."

        # Build messages
        instructions = system_prompt or DEFAULT_SYSTEM_INSTRUCTIONS
        system = build_system_prompt(instructions, context)
        messages = [{"role": "system", "content": system}]

        # Add conversation history
        if conversation_history:
            messages.extend(conversation_history)

        # Add current query
        messages.append({"role": "user", "content": query})

        # Stream response
        async for chunk in self.llm.chat_stream(messages, temperature=temperature):
            yield chunk

        # Yield sources at the end
        sources = self._build_sources(results)
        yield sources


@lru_cache
def get_chat_service() -> ChatService:
    return ChatService()
