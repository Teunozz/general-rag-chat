"""Email templates for notifications."""

from app.models.recap import Recap, RecapType

# Base HTML template
BASE_TEMPLATE = """
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{title}</title>
    <style>
        body {{
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }}
        .container {{
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }}
        .header {{
            border-bottom: 2px solid #3B82F6;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }}
        .header h1 {{
            color: #3B82F6;
            margin: 0;
            font-size: 24px;
        }}
        .recap-type {{
            display: inline-block;
            background-color: #EBF5FF;
            color: #3B82F6;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 8px;
        }}
        .content {{
            margin-bottom: 25px;
        }}
        .summary {{
            background-color: #f9fafb;
            border-left: 4px solid #3B82F6;
            padding: 15px;
            margin: 20px 0;
        }}
        .summary h2 {{
            margin: 0 0 10px 0;
            font-size: 16px;
            color: #374151;
        }}
        .summary p {{
            margin: 0;
            color: #6b7280;
        }}
        .stats {{
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }}
        .stat {{
            text-align: center;
        }}
        .stat-value {{
            font-size: 24px;
            font-weight: bold;
            color: #3B82F6;
        }}
        .stat-label {{
            font-size: 12px;
            color: #6b7280;
        }}
        .button {{
            display: inline-block;
            background-color: #3B82F6;
            color: #ffffff;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 15px;
        }}
        .button:hover {{
            background-color: #2563EB;
        }}
        .footer {{
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #9ca3af;
            text-align: center;
        }}
        .period {{
            color: #6b7280;
            font-size: 14px;
            margin-top: 5px;
        }}
    </style>
</head>
<body>
    <div class="container">
        {content}
        <div class="footer">
            <p>You received this email because you have recap notifications enabled.</p>
            <p>To unsubscribe, update your notification preferences in settings.</p>
        </div>
    </div>
</body>
</html>
"""


def _get_recap_type_label(recap_type: RecapType) -> str:
    """Get human-readable label for recap type."""
    return {
        RecapType.DAILY: "Daily",
        RecapType.WEEKLY: "Weekly",
        RecapType.MONTHLY: "Monthly",
    }.get(recap_type, recap_type.value.title())


def render_recap_email(recap: Recap, base_url: str) -> tuple[str, str]:
    """
    Render HTML and plain text email content for a recap notification.

    Args:
        recap: The Recap object to render
        base_url: Base URL for the application (for links)

    Returns:
        Tuple of (html_content, text_content)
    """
    recap_type_label = _get_recap_type_label(recap.recap_type)
    recap_url = f"{base_url.rstrip('/')}/recaps/{recap.id}"
    period = f"{recap.period_start.strftime('%b %d')} - {recap.period_end.strftime('%b %d, %Y')}"

    # HTML content
    html_content = f"""
        <div class="header">
            <h1>{recap.title or f'{recap_type_label} Recap'}</h1>
            <span class="recap-type">{recap_type_label} Recap</span>
            <p class="period">{period}</p>
        </div>
        <div class="content">
            <div class="summary">
                <h2>Summary</h2>
                <p>{recap.summary or 'Your recap is ready to view.'}</p>
            </div>
            <div class="stats">
                <div class="stat">
                    <div class="stat-value">{recap.document_count}</div>
                    <div class="stat-label">Documents</div>
                </div>
                <div class="stat">
                    <div class="stat-value">{len(recap.source_ids) if recap.source_ids else 0}</div>
                    <div class="stat-label">Sources</div>
                </div>
            </div>
            <a href="{recap_url}" class="button">View Full Recap</a>
        </div>
    """

    html = BASE_TEMPLATE.format(
        title=f"{recap_type_label} Recap - {recap.title or 'Your Knowledge Base'}",
        content=html_content,
    )

    # Plain text content
    text = f"""{recap_type_label} Recap
{'=' * 40}

{recap.title or f'{recap_type_label} Recap'}
Period: {period}

Summary:
{recap.summary or 'Your recap is ready to view.'}

Documents: {recap.document_count}
Sources: {len(recap.source_ids) if recap.source_ids else 0}

View full recap: {recap_url}

---
You received this email because you have recap notifications enabled.
To unsubscribe, update your notification preferences in settings.
"""

    return html, text


def render_test_email(app_name: str) -> tuple[str, str]:
    """
    Render a test email to verify SMTP configuration.

    Args:
        app_name: Name of the application

    Returns:
        Tuple of (html_content, text_content)
    """
    html_content = f"""
        <div class="header">
            <h1>Test Email</h1>
        </div>
        <div class="content">
            <p>This is a test email from <strong>{app_name}</strong>.</p>
            <p>If you received this email, your SMTP configuration is working correctly.</p>
            <div class="summary">
                <h2>Configuration Verified</h2>
                <p>Email notifications are ready to use.</p>
            </div>
        </div>
    """

    html = BASE_TEMPLATE.format(
        title=f"Test Email - {app_name}",
        content=html_content,
    )

    text = f"""Test Email - {app_name}
{'=' * 40}

This is a test email from {app_name}.

If you received this email, your SMTP configuration is working correctly.

Configuration Verified
Email notifications are ready to use.
"""

    return html, text
