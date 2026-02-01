"""Celery tasks for sending email notifications."""

import logging

from app.config import get_settings
from app.database import SessionLocal
from app.models.recap import Recap, RecapStatus, RecapType
from app.models.settings import AppSettings
from app.models.user import User
from app.services.email import get_email_service
from app.services.email_templates import render_recap_email
from app.services.encryption import decrypt_field
from app.tasks import celery_app

logger = logging.getLogger(__name__)


def _get_base_url() -> str:
    """Get the base URL for the application."""
    # In production, this should come from settings/environment
    # For now, we'll use a reasonable default
    settings = get_settings()
    return getattr(settings, "app_base_url", "http://localhost:3000")


@celery_app.task(
    bind=True,
    max_retries=3,
    default_retry_delay=60,
    autoretry_for=(Exception,),
)
def send_email_task(self, to: str, subject: str, html: str, text: str | None = None):
    """
    Send a single email with retries.

    Args:
        to: Recipient email address
        subject: Email subject
        html: HTML content
        text: Plain text content (optional)
    """
    email_service = get_email_service()
    success = email_service.send_email(to, subject, html, text, retries=1)

    if not success:
        raise Exception(f"Failed to send email to {to}")

    return {"success": True, "to": to, "subject": subject}


@celery_app.task
def send_recap_notifications(recap_id: int):
    """
    Send recap notification emails to all opted-in users.

    Args:
        recap_id: ID of the recap to send notifications for
    """
    db = SessionLocal()
    try:
        # Check if email notifications are enabled globally
        settings = db.query(AppSettings).first()
        if not settings:
            logger.info("No settings found, skipping recap notifications")
            return {"skipped": True, "reason": "No settings configured"}

        if not settings.email_notifications_enabled:
            logger.info("Email notifications disabled globally")
            return {"skipped": True, "reason": "Email notifications disabled"}

        if not settings.email_recap_notifications_enabled:
            logger.info("Recap email notifications disabled")
            return {"skipped": True, "reason": "Recap notifications disabled"}

        # Get the recap
        recap = db.query(Recap).filter(Recap.id == recap_id).first()
        if not recap:
            logger.error(f"Recap {recap_id} not found")
            return {"error": f"Recap {recap_id} not found"}

        if recap.status != RecapStatus.READY:
            logger.info(f"Recap {recap_id} not ready (status: {recap.status})")
            return {"skipped": True, "reason": f"Recap status is {recap.status.value}"}

        # Determine which users want this type of recap
        recap_pref_field = {
            RecapType.DAILY: "email_daily_recap",
            RecapType.WEEKLY: "email_weekly_recap",
            RecapType.MONTHLY: "email_monthly_recap",
        }.get(recap.recap_type)

        if not recap_pref_field:
            logger.error(f"Unknown recap type: {recap.recap_type}")
            return {"error": f"Unknown recap type: {recap.recap_type}"}

        # Get users who have enabled notifications for this recap type
        query_filter = (
            User.is_active.is_(True)
            & User.email_notifications_enabled.is_(True)
            & (getattr(User, recap_pref_field).is_(True))
        )
        users = db.query(User).filter(query_filter).all()

        if not users:
            logger.info("No users opted in for recap notifications")
            return {"skipped": True, "reason": "No opted-in users"}

        # Render email content
        base_url = _get_base_url()
        html, text = render_recap_email(recap, base_url)
        subject = f"{recap.title or 'Your Recap'} - {settings.app_name}"

        # Queue emails for each user
        queued = 0
        errors = []
        for user in users:
            try:
                # Decrypt email address
                email = decrypt_field(user.email)
                if not email:
                    logger.warning(f"Could not decrypt email for user {user.id}")
                    continue

                send_email_task.delay(email, subject, html, text)
                queued += 1
                logger.info(f"Queued recap email for user {user.id}")
            except Exception as e:
                logger.error(f"Failed to queue email for user {user.id}: {e}")
                errors.append(str(e))

        return {
            "recap_id": recap_id,
            "queued": queued,
            "total_users": len(users),
            "errors": errors if errors else None,
        }

    except Exception as e:
        logger.error(f"Error sending recap notifications: {e}")
        return {"error": str(e)}
    finally:
        db.close()
