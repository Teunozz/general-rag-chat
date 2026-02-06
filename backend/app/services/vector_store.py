import uuid
from dataclasses import dataclass
from functools import lru_cache

from qdrant_client import QdrantClient
from qdrant_client.http import models as qdrant_models
from qdrant_client.http.exceptions import UnexpectedResponse

from app.config import get_settings
from app.services.date_filter import DateFilter
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
        """Ensure the collection exists with the correct vector dimension.

        If the collection exists but has a different dimension than the current
        embedding model, it will be deleted and recreated. This handles the case
        where the user changes embedding models and triggers a rechunk.
        """
        expected_dim = self.embedding_service.dimension

        try:
            info = self.client.get_collection(self.collection_name)
            existing_dim = info.config.params.vectors.size

            if existing_dim != expected_dim:
                # Dimension mismatch - delete and recreate
                self.client.delete_collection(self.collection_name)
                self._create_collection(expected_dim)

        except (UnexpectedResponse, Exception):
            # Collection doesn't exist, create it
            self._create_collection(expected_dim)

    def _create_collection(self, dimension: int):
        """Create the Qdrant collection with the specified dimension."""
        self.client.create_collection(
            collection_name=self.collection_name,
            vectors_config=qdrant_models.VectorParams(
                size=dimension,
                distance=qdrant_models.Distance.COSINE,
            ),
        )

    def _build_date_filter_condition(self, date_filter: DateFilter) -> qdrant_models.Filter | None:
        """Build a Qdrant filter condition for date range filtering.

        Args:
            date_filter: The date filter to apply

        Returns:
            A Qdrant Filter for date constraints, or None if no constraints
        """
        start_ts, end_ts = date_filter.to_timestamps()

        if start_ts is None and end_ts is None:
            return None

        # Build range condition for published_at_ts
        range_conditions = {}
        if start_ts is not None:
            range_conditions["gte"] = start_ts
        if end_ts is not None:
            # Add 1 day to end_ts to make it inclusive (end of day)
            range_conditions["lte"] = end_ts + 86400  # 86400 seconds = 1 day

        date_range_condition = qdrant_models.FieldCondition(
            key="published_at_ts",
            range=qdrant_models.Range(**range_conditions),
        )

        if date_filter.include_undated:
            # Use should clause: include if in range OR if published_at_ts is null
            # Qdrant uses IsNull to match null values
            return qdrant_models.Filter(
                should=[
                    date_range_condition,
                    qdrant_models.IsNullCondition(
                        is_null=qdrant_models.PayloadField(key="published_at_ts")
                    ),
                ]
            )
        else:
            # Only include documents with dates in range
            return date_range_condition

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
            points.append(qdrant_models.PointStruct(id=chunk_id, vector=embedding, payload=payload))

        # Upsert to Qdrant
        self.client.upsert(collection_name=self.collection_name, points=points)

        return chunk_ids

    def search(
        self,
        query: str,
        limit: int = 5,
        source_ids: list[int] | None = None,
        score_threshold: float = 0.5,
        date_filter: DateFilter | None = None,
    ) -> list[SearchResult]:
        """Search for similar chunks.

        Args:
            query: Search query string
            limit: Maximum number of results
            source_ids: Optional filter by source IDs
            score_threshold: Minimum score threshold
            date_filter: Optional date range filter
        """
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

        # Apply date filter if active
        if date_filter and date_filter.is_active():
            date_condition = self._build_date_filter_condition(date_filter)
            if date_condition:
                filter_conditions.append(date_condition)

        query_filter = qdrant_models.Filter(must=filter_conditions) if filter_conditions else None

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

    def get_adjacent_chunks(
        self,
        document_id: int,
        chunk_indices: list[int],
        window: int = 1,
    ) -> list[SearchResult]:
        """Fetch adjacent chunks from the same document.

        Args:
            document_id: The document to fetch chunks from
            chunk_indices: List of chunk indices to expand around
            window: Number of adjacent chunks to include on each side

        Returns:
            List of SearchResult objects for adjacent chunks, ordered by chunk_index
        """
        if not chunk_indices or window <= 0:
            return []

        # Calculate all indices we need to fetch
        indices_to_fetch = set()
        for idx in chunk_indices:
            for offset in range(-window, window + 1):
                if idx + offset >= 0:  # Avoid negative indices
                    indices_to_fetch.add(idx + offset)

        # Remove the original indices (we already have those)
        indices_to_fetch -= set(chunk_indices)

        if not indices_to_fetch:
            return []

        # Query Qdrant for the adjacent chunks using scroll
        filter_condition = qdrant_models.Filter(
            must=[
                qdrant_models.FieldCondition(
                    key="document_id",
                    match=qdrant_models.MatchValue(value=document_id),
                ),
                qdrant_models.FieldCondition(
                    key="chunk_index",
                    match=qdrant_models.MatchAny(any=list(indices_to_fetch)),
                ),
            ]
        )

        # Use scroll to fetch all matching points
        results, _ = self.client.scroll(
            collection_name=self.collection_name,
            scroll_filter=filter_condition,
            limit=len(indices_to_fetch),
            with_payload=True,
            with_vectors=False,
        )

        # Convert to SearchResult objects (score is 0 for adjacent chunks)
        adjacent_results = [
            SearchResult(
                chunk_id=str(point.id),
                document_id=point.payload.get("document_id"),
                source_id=point.payload.get("source_id"),
                content=point.payload.get("content", ""),
                score=0.0,  # Adjacent chunks don't have a search score
                metadata={
                    k: v
                    for k, v in point.payload.items()
                    if k not in ["document_id", "source_id", "content"]
                },
            )
            for point in results
        ]

        # Sort by chunk_index
        return sorted(adjacent_results, key=lambda r: r.metadata.get("chunk_index", 0))

    def search_with_context(
        self,
        query: str,
        limit: int = 10,
        source_ids: list[int] | None = None,
        score_threshold: float = 0.5,
        context_window: int = 1,
        date_filter: DateFilter | None = None,
    ) -> list[SearchResult]:
        """Search and expand results with adjacent chunks.

        Args:
            query: Search query
            limit: Maximum number of initial results
            source_ids: Optional filter by source IDs
            score_threshold: Minimum score threshold
            context_window: Number of adjacent chunks to include on each side
            date_filter: Optional date range filter

        Returns:
            List of SearchResult objects including original matches and adjacent chunks,
            deduplicated and sorted by (document_id, chunk_index)
        """
        # 1. Perform normal search
        results = self.search(
            query=query,
            limit=limit,
            source_ids=source_ids,
            score_threshold=score_threshold,
            date_filter=date_filter,
        )

        if not results or context_window <= 0:
            return results

        # 2. Collect (document_id, chunk_index) pairs and track best score per document
        original_chunks = {}  # key: (doc_id, chunk_idx), value: SearchResult
        doc_to_indices = {}  # key: doc_id, value: list of chunk_indices
        doc_best_score = {}  # key: doc_id, value: best score for that document

        for result in results:
            doc_id = result.document_id
            chunk_idx = result.metadata.get("chunk_index", 0)
            key = (doc_id, chunk_idx)
            original_chunks[key] = result

            if doc_id not in doc_to_indices:
                doc_to_indices[doc_id] = []
            doc_to_indices[doc_id].append(chunk_idx)

            # Track best score per document for ordering
            if doc_id not in doc_best_score or result.score > doc_best_score[doc_id]:
                doc_best_score[doc_id] = result.score

        # 3. Fetch adjacent chunks for each document
        all_adjacent = []
        for doc_id, chunk_indices in doc_to_indices.items():
            adjacent = self.get_adjacent_chunks(doc_id, chunk_indices, context_window)
            all_adjacent.extend(adjacent)

        # 4. Merge results, keeping original scores where applicable
        all_chunks = dict(original_chunks)  # Start with original results
        for adj in all_adjacent:
            key = (adj.document_id, adj.metadata.get("chunk_index", 0))
            if key not in all_chunks:
                all_chunks[key] = adj

        # 5. Sort by best document score (descending), then chunk_index within document
        # This ensures most relevant documents come first for token budget
        sorted_results = sorted(
            all_chunks.values(),
            key=lambda r: (
                -doc_best_score.get(r.document_id, 0),  # Highest score first
                r.metadata.get("chunk_index", 0),  # Then by chunk order
            ),
        )

        return sorted_results

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


def reset_vector_store():
    """Reset the cached vector store service.

    Call this after changing embedding settings to ensure the vector store
    picks up the new embedding model and recreates the collection if needed.
    """
    get_vector_store.cache_clear()
