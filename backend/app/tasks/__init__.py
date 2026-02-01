from celery import Celery

from app.config import get_settings

settings = get_settings()

celery_app = Celery(
    "rag_tasks",
    broker=settings.redis_url,
    backend=settings.redis_url,
    include=["app.tasks.ingestion", "app.tasks.recap", "app.tasks.email"],
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
