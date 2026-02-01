import json
import logging
import re
from dataclasses import dataclass
from datetime import datetime

from app.services.date_filter import DateFilter
from app.services.llm import LLMService, get_llm_service

logger = logging.getLogger(__name__)


DEFAULT_ENRICHMENT_PROMPT = """You are a query rewriting assistant for a document search system. Your task is to improve the user's search query for better retrieval, extract temporal constraints, and identify source references.

Instructions:
1. Expand pronouns and references using the conversation context (e.g., "it" -> "the authentication system")
2. Add relevant synonyms if helpful
3. Clarify ambiguous terms
4. Keep the rewritten query concise (under 50 words)
5. Identify temporal expressions and convert them to date ranges
6. Remove temporal expressions from the rewritten query (they will be applied as filters)
7. Identify source references and match them to available sources

Temporal expressions to detect:
- "latest", "recent", "new" -> last 7 days
- "last week" -> last 7 days
- "past month", "last month" -> last 30 days
- "this year" -> from January 1 of current year
- "yesterday" -> yesterday only
- "last N days" -> last N days
- "from YYYY-MM-DD to YYYY-MM-DD" -> specific range
- "after YYYY-MM-DD" -> from that date to now
- "before YYYY-MM-DD" -> from epoch to that date

Source matching rules:
- Match partial names (e.g., "Bitcoin Mag" matches "Bitcoin Magazine")
- Match by domain if URL is mentioned (e.g., "from techcrunch" matches techcrunch.com)
- Be case-insensitive when matching
- If multiple sources match, include all of them
- Only match if there is clear intent to filter by source
- Remove source references from the rewritten query (they will be applied as filters)

Today's date is: {today}

{sources_section}

Output your response as JSON with this structure:
{{
    "rewritten_query": "the improved search query without temporal expressions or source references",
    "date_filter": {{
        "expression": "the original temporal expression or null",
        "start_date": "YYYY-MM-DD or null",
        "end_date": "YYYY-MM-DD or null"
    }},
    "source_filter": {{
        "expression": "the original source reference or null",
        "source_ids": [list of matched source IDs or empty array]
    }}
}}

If there is no temporal constraint, set date_filter to null.
If there is no source reference or no available sources, set source_filter to null.
Only output valid JSON, nothing else."""


@dataclass
class EnrichmentResult:
    """Result of query enrichment."""

    original_query: str
    enriched_query: str
    success: bool
    date_filter: DateFilter | None = None
    source_ids: list[int] | None = None


class QueryEnrichmentService:
    """Service for enriching user queries before vector search."""

    def __init__(self, llm_service: LLMService | None = None):
        self.llm = llm_service or get_llm_service()

    def enrich_query(
        self,
        query: str,
        conversation_history: list[dict] | None = None,
        custom_prompt: str | None = None,
        available_sources: list[dict] | None = None,
    ) -> EnrichmentResult:
        """Enrich a query using the LLM for better retrieval.

        Args:
            query: The original user query
            conversation_history: Previous messages for context
            custom_prompt: Optional custom enrichment prompt
            available_sources: List of source dicts with id, name, url for source matching

        Returns:
            EnrichmentResult with original and enriched queries
        """
        try:
            # Build the enrichment prompt with today's date and available sources
            today = datetime.now().strftime("%Y-%m-%d")
            sources_section = self._format_sources_section(available_sources)
            base_prompt = custom_prompt or DEFAULT_ENRICHMENT_PROMPT
            system_prompt = base_prompt.format(today=today, sources_section=sources_section)

            messages = [{"role": "system", "content": system_prompt}]

            # Add conversation context if available
            if conversation_history:
                context_text = self._format_conversation_context(conversation_history)
                messages.append(
                    {
                        "role": "user",
                        "content": f"Conversation context:\n{context_text}\n\nQuery to rewrite: {query}",
                    }
                )
            else:
                messages.append(
                    {
                        "role": "user",
                        "content": f"Query to rewrite: {query}",
                    }
                )

            # Use low temperature for more deterministic rewrites
            response = self.llm.chat(messages, temperature=0.3)

            # Parse the JSON response
            enriched_query, date_filter, source_ids = self._parse_enrichment_response(
                response, query
            )

            # If the enrichment is empty or too long, fall back to original
            if not enriched_query or len(enriched_query) > 500:
                return EnrichmentResult(
                    original_query=query,
                    enriched_query=query,
                    success=False,
                    date_filter=date_filter,
                    source_ids=source_ids,
                )

            return EnrichmentResult(
                original_query=query,
                enriched_query=enriched_query,
                success=True,
                date_filter=date_filter,
                source_ids=source_ids,
            )

        except Exception as e:
            # Graceful degradation: return original query on failure
            logger.warning("Error enriching query: %s", e)
            return EnrichmentResult(
                original_query=query,
                enriched_query=query,
                success=False,
            )

    def _format_sources_section(self, available_sources: list[dict] | None) -> str:
        """Format available sources for the enrichment prompt.

        Args:
            available_sources: List of source dicts with id, name, url

        Returns:
            Formatted sources section for the prompt
        """
        if not available_sources:
            return "No sources are available for filtering."

        # Limit to 50 sources to keep prompt manageable
        max_sources = 50
        sources_to_show = available_sources[:max_sources]
        remaining = len(available_sources) - max_sources

        lines = ["Available sources:"]
        for source in sources_to_show:
            source_id = source.get("id")
            name = source.get("name", "Untitled")
            url = source.get("url", "")
            if url:
                lines.append(f"- ID {source_id}: {name} ({url})")
            else:
                lines.append(f"- ID {source_id}: {name}")

        if remaining > 0:
            lines.append(f"... and {remaining} more sources")

        return "\n".join(lines)

    def _parse_enrichment_response(
        self, response: str, original_query: str
    ) -> tuple[str, DateFilter | None, list[int] | None]:
        """Parse the LLM JSON response into query, date filter, and source IDs.

        Args:
            response: The raw LLM response
            original_query: Fallback query if parsing fails

        Returns:
            Tuple of (enriched_query, date_filter, source_ids)
        """
        response = response.strip()

        # Try to extract JSON from the response
        # Handle cases where LLM wraps JSON in markdown code blocks
        json_match = re.search(r"```(?:json)?\s*(\{.*?\})\s*```", response, re.DOTALL)
        if json_match:
            response = json_match.group(1)

        try:
            data = json.loads(response)
        except json.JSONDecodeError:
            # Fall back to treating the response as a plain query
            return response, None, None

        enriched_query = data.get("rewritten_query", original_query)
        date_filter = None
        source_ids = None

        # Parse date filter if present
        date_data = data.get("date_filter")
        if date_data and isinstance(date_data, dict):
            start_date = self._parse_date(date_data.get("start_date"))
            end_date = self._parse_date(date_data.get("end_date"))
            expression = date_data.get("expression")

            if start_date or end_date:
                date_filter = DateFilter(
                    start_date=start_date,
                    end_date=end_date,
                    original_expression=expression,
                    include_undated=True,
                )

        # Parse source filter if present
        source_data = data.get("source_filter")
        if source_data and isinstance(source_data, dict):
            ids = source_data.get("source_ids")
            if ids and isinstance(ids, list) and len(ids) > 0:
                # Filter to valid integers only
                source_ids = [int(i) for i in ids if isinstance(i, (int, float))]
                if not source_ids:
                    source_ids = None

        return enriched_query, date_filter, source_ids

    def _parse_date(self, date_str: str | None) -> datetime | None:
        """Parse a date string into a datetime object.

        Args:
            date_str: Date string in YYYY-MM-DD format

        Returns:
            datetime object or None
        """
        if not date_str or date_str == "null":
            return None

        try:
            return datetime.strptime(date_str, "%Y-%m-%d")
        except ValueError:
            return None

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
