from abc import ABC, abstractmethod

from app.config import get_settings, EmbeddingProvider
from app.database import SessionLocal
from app.models.settings import AppSettings

env_settings = get_settings()


def get_embedding_settings() -> tuple[str, str]:
    """Get embedding provider and model from database settings.

    Returns:
        Tuple of (provider, model)
    """
    db = SessionLocal()
    try:
        settings = db.query(AppSettings).first()
        if settings:
            return settings.embedding_provider, settings.embedding_model
        # Defaults if no settings in database yet
        return "openai", "text-embedding-3-small"
    finally:
        db.close()


class BaseEmbeddingService(ABC):
    """Abstract base class for embedding services."""

    @abstractmethod
    def embed_text(self, text: str) -> list[float]:
        """Generate embedding for a single text."""
        pass

    @abstractmethod
    def embed_texts(self, texts: list[str]) -> list[list[float]]:
        """Generate embeddings for multiple texts."""
        pass

    @property
    @abstractmethod
    def dimension(self) -> int:
        """Return the dimension of embeddings."""
        pass


class OpenAIEmbeddingService(BaseEmbeddingService):
    """OpenAI embedding service."""

    # Dimension lookup for common OpenAI embedding models
    MODEL_DIMENSIONS = {
        "text-embedding-3-small": 1536,
        "text-embedding-3-large": 3072,
        "text-embedding-ada-002": 1536,
    }

    def __init__(self, model: str = "text-embedding-3-small"):
        from openai import OpenAI

        self.client = OpenAI(api_key=env_settings.openai_api_key)
        self.model = model
        self._dimension = self.MODEL_DIMENSIONS.get(model, 1536)

    def embed_text(self, text: str) -> list[float]:
        response = self.client.embeddings.create(input=text, model=self.model)
        return response.data[0].embedding

    def embed_texts(self, texts: list[str]) -> list[list[float]]:
        if not texts:
            return []
        # OpenAI API supports batch embedding
        response = self.client.embeddings.create(input=texts, model=self.model)
        return [item.embedding for item in response.data]

    @property
    def dimension(self) -> int:
        return self._dimension


class SentenceTransformerEmbeddingService(BaseEmbeddingService):
    """Sentence Transformers embedding service (local)."""

    def __init__(self, model: str = "all-MiniLM-L6-v2"):
        from sentence_transformers import SentenceTransformer

        self.model = SentenceTransformer(model)
        self._dimension = self.model.get_sentence_embedding_dimension()

    def embed_text(self, text: str) -> list[float]:
        embedding = self.model.encode(text, convert_to_numpy=True)
        return embedding.tolist()

    def embed_texts(self, texts: list[str]) -> list[list[float]]:
        if not texts:
            return []
        embeddings = self.model.encode(texts, convert_to_numpy=True)
        return embeddings.tolist()

    @property
    def dimension(self) -> int:
        return self._dimension


class EmbeddingService:
    """Facade for embedding services that reads configuration from database."""

    def __init__(self, provider: str | EmbeddingProvider | None = None, model: str | None = None):
        # If not specified, read from database
        if provider is None or model is None:
            db_provider, db_model = get_embedding_settings()
            provider = provider or db_provider
            model = model or db_model

        # Normalize provider to string
        if isinstance(provider, EmbeddingProvider):
            provider = provider.value

        if provider == "openai":
            self._service = OpenAIEmbeddingService(model=model)
        elif provider == "sentence_transformers":
            self._service = SentenceTransformerEmbeddingService(model=model)
        else:
            raise ValueError(f"Unknown embedding provider: {provider}")

    def embed_text(self, text: str) -> list[float]:
        return self._service.embed_text(text)

    def embed_texts(self, texts: list[str]) -> list[list[float]]:
        return self._service.embed_texts(texts)

    @property
    def dimension(self) -> int:
        return self._service.dimension


# Cache the embedding service instance
# Note: If embedding settings change, the application needs to be restarted
# or the cache cleared, since existing vectors are incompatible with new models
_embedding_service_instance: EmbeddingService | None = None


def get_embedding_service() -> EmbeddingService:
    """Get an embedding service instance.

    Note: This is cached because changing embedding models mid-operation
    would create incompatible vectors. If you change embedding settings,
    you must re-index all content.
    """
    global _embedding_service_instance
    if _embedding_service_instance is None:
        _embedding_service_instance = EmbeddingService()
    return _embedding_service_instance


def reset_embedding_service():
    """Reset the cached embedding service.

    Call this after changing embedding settings to pick up the new configuration.
    Warning: Existing vectors will be incompatible with the new model.
    """
    global _embedding_service_instance
    _embedding_service_instance = None
