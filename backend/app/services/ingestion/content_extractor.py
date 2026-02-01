"""Content extraction module using trafilatura with optional JSON-LD filtering."""

import json
import logging
from dataclasses import dataclass
from datetime import datetime

import trafilatura
from bs4 import BeautifulSoup
from dateutil import parser as date_parser

logger = logging.getLogger(__name__)

DEFAULT_ARTICLE_TYPES = {
    "Article",
    "NewsArticle",
    "BlogPosting",
    "ScholarlyArticle",
    "TechArticle",
    "Report",
}


@dataclass
class ExtractionResult:
    """Result of content extraction."""

    title: str
    content: str
    is_article: bool = False
    json_ld_type: str | None = None
    published_at: datetime | None = None


class ContentExtractor:
    """Extract clean content from HTML using trafilatura with optional JSON-LD filtering."""

    def extract(
        self,
        html: str,
        url: str,
        require_article_type: bool = False,
        article_types: set[str] | None = None,
        min_content_length: int = 0,
    ) -> ExtractionResult | None:
        """
        Extract content using trafilatura + optional JSON-LD filtering.

        Args:
            html: Raw HTML content
            url: URL of the page (used for trafilatura metadata extraction)
            require_article_type: If True, skip pages without matching JSON-LD type
            article_types: Set of allowed JSON-LD @type values. Uses DEFAULT_ARTICLE_TYPES if None.
            min_content_length: Minimum content length to accept

        Returns:
            ExtractionResult if content extracted, None if page should be skipped
        """
        soup = BeautifulSoup(html, "lxml")

        # Parse JSON-LD data
        json_ld_data = self._extract_json_ld(soup)
        allowed_types = article_types or DEFAULT_ARTICLE_TYPES
        is_article, matched_type = self._check_article_type(json_ld_data, allowed_types)

        # If article type required but not found, skip this page
        if require_article_type and not is_article:
            logger.debug(f"Skipping {url}: no matching JSON-LD article type found")
            return None

        # Extract publish date from JSON-LD (with trafilatura fallback)
        published_at = self._extract_publish_date(json_ld_data, html, url)

        # Extract content using trafilatura
        extracted = trafilatura.extract(
            html,
            url=url,
            include_comments=False,
            include_tables=True,
            no_fallback=False,
            favor_precision=True,
        )

        if not extracted:
            logger.debug(f"Trafilatura could not extract content from {url}")
            return None

        # Check minimum content length
        if len(extracted) < min_content_length:
            logger.debug(
                f"Skipping {url}: content too short ({len(extracted)} < {min_content_length})"
            )
            return None

        # Get title - try trafilatura metadata first, then fallback to HTML
        title = self._extract_title(html, url, soup)

        return ExtractionResult(
            title=title,
            content=extracted,
            is_article=is_article,
            json_ld_type=matched_type,
            published_at=published_at,
        )

    def _extract_title(self, html: str, url: str, soup: BeautifulSoup) -> str:
        """Extract page title."""
        # Try trafilatura metadata
        metadata = trafilatura.extract_metadata(html, default_url=url)
        if metadata and metadata.title:
            return metadata.title

        # Fallback to HTML title tag
        if soup.title:
            return soup.title.get_text(strip=True)

        # Fallback to first H1
        h1 = soup.find("h1")
        if h1:
            return h1.get_text(strip=True)

        return url

    def _extract_json_ld(self, soup: BeautifulSoup) -> list[dict]:
        """Parse JSON-LD scripts from page."""
        json_ld_data = []

        for script in soup.find_all("script", type="application/ld+json"):
            try:
                content = script.string
                if content:
                    data = json.loads(content)
                    # Handle both single objects and arrays
                    if isinstance(data, list):
                        json_ld_data.extend(data)
                    else:
                        json_ld_data.append(data)
            except (json.JSONDecodeError, TypeError) as e:
                logger.debug(f"Failed to parse JSON-LD: {e}")
                continue

        return json_ld_data

    def _check_article_type(
        self, json_ld_data: list[dict], allowed_types: set[str]
    ) -> tuple[bool, str | None]:
        """
        Check if page has matching JSON-LD @type.

        Returns:
            Tuple of (is_article, matched_type)
        """
        for item in json_ld_data:
            item_type = item.get("@type")

            # Check direct @type if present
            if item_type:
                # Handle both string and list types
                types = [item_type] if isinstance(item_type, str) else item_type

                for t in types:
                    if t in allowed_types:
                        return True, t

            # Check @graph if present (common in some JSON-LD implementations)
            # This runs regardless of whether @type exists on the wrapper
            graph = item.get("@graph", [])
            for graph_item in graph:
                graph_type = graph_item.get("@type")
                if not graph_type:
                    continue
                graph_types = [graph_type] if isinstance(graph_type, str) else graph_type
                for t in graph_types:
                    if t in allowed_types:
                        return True, t

        return False, None

    def _extract_publish_date(
        self, json_ld_data: list[dict], html: str, url: str
    ) -> datetime | None:
        """Extract datePublished from JSON-LD, with trafilatura fallback."""
        # Try JSON-LD first
        for item in json_ld_data:
            date_str = item.get("datePublished")
            if date_str:
                parsed = self._parse_date(date_str)
                if parsed:
                    return parsed

            # Check @graph
            for graph_item in item.get("@graph", []):
                date_str = graph_item.get("datePublished")
                if date_str:
                    parsed = self._parse_date(date_str)
                    if parsed:
                        return parsed

        # Fallback to trafilatura metadata
        metadata = trafilatura.extract_metadata(html, default_url=url)
        if metadata and metadata.date:
            return self._parse_date(metadata.date)

        return None

    def _parse_date(self, date_str: str) -> datetime | None:
        """Parse date string to datetime."""
        try:
            return date_parser.parse(date_str)
        except (ValueError, TypeError):
            return None


def parse_article_types(article_types_str: str | None) -> set[str] | None:
    """
    Parse comma-separated article types string into a set.

    Args:
        article_types_str: Comma-separated string like "Article,NewsArticle,BlogPosting"

    Returns:
        Set of article types, or None if input is None/empty
    """
    if not article_types_str:
        return None
    return {t.strip() for t in article_types_str.split(",") if t.strip()}
