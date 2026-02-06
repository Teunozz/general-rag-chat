import logging

from celery import Celery
from celery.signals import task_prerun

from app.config import get_settings

settings = get_settings()
logger = logging.getLogger(__name__)

celery_app = Celery(
    "rag_tasks",
    broker=settings.redis_url,
    backend=settings.redis_url,
    include=["app.tasks.ingestion", "app.tasks.recap", "app.tasks.email", "app.tasks.cache"],
)

# Celery configuration
celery_app.conf.update(
    task_serializer="json",
    accept_content=["json"],
    result_serializer="json",
    timezone="UTC",
    enable_utc=True,
    task_track_started=True,
    task_time_limit=3600,  # 1 hour max
    worker_prefetch_multiplier=1,
)

# Tasks that use embeddings and need cache validation before running
EMBEDDING_TASKS = {
    "app.tasks.ingestion.rechunk_source",
    "app.tasks.ingestion.rechunk_all_sources",
    "app.tasks.ingestion.rechunk_all_sources_sequential",
    "app.tasks.ingestion.ingest_source",
}


@task_prerun.connect
def validate_embedding_caches(sender=None, **kwargs):
    """Validate embedding caches before running tasks that use embeddings.

    This signal handler checks if the embedding settings have changed since
    the worker started. If so, it invalidates the local caches to ensure
    the task uses the current embedding model.
    """
    task_name = sender.name if sender else None
    if task_name in EMBEDDING_TASKS:
        from app.services.embeddings import invalidate_embedding_caches_if_stale

        if invalidate_embedding_caches_if_stale():
            logger.info("Embedding caches invalidated before task %s", task_name)


# Beat schedule for periodic tasks
celery_app.conf.beat_schedule = {
    "refresh-rss-feeds": {
        "task": "app.tasks.ingestion.refresh_rss_feeds",
        "schedule": 900.0,  # Every 15 minutes
    },
    "generate-daily-recap": {
        "task": "app.tasks.recap.generate_daily_recap",
        "schedule": 86400.0,  # Daily
        "options": {"expires": 3600},
    },
    "generate-weekly-recap": {
        "task": "app.tasks.recap.generate_weekly_recap",
        "schedule": 604800.0,  # Weekly
        "options": {"expires": 3600},
    },
}
