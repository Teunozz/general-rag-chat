<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #4F46E5; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f9fafb; padding: 20px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px; }
        .meta { color: #6b7280; font-size: 14px; margin-bottom: 16px; }
        .summary { font-size: 15px; }
        .summary h2 { font-size: 18px; font-weight: 600; margin: 16px 0 8px 0; color: #1f2937; }
        .summary p { margin: 0 0 12px 0; }
        .footer { margin-top: 20px; font-size: 12px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0; font-size: 20px;">{{ ucfirst($recap->type) }} Recap</h1>
    </div>
    <div class="content">
        <div class="meta">
            {{ $recap->period_start->format('M j, Y') }} &mdash; {{ $recap->period_end->format('M j, Y') }}
            &middot; {{ $recap->document_count }} new documents
        </div>
        <div class="summary">
            {!! \Illuminate\Support\Str::markdown($recap->summary) !!}
        </div>
    </div>
    <div class="footer">
        You received this because you opted in to {{ $recap->type }} recap emails.
    </div>
</body>
</html>
