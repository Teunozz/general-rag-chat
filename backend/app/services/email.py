"""Email service for sending SMTP emails."""

import logging
import smtplib
import time
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText

from app.config import get_settings

logger = logging.getLogger(__name__)


class EmailService:
    """Service for sending emails via SMTP."""

    def __init__(self):
        self.settings = get_settings()

    def _get_smtp_connection(self) -> smtplib.SMTP | smtplib.SMTP_SSL:
        """Create and return an SMTP connection."""
        if self.settings.smtp_use_ssl:
            server = smtplib.SMTP_SSL(
                self.settings.smtp_host,
                self.settings.smtp_port,
                timeout=30,
            )
        else:
            server = smtplib.SMTP(
                self.settings.smtp_host,
                self.settings.smtp_port,
                timeout=30,
            )
            if self.settings.smtp_use_tls:
                server.starttls()

        # Login if credentials provided
        if self.settings.smtp_user and self.settings.smtp_password:
            server.login(self.settings.smtp_user, self.settings.smtp_password)

        return server

    def send_email(
        self,
        to: str,
        subject: str,
        html: str,
        text: str | None = None,
        retries: int = 3,
        retry_delay: float = 1.0,
    ) -> bool:
        """
        Send an email with retry logic and exponential backoff.

        Args:
            to: Recipient email address
            subject: Email subject
            html: HTML body content
            text: Plain text body content (optional, will be derived from html if not provided)
            retries: Number of retry attempts
            retry_delay: Initial delay between retries (doubles each attempt)

        Returns:
            True if email was sent successfully, False otherwise
        """
        if not self.settings.smtp_host:
            logger.warning("SMTP host not configured, skipping email send")
            return False

        # Create message
        msg = MIMEMultipart("alternative")
        msg["Subject"] = subject
        msg["From"] = (
            f"{self.settings.smtp_from_name} <{self.settings.smtp_from_email}>"
            if self.settings.smtp_from_name
            else self.settings.smtp_from_email
        )
        msg["To"] = to

        # Add plain text part
        if text:
            msg.attach(MIMEText(text, "plain"))

        # Add HTML part
        msg.attach(MIMEText(html, "html"))

        # Attempt to send with retries
        # Note: time.sleep is acceptable here as this runs in sync Celery workers
        last_error = None
        delay = retry_delay

        for attempt in range(retries):
            try:
                with self._get_smtp_connection() as server:
                    server.send_message(msg)
                logger.info("Email sent successfully to %s: %s", to, subject)
                return True
            except Exception as e:
                last_error = e
                logger.warning(
                    "Email send attempt %d/%d failed: %s", attempt + 1, retries, e
                )
                if attempt < retries - 1:
                    time.sleep(delay)
                    delay *= 2  # Exponential backoff

        logger.error(f"Failed to send email to {to} after {retries} attempts: {last_error}")
        return False

    def test_connection(self) -> tuple[bool, str]:
        """
        Test SMTP connection.

        Returns:
            Tuple of (success: bool, message: str)
        """
        if not self.settings.smtp_host:
            return False, "SMTP host not configured"

        try:
            with self._get_smtp_connection() as server:
                # NOOP command to verify connection
                server.noop()
            return True, "SMTP connection successful"
        except smtplib.SMTPAuthenticationError as e:
            logger.error("SMTP authentication failed: %s", e)
            return False, "SMTP authentication failed. Check your credentials."
        except smtplib.SMTPConnectError as e:
            logger.error("Failed to connect to SMTP server: %s", e)
            return False, "Failed to connect to SMTP server. Check host and port."
        except smtplib.SMTPException as e:
            logger.error("SMTP error: %s", e)
            return False, "SMTP error occurred. Check server logs for details."
        except Exception as e:
            logger.error("SMTP connection error: %s", e)
            return False, "Connection error. Check server logs for details."


def get_email_service() -> EmailService:
    """Get email service instance."""
    return EmailService()
