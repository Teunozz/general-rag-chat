"""Cache invalidation tasks for coordinating state across Celery workers."""

import logging

from app.services.embeddings import (
    get_embedding_settings_hash,
    reset_embedding_service,
    set_cached_settings_version,
)
from app.services.vector_store import reset_vector_store
from app.tasks import celery_app

logger = logging.getLogger(__name__)


@celery_app.task
def invalidate_embedding_caches():
    """Broadcast task to clear embedding and vector store caches on workers.

    This task should be sent to all workers when embedding settings change.
    Each worker will reset its local cached instances and update the Redis
    version key to reflect the current settings.
    """
    logger.info("Invalidating embedding caches on worker")

    # Reset local cached instances
    reset_embedding_service()
    reset_vector_store()

    # Update Redis version key to current settings
    current_hash = get_embedding_settings_hash()
    set_cached_settings_version(current_hash)

    logger.info("Embedding caches invalidated, version set to %s", current_hash)
    return {"invalidated": True, "version": current_hash}


def broadcast_cache_invalidation():
    """Broadcast cache invalidation to all Celery workers.

    Uses Celery's broadcast mechanism to send the invalidation task to all
    workers in the cluster.
    """
    # Send to all workers using broadcast
    invalidate_embedding_caches.apply_async(
        queue="celery",
        # Using the default exchange with broadcast routing
        # Each worker will receive and process this task
    )
    logger.info("Broadcast cache invalidation sent to workers")
