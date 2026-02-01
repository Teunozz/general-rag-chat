from app.database import SessionLocal
from app.models.recap import RecapStatus, RecapType
from app.models.settings import AppSettings
from app.services.recap import get_recap_service
from app.tasks import celery_app
from app.tasks.email import send_recap_notifications


def get_recap_settings(db) -> AppSettings | None:
    """Get recap settings from database."""
    return db.query(AppSettings).first()


@celery_app.task
def generate_daily_recap():
    """Generate daily recap if enabled in settings."""
    db = SessionLocal()
    try:
        # Check if daily recaps are enabled
        settings = get_recap_settings(db)
        if settings and (not settings.recap_enabled or not settings.recap_daily_enabled):
            return {"skipped": True, "reason": "Daily recaps disabled in settings"}

        recap_service = get_recap_service()
        recap = recap_service.generate_recap(db, RecapType.DAILY)

        # Trigger email notifications if recap was generated successfully
        if recap and recap.status == RecapStatus.READY:
            send_recap_notifications.delay(recap.id)

        return {
            "recap_id": recap.id,
            "title": recap.title,
            "status": recap.status.value,
        }
    except Exception as e:
        return {"error": str(e)}
    finally:
        db.close()


@celery_app.task
def generate_weekly_recap():
    """Generate weekly recap if enabled in settings."""
    db = SessionLocal()
    try:
        # Check if weekly recaps are enabled
        settings = get_recap_settings(db)
        if settings and (not settings.recap_enabled or not settings.recap_weekly_enabled):
            return {"skipped": True, "reason": "Weekly recaps disabled in settings"}

        recap_service = get_recap_service()
        recap = recap_service.generate_recap(db, RecapType.WEEKLY)

        # Trigger email notifications if recap was generated successfully
        if recap and recap.status == RecapStatus.READY:
            send_recap_notifications.delay(recap.id)

        return {
            "recap_id": recap.id,
            "title": recap.title,
            "status": recap.status.value,
        }
    except Exception as e:
        return {"error": str(e)}
    finally:
        db.close()


@celery_app.task
def generate_monthly_recap():
    """Generate monthly recap if enabled in settings."""
    db = SessionLocal()
    try:
        # Check if monthly recaps are enabled
        settings = get_recap_settings(db)
        if settings and (not settings.recap_enabled or not settings.recap_monthly_enabled):
            return {"skipped": True, "reason": "Monthly recaps disabled in settings"}

        recap_service = get_recap_service()
        recap = recap_service.generate_recap(db, RecapType.MONTHLY)

        # Trigger email notifications if recap was generated successfully
        if recap and recap.status == RecapStatus.READY:
            send_recap_notifications.delay(recap.id)

        return {
            "recap_id": recap.id,
            "title": recap.title,
            "status": recap.status.value,
        }
    except Exception as e:
        return {"error": str(e)}
    finally:
        db.close()


@celery_app.task
def generate_recap_task(recap_type: str):
    """Generate a specific type of recap (called manually, bypasses enabled check)."""
    db = SessionLocal()
    try:
        recap_service = get_recap_service()
        recap = recap_service.generate_recap(db, RecapType(recap_type))

        # Trigger email notifications if recap was generated successfully
        if recap and recap.status == RecapStatus.READY:
            send_recap_notifications.delay(recap.id)

        return {
            "recap_id": recap.id,
            "title": recap.title,
            "status": recap.status.value,
        }
    except Exception as e:
        return {"error": str(e)}
    finally:
        db.close()
