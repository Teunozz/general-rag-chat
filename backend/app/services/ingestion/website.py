import re
from urllib.parse import urljoin, urlparse

import httpx
from bs4 import BeautifulSoup

from app.services.ingestion.base import BaseIngestionService, ExtractedContent
from app.services.security import is_safe_url


class WebsiteIngestionService(BaseIngestionService):
    """Service for ingesting website content."""

    def __init__(self):
        super().__init__()
        self.visited_urls: set[str] = set()
        self.headers = {
            "User-Agent": "Mozilla/5.0 (compatible; RAGBot/1.0; +https://github.com/your-repo)"
        }

    def extract_content(self, source_config: dict) -> list[ExtractedContent]:
        """Extract content from website(s)."""
        url = source_config.get("url")
        crawl_depth = source_config.get("crawl_depth", 1)
        same_domain_only = source_config.get("same_domain_only", True)

        if not url:
            raise ValueError("URL is required for website ingestion")

        # SSRF protection: validate URL before fetching
        is_safe, error_msg = is_safe_url(url)
        if not is_safe:
            raise ValueError(f"URL validation failed: {error_msg}")

        self.visited_urls.clear()
        base_domain = urlparse(url).netloc

        results = []
        self._crawl(url, base_domain, crawl_depth, same_domain_only, results)
        return results

    def _crawl(
        self,
        url: str,
        base_domain: str,
        depth: int,
        same_domain_only: bool,
        results: list[ExtractedContent],
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
            print(f"Skipping unsafe URL {url}: {error_msg}")
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

            # Parse HTML
            soup = BeautifulSoup(response.text, "lxml")

            # Extract content
            content = self._extract_page_content(soup, url)
            if content and content.content.strip():
                results.append(content)

            # Find links for deeper crawling
            if depth > 0:
                links = self._extract_links(soup, url)
                for link in links:
                    self._crawl(link, base_domain, depth - 1, same_domain_only, results)

        except Exception as e:
            # Log error but continue with other pages
            print(f"Error crawling {url}: {e}")

    def _normalize_url(self, url: str) -> str:
        """Normalize URL for deduplication."""
        parsed = urlparse(url)
        # Remove fragments and trailing slashes
        normalized = f"{parsed.scheme}://{parsed.netloc}{parsed.path.rstrip('/')}"
        if parsed.query:
            normalized += f"?{parsed.query}"
        return normalized

    def _extract_page_content(self, soup: BeautifulSoup, url: str) -> ExtractedContent | None:
        """Extract content from a page."""
        # Get title
        title = ""
        if soup.title:
            title = soup.title.get_text(strip=True)
        elif soup.find("h1"):
            title = soup.find("h1").get_text(strip=True)

        # Remove unwanted elements
        for element in soup.find_all(
            ["script", "style", "nav", "footer", "header", "aside", "noscript", "iframe"]
        ):
            element.decompose()

        # Try to find main content
        main_content = (
            soup.find("main")
            or soup.find("article")
            or soup.find(class_=re.compile(r"(content|main|article|post)", re.I))
            or soup.find("body")
        )

        if not main_content:
            return None

        # Extract text
        text = main_content.get_text(separator="\n", strip=True)

        # Clean up text
        text = self._clean_text(text)

        if not text:
            return None

        # Extract metadata
        metadata = self._extract_metadata(soup)

        return ExtractedContent(
            title=title or url,
            content=text,
            url=url,
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

    def _clean_text(self, text: str) -> str:
        """Clean extracted text."""
        # Remove excessive whitespace
        text = re.sub(r"\n{3,}", "\n\n", text)
        text = re.sub(r" {2,}", " ", text)
        # Remove very short lines (likely navigation remnants)
        lines = [line for line in text.split("\n") if len(line.strip()) > 3]
        return "\n".join(lines).strip()
