import uuid
from dataclasses import dataclass
from functools import lru_cache

from qdrant_client import QdrantClient
from qdrant_client.http import models as qdrant_models
from qdrant_client.http.exceptions import UnexpectedResponse

from app.config import get_settings
from app.services.embeddings import get_embedding_service

settings = get_settings()


@dataclass
class SearchResult:
    """Search result from vector store."""

    chunk_id: str
    document_id: int
    source_id: int
    content: str
    score: float
    metadata: dict


class VectorStoreService:
    """Qdrant vector store service."""

    def __init__(self):
        self.client = QdrantClient(host=settings.qdrant_host, port=settings.qdrant_port)
        self.collection_name = settings.qdrant_collection_name
        self.embedding_service = get_embedding_service()
        self._ensure_collection()

    def _ensure_collection(self):
        """Ensure the collection exists."""
        try:
            self.client.get_collection(self.collection_name)
        except (UnexpectedResponse, Exception):
            # Collection doesn't exist, create it
            self.client.create_collection(
                collection_name=self.collection_name,
                vectors_config=qdrant_models.VectorParams(
                    size=self.embedding_service.dimension,
                    distance=qdrant_models.Distance.COSINE,
                ),
            )

    def add_chunks(
        self,
        chunks: list[str],
        document_id: int,
        source_id: int,
        metadata: list[dict] | None = None,
    ) -> list[str]:
        """Add document chunks to the vector store."""
        if not chunks:
            return []

        # Generate embeddings
        embeddings = self.embedding_service.embed_texts(chunks)

        # Generate IDs
        chunk_ids = [str(uuid.uuid4()) for _ in chunks]

        # Prepare points
        points = []
        for i, (chunk_id, chunk, embedding) in enumerate(zip(chunk_ids, chunks, embeddings)):
            chunk_metadata = metadata[i] if metadata else {}
            payload = {
                "document_id": document_id,
                "source_id": source_id,
                "content": chunk,
                "chunk_index": i,
                **chunk_metadata,
            }
            points.append(
                qdrant_models.PointStruct(id=chunk_id, vector=embedding, payload=payload)
            )

        # Upsert to Qdrant
        self.client.upsert(collection_name=self.collection_name, points=points)

        return chunk_ids

    def search(
        self,
        query: str,
        limit: int = 5,
        source_ids: list[int] | None = None,
        score_threshold: float = 0.5,
    ) -> list[SearchResult]:
        """Search for similar chunks."""
        # Generate query embedding
        query_embedding = self.embedding_service.embed_text(query)

        # Build filter
        filter_conditions = []
        if source_ids:
            filter_conditions.append(
                qdrant_models.FieldCondition(
                    key="source_id",
                    match=qdrant_models.MatchAny(any=source_ids),
                )
            )

        query_filter = (
            qdrant_models.Filter(must=filter_conditions) if filter_conditions else None
        )

        # Search using query_points (new API in qdrant-client >= 1.7)
        results = self.client.query_points(
            collection_name=self.collection_name,
            query=query_embedding,
            limit=limit,
            query_filter=query_filter,
            score_threshold=score_threshold,
        )

        # Convert to SearchResult - results.points contains the scored points
        return [
            SearchResult(
                chunk_id=str(point.id),
                document_id=point.payload.get("document_id"),
                source_id=point.payload.get("source_id"),
                content=point.payload.get("content", ""),
                score=point.score,
                metadata={
                    k: v
                    for k, v in point.payload.items()
                    if k not in ["document_id", "source_id", "content"]
                },
            )
            for point in results.points
        ]

    def delete_by_document(self, document_id: int):
        """Delete all chunks for a document."""
        self.client.delete(
            collection_name=self.collection_name,
            points_selector=qdrant_models.FilterSelector(
                filter=qdrant_models.Filter(
                    must=[
                        qdrant_models.FieldCondition(
                            key="document_id",
                            match=qdrant_models.MatchValue(value=document_id),
                        )
                    ]
                )
            ),
        )

    def delete_by_source(self, source_id: int):
        """Delete all chunks for a source."""
        self.client.delete(
            collection_name=self.collection_name,
            points_selector=qdrant_models.FilterSelector(
                filter=qdrant_models.Filter(
                    must=[
                        qdrant_models.FieldCondition(
                            key="source_id",
                            match=qdrant_models.MatchValue(value=source_id),
                        )
                    ]
                )
            ),
        )

    def get_collection_info(self) -> dict:
        """Get collection statistics."""
        try:
            info = self.client.get_collection(self.collection_name)
            return {
                "vectors_count": info.vectors_count,
                "points_count": info.points_count,
                "status": info.status.value,
            }
        except Exception as e:
            return {"error": str(e)}


@lru_cache
def get_vector_store() -> VectorStoreService:
    return VectorStoreService()
