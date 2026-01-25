from datetime import datetime
from time import mktime

import feedparser
import httpx
from bs4 import BeautifulSoup

from app.services.ingestion.base import BaseIngestionService, ExtractedContent
from app.services.security import is_safe_url


class RSSIngestionService(BaseIngestionService):
    """Service for ingesting RSS/Atom feeds."""

    def __init__(self):
        super().__init__()
        self.headers = {
            "User-Agent": "Mozilla/5.0 (compatible; RAGBot/1.0)"
        }

    def extract_content(self, source_config: dict) -> list[ExtractedContent]:
        """Extract content from RSS feed."""
        url = source_config.get("url")
        fetch_full_content = source_config.get("fetch_full_content", True)
        max_items = source_config.get("max_items", 50)
        since_date = source_config.get("since_date")  # datetime or None

        if not url:
            raise ValueError("URL is required for RSS ingestion")

        # SSRF protection: validate URL before fetching
        is_safe, error_msg = is_safe_url(url)
        if not is_safe:
            raise ValueError(f"URL validation failed: {error_msg}")

        # Fetch and parse feed
        feed = feedparser.parse(url)

        if feed.bozo and not feed.entries:
            raise ValueError(f"Failed to parse feed: {feed.bozo_exception}")

        results = []
        for entry in feed.entries[:max_items]:
            # Check date filter
            if since_date and hasattr(entry, "published_parsed") and entry.published_parsed:
                entry_date = datetime.fromtimestamp(mktime(entry.published_parsed))
                if entry_date < since_date:
                    continue

            try:
                content = self._extract_entry(entry, fetch_full_content)
                if content and content.content.strip():
                    results.append(content)
            except Exception as e:
                print(f"Error extracting entry {entry.get('link', 'unknown')}: {e}")

        return results

    def _extract_entry(self, entry: dict, fetch_full_content: bool) -> ExtractedContent | None:
        """Extract content from a single feed entry."""
        title = entry.get("title", "Untitled")
        link = entry.get("link", "")

        # Get published date
        published_at = None
        if hasattr(entry, "published_parsed") and entry.published_parsed:
            published_at = datetime.fromtimestamp(mktime(entry.published_parsed))

        # Get content
        content = ""

        # Try to get content from feed first
        if hasattr(entry, "content") and entry.content:
            content = entry.content[0].get("value", "")
        elif hasattr(entry, "summary"):
            content = entry.summary
        elif hasattr(entry, "description"):
            content = entry.description

        # Clean HTML content
        if content:
            content = self._clean_html_content(content)

        # Optionally fetch full article content
        if fetch_full_content and link:
            try:
                full_content = self._fetch_full_article(link)
                if full_content and len(full_content) > len(content):
                    content = full_content
            except Exception as e:
                print(f"Could not fetch full content for {link}: {e}")

        if not content:
            return None

        # Extract metadata
        metadata = {
            "feed_title": entry.get("source", {}).get("title", ""),
            "author": entry.get("author", ""),
            "tags": [tag.get("term", "") for tag in entry.get("tags", [])],
        }

        return ExtractedContent(
            title=title,
            content=content,
            url=link,
            published_at=published_at,
            metadata=metadata,
        )

    def _clean_html_content(self, html_content: str) -> str:
        """Clean HTML content to plain text."""
        soup = BeautifulSoup(html_content, "lxml")

        # Remove scripts and styles
        for element in soup.find_all(["script", "style"]):
            element.decompose()

        # Get text
        text = soup.get_text(separator="\n", strip=True)
        return text

    def _fetch_full_article(self, url: str) -> str | None:
        """Fetch full article content from URL."""
        # SSRF protection: validate URL before fetching
        is_safe, error_msg = is_safe_url(url)
        if not is_safe:
            print(f"Skipping unsafe article URL {url}: {error_msg}")
            return None

        try:
            with httpx.Client(timeout=30, follow_redirects=True) as client:
                response = client.get(url, headers=self.headers)
                response.raise_for_status()

            content_type = response.headers.get("content-type", "")
            if "text/html" not in content_type.lower():
                return None

            soup = BeautifulSoup(response.text, "lxml")

            # Remove unwanted elements
            for element in soup.find_all(
                ["script", "style", "nav", "footer", "header", "aside", "noscript"]
            ):
                element.decompose()

            # Try to find article content
            article = (
                soup.find("article")
                or soup.find(class_=["article", "post-content", "entry-content", "content"])
                or soup.find("main")
            )

            if article:
                return article.get_text(separator="\n", strip=True)

            # Fallback to body
            body = soup.find("body")
            if body:
                return body.get_text(separator="\n", strip=True)

            return None
        except Exception:
            return None

    def get_feed_info(self, url: str) -> dict:
        """Get feed metadata without extracting all content."""
        # SSRF protection: validate URL before fetching
        is_safe, error_msg = is_safe_url(url)
        if not is_safe:
            raise ValueError(f"URL validation failed: {error_msg}")

        feed = feedparser.parse(url)

        if feed.bozo and not feed.entries:
            raise ValueError(f"Failed to parse feed: {feed.bozo_exception}")

        return {
            "title": feed.feed.get("title", ""),
            "description": feed.feed.get("description", ""),
            "link": feed.feed.get("link", ""),
            "entry_count": len(feed.entries),
            "last_updated": (
                datetime.fromtimestamp(mktime(feed.feed.updated_parsed))
                if hasattr(feed.feed, "updated_parsed") and feed.feed.updated_parsed
                else None
            ),
        }
