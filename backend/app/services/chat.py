import re
from collections.abc import AsyncGenerator
from dataclasses import dataclass
from functools import lru_cache
from itertools import groupby

from sqlalchemy.orm import Session

from app.config import get_settings
from app.services.llm import LLMService, get_llm_service
from app.services.date_filter import DateFilter
from app.services.query_enrichment import (
    EnrichmentResult,
    QueryEnrichmentService,
    get_query_enrichment_service,
)
from app.services.vector_store import SearchResult, VectorStoreService, get_vector_store

settings = get_settings()

# Rough estimate: 1 token ≈ 4 characters
CHARS_PER_TOKEN = 4

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
    pattern = r"\[(\d+)\]"
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
        query_enrichment_service: QueryEnrichmentService | None = None,
    ):
        self.llm = llm_service or get_llm_service()
        self.vector_store = vector_store or get_vector_store()
        self.query_enrichment = query_enrichment_service or get_query_enrichment_service()

    def _enrich_query(
        self,
        query: str,
        conversation_history: list[dict] | None = None,
        custom_prompt: str | None = None,
        available_sources: list[dict] | None = None,
    ) -> EnrichmentResult:
        """Enrich the query for better retrieval."""
        return self.query_enrichment.enrich_query(
            query=query,
            conversation_history=conversation_history,
            custom_prompt=custom_prompt,
            available_sources=available_sources,
        )

    def _get_available_sources(self, db: Session) -> list[dict]:
        """Fetch available sources from the database for source matching.

        Args:
            db: Database session

        Returns:
            List of source dicts with id, name, url
        """
        from app.models.source import Source, SourceStatus

        sources = db.query(Source).filter(Source.status == SourceStatus.READY).all()
        return [
            {"id": source.id, "name": source.name, "url": source.url}
            for source in sources
        ]

    def _merge_source_ids(
        self,
        explicit_ids: list[int] | None,
        enriched_ids: list[int] | None,
    ) -> list[int] | None:
        """Merge explicit source IDs with enriched ones.

        Explicit IDs from UI selection take precedence over enriched IDs
        from natural language parsing.

        Args:
            explicit_ids: Source IDs explicitly passed by the caller
            enriched_ids: Source IDs extracted from the query by enrichment

        Returns:
            Merged source IDs, or None if no filtering needed
        """
        # Explicit IDs from UI selection take precedence
        if explicit_ids:
            return explicit_ids
        # Fall back to enriched IDs from natural language
        return enriched_ids

    def _expand_to_full_documents(
        self,
        results: list[SearchResult],
        db: Session,
        score_threshold: float = 0.85,
        max_chars: int = 10000,
    ) -> dict[int, str]:
        """Fetch full document content for high-scoring results.

        Args:
            results: Search results to check
            db: Database session
            score_threshold: Minimum score to trigger full doc retrieval
            max_chars: Maximum characters to include from each document

        Returns:
            Dict mapping document_id -> full content (truncated to max_chars)
        """
        from app.models.document import Document

        # Identify high-scoring documents
        high_score_doc_ids = {r.document_id for r in results if r.score >= score_threshold}

        if not high_score_doc_ids:
            return {}

        # Fetch documents from database
        documents = (
            db.query(Document)
            .filter(Document.id.in_(high_score_doc_ids))
            .filter(Document.content.isnot(None))
            .all()
        )

        # Build result dict with truncated content
        full_docs = {}
        for doc in documents:
            if doc.content:
                content = doc.content
                if len(content) > max_chars:
                    content = content[:max_chars] + "\n\n[... content truncated ...]"
                full_docs[doc.id] = content

        return full_docs

    def _build_context(
        self,
        results: list[SearchResult],
        full_docs: dict[int, str] | None = None,
        max_tokens: int = 16000,
    ) -> str:
        """Build context string from search results.

        Groups consecutive chunks from the same document into single source blocks.
        Uses full document content when available for high-scoring matches.
        Enforces token budget.

        Args:
            results: Search results (should be sorted by doc_id, chunk_index)
            full_docs: Optional dict of document_id -> full content
            max_tokens: Maximum tokens to include in context

        Returns:
            Formatted context string
        """
        if not results:
            return "No relevant context found."

        full_docs = full_docs or {}
        max_chars = max_tokens * CHARS_PER_TOKEN
        context_parts = []
        current_chars = 0
        source_num = 0

        # Group results by document_id
        for doc_id, doc_results in groupby(results, key=lambda r: r.document_id):
            doc_results = list(doc_results)
            if not doc_results:
                continue

            source_num += 1

            # Get source info from first result
            first_result = doc_results[0]
            source_info = []
            if first_result.metadata.get("title"):
                source_info.append(f"Title: {first_result.metadata['title']}")
            if first_result.metadata.get("url"):
                source_info.append(f"URL: {first_result.metadata['url']}")

            source_header = f"[Source {source_num}]"
            if source_info:
                source_header += f" ({', '.join(source_info)})"

            # Check if we should use full document content
            if doc_id in full_docs:
                content = full_docs[doc_id]
                source_header += " [Full Document]"
            else:
                # Merge consecutive chunks, removing duplicates
                seen_indices = set()
                merged_chunks = []
                for result in doc_results:
                    chunk_idx = result.metadata.get("chunk_index", 0)
                    if chunk_idx not in seen_indices:
                        seen_indices.add(chunk_idx)
                        merged_chunks.append((chunk_idx, result.content))

                # Sort by chunk index and join
                merged_chunks.sort(key=lambda x: x[0])
                content = "\n\n".join(chunk[1] for chunk in merged_chunks)

            # Check token budget
            entry = f"{source_header}\n{content}"
            entry_chars = len(entry)

            if current_chars + entry_chars > max_chars:
                # Check if we have room for at least something
                remaining_chars = max_chars - current_chars
                if remaining_chars > 500:  # Include partial if meaningful
                    truncated_content = content[: remaining_chars - len(source_header) - 50]
                    truncated_content += "\n\n[... truncated due to context limit ...]"
                    entry = f"{source_header}\n{truncated_content}"
                    context_parts.append(entry)
                break

            context_parts.append(entry)
            current_chars += entry_chars + 10  # Account for separator

        return "\n\n---\n\n".join(context_parts)

    def _build_sources(
        self,
        results: list[SearchResult],
        cited_indices: set[int] | None = None,
        full_docs: dict[int, str] | None = None,
    ) -> list[ChatSource]:
        """Build source citations from search results.

        Groups chunks by document to match the context format.
        Source indices correspond to [Source N] in the context.
        """
        cited_indices = cited_indices or set()
        full_docs = full_docs or {}
        sources = []
        source_num = 0

        # Group by document_id (same grouping as _build_context)
        for doc_id, doc_results in groupby(results, key=lambda r: r.document_id):
            doc_results = list(doc_results)
            if not doc_results:
                continue

            source_num += 1
            first_result = doc_results[0]

            # Get the best score from all chunks of this document
            best_score = max(r.score for r in doc_results)

            # Build content preview
            if doc_id in full_docs:
                full_content = full_docs[doc_id]
                preview = full_content[:200] + "..." if len(full_content) > 200 else full_content
            else:
                # Combine chunk previews
                all_content = " ".join(r.content for r in doc_results)
                preview = all_content[:200] + "..." if len(all_content) > 200 else all_content

            sources.append(
                ChatSource(
                    source_index=source_num,
                    document_id=first_result.document_id,
                    source_id=first_result.source_id,
                    title=first_result.metadata.get("title"),
                    url=first_result.metadata.get("url"),
                    content_preview=preview,
                    score=best_score,
                    cited=(source_num in cited_indices),
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
        enable_enrichment: bool = False,
        enrichment_prompt: str | None = None,
        db: Session | None = None,
        context_window_size: int = 0,
        full_doc_score_threshold: float = 0.85,
        max_full_doc_chars: int = 10000,
        max_context_tokens: int = 16000,
    ) -> ChatResponse:
        """Generate a chat response using RAG.

        Args:
            query: User's question
            source_ids: Optional filter by source IDs
            conversation_history: Previous messages for context
            num_chunks: Number of chunks to retrieve
            temperature: LLM temperature
            system_prompt: Custom system prompt
            enable_enrichment: Whether to enrich the query
            enrichment_prompt: Custom enrichment prompt
            db: Database session for full document retrieval
            context_window_size: Number of adjacent chunks to include
            full_doc_score_threshold: Score threshold for full doc retrieval
            max_full_doc_chars: Max chars for full document content
            max_context_tokens: Token budget for context
        """
        # Enrich query if enabled
        search_query = query
        date_filter: DateFilter | None = None
        enriched_source_ids: list[int] | None = None
        if enable_enrichment:
            # Fetch available sources for source matching
            available_sources = self._get_available_sources(db) if db else None
            result = self._enrich_query(
                query=query,
                conversation_history=conversation_history,
                custom_prompt=enrichment_prompt,
                available_sources=available_sources,
            )
            if result.success:
                search_query = result.enriched_query
                print(f"[Chat] Enriched query: {query!r} -> {search_query!r}")
            if result.date_filter and result.date_filter.is_active():
                date_filter = result.date_filter
                print(f"[Chat] Date filter extracted: {date_filter}")
            if result.source_ids:
                enriched_source_ids = result.source_ids
                print(f"[Chat] Source filter extracted: {enriched_source_ids}")

        # Merge explicit source_ids with enriched ones (explicit takes precedence)
        effective_source_ids = self._merge_source_ids(source_ids, enriched_source_ids)

        # Lower score threshold when source filtering is active, since user
        # explicitly wants content from specific sources (e.g., "recap Bitcoin Magazine")
        search_score_threshold = 0.3 if effective_source_ids else 0.5

        # Search for relevant chunks using (possibly enriched) query
        # Use search_with_context if context_window_size > 0
        if context_window_size > 0:
            results = self.vector_store.search_with_context(
                query=search_query,
                limit=num_chunks,
                source_ids=effective_source_ids,
                context_window=context_window_size,
                date_filter=date_filter,
                score_threshold=search_score_threshold,
            )
        else:
            results = self.vector_store.search(
                query=search_query,
                limit=num_chunks,
                source_ids=effective_source_ids,
                date_filter=date_filter,
                score_threshold=search_score_threshold,
            )

        # Expand to full documents for high-scoring results
        full_docs = {}
        if db and results:
            full_docs = self._expand_to_full_documents(
                results=results,
                db=db,
                score_threshold=full_doc_score_threshold,
                max_chars=max_full_doc_chars,
            )

        # Build context
        context = (
            self._build_context(results, full_docs=full_docs, max_tokens=max_context_tokens)
            if results
            else "No relevant context found."
        )

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
        # Count unique documents for valid citation range
        unique_docs = len(set(r.document_id for r in results)) if results else 0
        valid_cited = {i for i in cited_indices if 1 <= i <= unique_docs}
        sources = self._build_sources(results, valid_cited, full_docs)

        return ChatResponse(answer=answer, sources=sources, cited_indices=sorted(valid_cited))

    async def chat_stream(
        self,
        query: str,
        source_ids: list[int] | None = None,
        conversation_history: list[dict] | None = None,
        num_chunks: int = 5,
        temperature: float = 0.7,
        system_prompt: str | None = None,
        enable_enrichment: bool = False,
        enrichment_prompt: str | None = None,
        db: Session | None = None,
        context_window_size: int = 0,
        full_doc_score_threshold: float = 0.85,
        max_full_doc_chars: int = 10000,
        max_context_tokens: int = 16000,
    ) -> AsyncGenerator[str | dict, None]:
        """Generate a streaming chat response using RAG.

        Yields chunks of the answer (str), then yields a dict with sources and cited_indices at the end.

        Args:
            query: User's question
            source_ids: Optional filter by source IDs
            conversation_history: Previous messages for context
            num_chunks: Number of chunks to retrieve
            temperature: LLM temperature
            system_prompt: Custom system prompt
            enable_enrichment: Whether to enrich the query
            enrichment_prompt: Custom enrichment prompt
            db: Database session for full document retrieval
            context_window_size: Number of adjacent chunks to include
            full_doc_score_threshold: Score threshold for full doc retrieval
            max_full_doc_chars: Max chars for full document content
            max_context_tokens: Token budget for context
        """
        # Enrich query if enabled
        search_query = query
        date_filter: DateFilter | None = None
        enriched_source_ids: list[int] | None = None
        if enable_enrichment:
            # Fetch available sources for source matching
            available_sources = self._get_available_sources(db) if db else None
            result = self._enrich_query(
                query=query,
                conversation_history=conversation_history,
                custom_prompt=enrichment_prompt,
                available_sources=available_sources,
            )
            if result.success:
                search_query = result.enriched_query
                print(f"[ChatStream] Enriched query: {query!r} -> {search_query!r}")
            if result.date_filter and result.date_filter.is_active():
                date_filter = result.date_filter
                print(f"[ChatStream] Date filter extracted: {date_filter}")
            if result.source_ids:
                enriched_source_ids = result.source_ids
                print(f"[ChatStream] Source filter extracted: {enriched_source_ids}")

        # Merge explicit source_ids with enriched ones (explicit takes precedence)
        effective_source_ids = self._merge_source_ids(source_ids, enriched_source_ids)

        # Lower score threshold when source filtering is active, since user
        # explicitly wants content from specific sources (e.g., "recap Bitcoin Magazine")
        search_score_threshold = 0.3 if effective_source_ids else 0.5

        # Search for relevant chunks using (possibly enriched) query
        # Use search_with_context if context_window_size > 0
        if context_window_size > 0:
            results = self.vector_store.search_with_context(
                query=search_query,
                limit=num_chunks,
                source_ids=effective_source_ids,
                context_window=context_window_size,
                date_filter=date_filter,
                score_threshold=search_score_threshold,
            )
        else:
            results = self.vector_store.search(
                query=search_query,
                limit=num_chunks,
                source_ids=effective_source_ids,
                date_filter=date_filter,
                score_threshold=search_score_threshold,
            )

        # Expand to full documents for high-scoring results
        full_docs = {}
        if db and results:
            full_docs = self._expand_to_full_documents(
                results=results,
                db=db,
                score_threshold=full_doc_score_threshold,
                max_chars=max_full_doc_chars,
            )

        # Build context
        context = (
            self._build_context(results, full_docs=full_docs, max_tokens=max_context_tokens)
            if results
            else "No relevant context found."
        )

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
        # Count unique documents for valid citation range
        unique_docs = len(set(r.document_id for r in results)) if results else 0
        valid_cited = {i for i in cited_indices if 1 <= i <= unique_docs}
        sources = self._build_sources(results, valid_cited, full_docs)

        # Yield sources and citation info at the end
        yield {"sources": sources, "cited_indices": sorted(valid_cited)}


@lru_cache
def get_chat_service() -> ChatService:
    return ChatService()
