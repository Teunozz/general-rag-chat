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
        last_error = None
        delay = retry_delay

        for attempt in range(retries):
            try:
                with self._get_smtp_connection() as server:
                    server.send_message(msg)
                logger.info(f"Email sent successfully to {to}: {subject}")
                return True
            except Exception as e:
                last_error = e
                logger.warning(
                    f"Email send attempt {attempt + 1}/{retries} failed: {e}"
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
            return False, f"SMTP authentication failed: {e}"
        except smtplib.SMTPConnectError as e:
            return False, f"Failed to connect to SMTP server: {e}"
        except smtplib.SMTPException as e:
            return False, f"SMTP error: {e}"
        except Exception as e:
            return False, f"Connection error: {e}"


def get_email_service() -> EmailService:
    """Get email service instance."""
    return EmailService()
