import logging
from urllib.parse import urljoin, urlparse

import httpx
from bs4 import BeautifulSoup

from app.config import USER_AGENT
from app.services.ingestion.base import BaseIngestionService, ExtractedContent
from app.services.ingestion.content_extractor import (
    ContentExtractor,
    parse_article_types,
)
from app.services.security import is_safe_url

logger = logging.getLogger(__name__)


class WebsiteIngestionService(BaseIngestionService):
    """Service for ingesting website content."""

    def __init__(self):
        super().__init__()
        self.visited_urls: set[str] = set()
        self.content_extractor = ContentExtractor()
        self.headers = {"User-Agent": USER_AGENT}

    def extract_content(self, source_config: dict) -> list[ExtractedContent]:
        """Extract content from website(s)."""
        url = source_config.get("url")
        crawl_depth = source_config.get("crawl_depth", 1)
        same_domain_only = source_config.get("same_domain_only", True)
        require_article_type = source_config.get("require_article_type", False)
        article_types = parse_article_types(source_config.get("article_types"))
        min_content_length = source_config.get("min_content_length", 0)

        if not url:
            raise ValueError("URL is required for website ingestion")

        # SSRF protection: validate URL before fetching
        is_safe, error_msg = is_safe_url(url)
        if not is_safe:
            raise ValueError(f"URL validation failed: {error_msg}")

        self.visited_urls.clear()
        base_domain = urlparse(url).netloc

        results = []
        self._crawl(
            url=url,
            base_domain=base_domain,
            depth=crawl_depth,
            same_domain_only=same_domain_only,
            results=results,
            require_article_type=require_article_type,
            article_types=article_types,
            min_content_length=min_content_length,
        )
        return results

    def _crawl(
        self,
        url: str,
        base_domain: str,
        depth: int,
        same_domain_only: bool,
        results: list[ExtractedContent],
        require_article_type: bool = False,
        article_types: set[str] | None = None,
        min_content_length: int = 0,
    ):
        """Recursively crawl pages."""
        if depth < 0 or url in self.visited_urls:
            return

        # Normalize URL
        url = self._normalize_url(url)
        if url in self.visited_urls:
            return

        self.visited_urls.add(url)

        # SSRF protection: validate each URL before fetching
        is_safe, error_msg = is_safe_url(url)
        if not is_safe:
            logger.warning(f"Skipping unsafe URL {url}: {error_msg}")
            return

        # Check domain
        if same_domain_only and urlparse(url).netloc != base_domain:
            return

        try:
            # Fetch page
            with httpx.Client(timeout=30, follow_redirects=True) as client:
                response = client.get(url, headers=self.headers)
                response.raise_for_status()

            # Check content type
            content_type = response.headers.get("content-type", "")
            if "text/html" not in content_type.lower():
                return

            html = response.text

            # Parse HTML for link extraction
            soup = BeautifulSoup(html, "lxml")

            # Extract content using ContentExtractor
            content = self._extract_page_content(
                html=html,
                url=url,
                require_article_type=require_article_type,
                article_types=article_types,
                min_content_length=min_content_length,
            )
            if content and content.content.strip():
                results.append(content)

            # Find links for deeper crawling
            if depth > 0:
                links = self._extract_links(soup, url)
                for link in links:
                    self._crawl(
                        url=link,
                        base_domain=base_domain,
                        depth=depth - 1,
                        same_domain_only=same_domain_only,
                        results=results,
                        require_article_type=require_article_type,
                        article_types=article_types,
                        min_content_length=min_content_length,
                    )

        except Exception as e:
            # Log error but continue with other pages
            logger.error(f"Error crawling {url}: {e}")

    def _normalize_url(self, url: str) -> str:
        """Normalize URL for deduplication."""
        parsed = urlparse(url)
        # Remove fragments and trailing slashes
        normalized = f"{parsed.scheme}://{parsed.netloc}{parsed.path.rstrip('/')}"
        if parsed.query:
            normalized += f"?{parsed.query}"
        return normalized

    def _extract_page_content(
        self,
        html: str,
        url: str,
        require_article_type: bool = False,
        article_types: set[str] | None = None,
        min_content_length: int = 0,
    ) -> ExtractedContent | None:
        """Extract content from a page using trafilatura."""
        result = self.content_extractor.extract(
            html=html,
            url=url,
            require_article_type=require_article_type,
            article_types=article_types,
            min_content_length=min_content_length,
        )

        if not result:
            return None

        # Build metadata
        soup = BeautifulSoup(html, "lxml")
        metadata = self._extract_metadata(soup)
        metadata["is_article"] = result.is_article
        if result.json_ld_type:
            metadata["json_ld_type"] = result.json_ld_type

        return ExtractedContent(
            title=result.title,
            content=result.content,
            url=url,
            published_at=result.published_at,
            metadata=metadata,
        )

    def _extract_links(self, soup: BeautifulSoup, base_url: str) -> list[str]:
        """Extract links from a page."""
        links = []
        for a_tag in soup.find_all("a", href=True):
            href = a_tag["href"]

            # Skip non-http links
            if href.startswith(("javascript:", "mailto:", "tel:", "#")):
                continue

            # Make absolute URL
            absolute_url = urljoin(base_url, href)

            # Only keep http(s) URLs
            if absolute_url.startswith(("http://", "https://")):
                links.append(absolute_url)

        return list(set(links))

    def _extract_metadata(self, soup: BeautifulSoup) -> dict:
        """Extract metadata from page."""
        metadata = {}

        # Meta description
        meta_desc = soup.find("meta", attrs={"name": "description"})
        if meta_desc and meta_desc.get("content"):
            metadata["description"] = meta_desc["content"]

        # Meta keywords
        meta_keywords = soup.find("meta", attrs={"name": "keywords"})
        if meta_keywords and meta_keywords.get("content"):
            metadata["keywords"] = meta_keywords["content"]

        # Open Graph
        og_title = soup.find("meta", property="og:title")
        if og_title and og_title.get("content"):
            metadata["og_title"] = og_title["content"]

        og_desc = soup.find("meta", property="og:description")
        if og_desc and og_desc.get("content"):
            metadata["og_description"] = og_desc["content"]

        return metadata
