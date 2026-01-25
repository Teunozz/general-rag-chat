from dataclasses import dataclass

from app.services.llm import LLMService, get_llm_service

DEFAULT_ENRICHMENT_PROMPT = """You are a query rewriting assistant for a document search system. Your task is to improve the user's search query for better retrieval.

Instructions:
1. Expand pronouns and references using the conversation context (e.g., "it" -> "the authentication system")
2. Add relevant synonyms if helpful
3. Clarify ambiguous terms
4. Keep the output concise (under 50 words)
5. Output ONLY the rewritten query, nothing else

If the query is already clear and self-contained, return it unchanged."""


@dataclass
class EnrichmentResult:
    """Result of query enrichment."""

    original_query: str
    enriched_query: str
    success: bool


class QueryEnrichmentService:
    """Service for enriching user queries before vector search."""

    def __init__(self, llm_service: LLMService | None = None):
        self.llm = llm_service or get_llm_service()

    def enrich_query(
        self,
        query: str,
        conversation_history: list[dict] | None = None,
        custom_prompt: str | None = None,
    ) -> EnrichmentResult:
        """Enrich a query using the LLM for better retrieval.

        Args:
            query: The original user query
            conversation_history: Previous messages for context
            custom_prompt: Optional custom enrichment prompt

        Returns:
            EnrichmentResult with original and enriched queries
        """
        try:
            # Build the enrichment prompt
            system_prompt = custom_prompt or DEFAULT_ENRICHMENT_PROMPT

            messages = [{"role": "system", "content": system_prompt}]

            # Add conversation context if available
            if conversation_history:
                context_text = self._format_conversation_context(conversation_history)
                messages.append({
                    "role": "user",
                    "content": f"Conversation context:\n{context_text}\n\nQuery to rewrite: {query}",
                })
            else:
                messages.append({
                    "role": "user",
                    "content": f"Query to rewrite: {query}",
                })

            # Use low temperature for more deterministic rewrites
            enriched = self.llm.chat(messages, temperature=0.3)

            # Clean up the response
            enriched = enriched.strip()

            # If the enrichment is empty or too long, fall back to original
            if not enriched or len(enriched) > 500:
                return EnrichmentResult(
                    original_query=query,
                    enriched_query=query,
                    success=False,
                )

            return EnrichmentResult(
                original_query=query,
                enriched_query=enriched,
                success=True,
            )

        except Exception as e:
            # Graceful degradation: return original query on failure
            print(f"[QueryEnrichment] Error enriching query: {e}")
            return EnrichmentResult(
                original_query=query,
                enriched_query=query,
                success=False,
            )

    def _format_conversation_context(
        self, conversation_history: list[dict], max_messages: int = 6
    ) -> str:
        """Format conversation history for the enrichment prompt.

        Only includes the most recent messages to keep context manageable.
        """
        # Take the most recent messages
        recent = conversation_history[-max_messages:]

        parts = []
        for msg in recent:
            role = msg.get("role", "user").upper()
            content = msg.get("content", "")
            # Truncate long messages
            if len(content) > 300:
                content = content[:300] + "..."
            parts.append(f"{role}: {content}")

        return "\n".join(parts)


def get_query_enrichment_service() -> QueryEnrichmentService:
    """Get a QueryEnrichmentService instance."""
    return QueryEnrichmentService()
