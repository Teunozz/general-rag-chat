import os
from pathlib import Path

from app.services.ingestion.base import BaseIngestionService, ExtractedContent


class DocumentIngestionService(BaseIngestionService):
    """Service for ingesting document files (PDF, DOCX, TXT, MD, etc.)."""

    SUPPORTED_EXTENSIONS = {
        ".pdf",
        ".docx",
        ".doc",
        ".txt",
        ".md",
        ".markdown",
        ".html",
        ".htm",
        ".rtf",
        ".odt",
        ".epub",
    }

    def __init__(self):
        super().__init__()

    def extract_content(self, source_config: dict) -> list[ExtractedContent]:
        """Extract content from document file(s)."""
        file_path = source_config.get("file_path")

        if not file_path:
            raise ValueError("file_path is required for document ingestion")

        path = Path(file_path)

        if not path.exists():
            raise FileNotFoundError(f"File not found: {file_path}")

        if path.is_file():
            return [self._extract_file(path)]
        elif path.is_dir():
            results = []
            for file in path.rglob("*"):
                if file.is_file() and file.suffix.lower() in self.SUPPORTED_EXTENSIONS:
                    try:
                        results.append(self._extract_file(file))
                    except Exception as e:
                        print(f"Error processing {file}: {e}")
            return results
        else:
            raise ValueError(f"Invalid path: {file_path}")

    def _extract_file(self, file_path: Path) -> ExtractedContent:
        """Extract content from a single file."""
        extension = file_path.suffix.lower()

        if extension in {".txt", ".md", ".markdown"}:
            return self._extract_text_file(file_path)
        elif extension == ".pdf":
            return self._extract_pdf(file_path)
        elif extension in {".docx", ".doc"}:
            return self._extract_docx(file_path)
        elif extension in {".html", ".htm"}:
            return self._extract_html(file_path)
        else:
            # Try using unstructured for other formats
            return self._extract_with_unstructured(file_path)

    def _extract_text_file(self, file_path: Path) -> ExtractedContent:
        """Extract content from plain text file."""
        with open(file_path, "r", encoding="utf-8", errors="ignore") as f:
            content = f.read()

        title = file_path.stem
        return ExtractedContent(
            title=title,
            content=content,
            metadata={"file_name": file_path.name, "file_type": "text"},
        )

    def _extract_pdf(self, file_path: Path) -> ExtractedContent:
        """Extract content from PDF file using unstructured."""
        try:
            from unstructured.partition.pdf import partition_pdf

            elements = partition_pdf(str(file_path))
            content = "\n\n".join([str(el) for el in elements])

            # Try to get title from metadata or first heading
            title = file_path.stem
            for el in elements:
                if hasattr(el, "category") and el.category == "Title":
                    title = str(el)
                    break

            return ExtractedContent(
                title=title,
                content=content,
                metadata={"file_name": file_path.name, "file_type": "pdf"},
            )
        except ImportError:
            # Fallback if unstructured PDF dependencies not installed
            return self._extract_pdf_simple(file_path)

    def _extract_pdf_simple(self, file_path: Path) -> ExtractedContent:
        """Simple PDF extraction fallback."""
        try:
            import subprocess

            # Use pdftotext if available
            result = subprocess.run(
                ["pdftotext", "-layout", str(file_path), "-"],
                capture_output=True,
                text=True,
                timeout=60,
            )
            if result.returncode == 0:
                content = result.stdout
            else:
                content = f"[Could not extract PDF content: {result.stderr}]"
        except Exception as e:
            content = f"[PDF extraction failed: {e}]"

        return ExtractedContent(
            title=file_path.stem,
            content=content,
            metadata={"file_name": file_path.name, "file_type": "pdf"},
        )

    def _extract_docx(self, file_path: Path) -> ExtractedContent:
        """Extract content from DOCX file."""
        try:
            from unstructured.partition.docx import partition_docx

            elements = partition_docx(str(file_path))
            content = "\n\n".join([str(el) for el in elements])

            title = file_path.stem
            for el in elements:
                if hasattr(el, "category") and el.category == "Title":
                    title = str(el)
                    break

            return ExtractedContent(
                title=title,
                content=content,
                metadata={"file_name": file_path.name, "file_type": "docx"},
            )
        except ImportError:
            # Fallback
            return ExtractedContent(
                title=file_path.stem,
                content="[DOCX parsing requires unstructured library with docx support]",
                metadata={"file_name": file_path.name, "file_type": "docx"},
            )

    def _extract_html(self, file_path: Path) -> ExtractedContent:
        """Extract content from HTML file."""
        from bs4 import BeautifulSoup

        with open(file_path, "r", encoding="utf-8", errors="ignore") as f:
            html_content = f.read()

        soup = BeautifulSoup(html_content, "lxml")

        # Get title
        title = file_path.stem
        if soup.title:
            title = soup.title.get_text(strip=True)

        # Remove scripts and styles
        for element in soup.find_all(["script", "style"]):
            element.decompose()

        # Get text content
        content = soup.get_text(separator="\n", strip=True)

        return ExtractedContent(
            title=title,
            content=content,
            metadata={"file_name": file_path.name, "file_type": "html"},
        )

    def _extract_with_unstructured(self, file_path: Path) -> ExtractedContent:
        """Extract content using unstructured library."""
        try:
            from unstructured.partition.auto import partition

            elements = partition(str(file_path))
            content = "\n\n".join([str(el) for el in elements])

            return ExtractedContent(
                title=file_path.stem,
                content=content,
                metadata={
                    "file_name": file_path.name,
                    "file_type": file_path.suffix.lstrip("."),
                },
            )
        except Exception as e:
            return ExtractedContent(
                title=file_path.stem,
                content=f"[Could not extract content: {e}]",
                metadata={
                    "file_name": file_path.name,
                    "file_type": file_path.suffix.lstrip("."),
                },
            )
