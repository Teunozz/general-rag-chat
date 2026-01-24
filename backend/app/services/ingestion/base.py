import hashlib
import re
from abc import ABC, abstractmethod
from dataclasses import dataclass
from datetime import datetime

import tiktoken

from app.config import get_settings

settings = get_settings()


@dataclass
class ExtractedContent:
    """Extracted content from a source."""

    title: str
    content: str
    url: str | None = None
    published_at: datetime | None = None
    metadata: dict | None = None


@dataclass
class ProcessedDocument:
    """Processed document ready for indexing."""

    title: str
    content: str
    chunks: list[str]
    content_hash: str
    url: str | None = None
    published_at: datetime | None = None
    metadata: dict | None = None


class ChunkingService:
    """Service for chunking text content."""

    def __init__(
        self,
        chunk_size: int | None = None,
        chunk_overlap: int | None = None,
    ):
        self.chunk_size = chunk_size or settings.chunk_size
        self.chunk_overlap = chunk_overlap or settings.chunk_overlap
        try:
            self.tokenizer = tiktoken.get_encoding("cl100k_base")
        except Exception:
            self.tokenizer = None

    def count_tokens(self, text: str) -> int:
        """Count tokens in text."""
        if self.tokenizer:
            return len(self.tokenizer.encode(text))
        # Fallback: approximate tokens as words * 1.3
        return int(len(text.split()) * 1.3)

    def chunk_text(self, text: str) -> list[str]:
        """Split text into overlapping chunks."""
        if not text or not text.strip():
            return []

        # Clean up text
        text = self._clean_text(text)

        # Split into sentences first
        sentences = self._split_into_sentences(text)

        chunks = []
        current_chunk = []
        current_tokens = 0

        for sentence in sentences:
            sentence_tokens = self.count_tokens(sentence)

            # If single sentence is too long, split it further
            if sentence_tokens > self.chunk_size:
                if current_chunk:
                    chunks.append(" ".join(current_chunk))
                    current_chunk = []
                    current_tokens = 0

                # Split long sentence into smaller parts
                sub_chunks = self._split_long_sentence(sentence)
                chunks.extend(sub_chunks)
                continue

            # Check if adding sentence exceeds chunk size
            if current_tokens + sentence_tokens > self.chunk_size:
                if current_chunk:
                    chunks.append(" ".join(current_chunk))

                # Keep overlap
                overlap_text = " ".join(current_chunk)
                overlap_tokens = self.count_tokens(overlap_text)

                # Find sentences to keep for overlap
                overlap_sentences = []
                overlap_count = 0
                for s in reversed(current_chunk):
                    s_tokens = self.count_tokens(s)
                    if overlap_count + s_tokens <= self.chunk_overlap:
                        overlap_sentences.insert(0, s)
                        overlap_count += s_tokens
                    else:
                        break

                current_chunk = overlap_sentences + [sentence]
                current_tokens = self.count_tokens(" ".join(current_chunk))
            else:
                current_chunk.append(sentence)
                current_tokens += sentence_tokens

        # Add remaining chunk
        if current_chunk:
            chunks.append(" ".join(current_chunk))

        return chunks

    def _clean_text(self, text: str) -> str:
        """Clean text content."""
        # Normalize whitespace
        text = re.sub(r"\s+", " ", text)
        # Remove control characters
        text = "".join(char for char in text if ord(char) >= 32 or char in "\n\t")
        return text.strip()

    def _split_into_sentences(self, text: str) -> list[str]:
        """Split text into sentences."""
        # Simple sentence splitting
        sentences = re.split(r"(?<=[.!?])\s+", text)
        return [s.strip() for s in sentences if s.strip()]

    def _split_long_sentence(self, sentence: str) -> list[str]:
        """Split a long sentence into smaller chunks."""
        words = sentence.split()
        chunks = []
        current_words = []
        current_tokens = 0

        for word in words:
            word_tokens = self.count_tokens(word)
            if current_tokens + word_tokens > self.chunk_size:
                if current_words:
                    chunks.append(" ".join(current_words))
                current_words = [word]
                current_tokens = word_tokens
            else:
                current_words.append(word)
                current_tokens += word_tokens

        if current_words:
            chunks.append(" ".join(current_words))

        return chunks


class BaseIngestionService(ABC):
    """Abstract base class for ingestion services."""

    def __init__(self):
        self.chunking_service = ChunkingService()

    @abstractmethod
    def extract_content(self, source_config: dict) -> list[ExtractedContent]:
        """Extract content from source. Must be implemented by subclasses."""
        pass

    def process_content(self, content: ExtractedContent) -> ProcessedDocument:
        """Process extracted content into a document."""
        # Generate content hash
        content_hash = hashlib.sha256(content.content.encode()).hexdigest()

        # Chunk content
        chunks = self.chunking_service.chunk_text(content.content)

        return ProcessedDocument(
            title=content.title,
            content=content.content,
            chunks=chunks,
            content_hash=content_hash,
            url=content.url,
            published_at=content.published_at,
            metadata=content.metadata,
        )

    def ingest(self, source_config: dict) -> list[ProcessedDocument]:
        """Full ingestion pipeline."""
        extracted = self.extract_content(source_config)
        return [self.process_content(content) for content in extracted]
