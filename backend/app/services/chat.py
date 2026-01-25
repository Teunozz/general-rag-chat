import re
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
3. ALWAYS cite your sources using bracketed numbers like [1], [2] that match the source numbers provided
4. Place citations after relevant information, e.g., "The answer is 42 [1]."
5. Be concise but thorough in your answers
6. If asked about something not in the context, explain that you can only answer based on the available documents"""


def extract_citations(response: str) -> set[int]:
    """Extract cited source numbers from the response."""
    pattern = r'\[(\d+)\]'
    matches = re.findall(pattern, response)
    return {int(m) for m in matches if int(m) > 0}


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

    source_index: int  # The [N] number used in context
    document_id: int
    source_id: int
    title: str | None
    url: str | None
    content_preview: str
    score: float
    cited: bool = False  # Whether this source was cited


@dataclass
class ChatResponse:
    """Chat response with sources."""

    answer: str
    sources: list[ChatSource]
    cited_indices: list[int]  # List of source indices that were cited


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

    def _build_sources(
        self, results: list[SearchResult], cited_indices: set[int] | None = None
    ) -> list[ChatSource]:
        """Build source citations from search results.

        Each chunk is shown as a separate source so indices match what the LLM cited.
        Multiple chunks from the same document will show the same title/URL.
        """
        cited_indices = cited_indices or set()
        sources = []

        for i, result in enumerate(results, 1):
            sources.append(
                ChatSource(
                    source_index=i,
                    document_id=result.document_id,
                    source_id=result.source_id,
                    title=result.metadata.get("title"),
                    url=result.metadata.get("url"),
                    content_preview=result.content[:200] + "..."
                    if len(result.content) > 200
                    else result.content,
                    score=result.score,
                    cited=(i in cited_indices),
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

        # Extract citations and build sources
        cited_indices = extract_citations(answer)
        # Filter to valid indices only
        valid_cited = {i for i in cited_indices if 1 <= i <= len(results)}
        sources = self._build_sources(results, valid_cited)

        return ChatResponse(
            answer=answer, sources=sources, cited_indices=sorted(valid_cited)
        )

    async def chat_stream(
        self,
        query: str,
        source_ids: list[int] | None = None,
        conversation_history: list[dict] | None = None,
        num_chunks: int = 5,
        temperature: float = 0.7,
        system_prompt: str | None = None,
    ) -> AsyncGenerator[str | dict, None]:
        """Generate a streaming chat response using RAG.

        Yields chunks of the answer (str), then yields a dict with sources and cited_indices at the end.
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

        # Stream response and buffer full text
        full_response = ""
        async for chunk in self.llm.chat_stream(messages, temperature=temperature):
            full_response += chunk
            yield chunk

        # Extract citations and build sources
        cited_indices = extract_citations(full_response)
        valid_cited = {i for i in cited_indices if 1 <= i <= len(results)}
        sources = self._build_sources(results, valid_cited)

        # Yield sources and citation info at the end
        yield {"sources": sources, "cited_indices": sorted(valid_cited)}


@lru_cache
def get_chat_service() -> ChatService:
    return ChatService()
