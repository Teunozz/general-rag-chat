<?php

namespace App\Mail;

use App\Models\Recap;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RecapMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Recap $recap,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ucfirst($this->recap->type) . ' Recap - ' .
                $this->recap->period_start->format('M j') . ' to ' .
                $this->recap->period_end->format('M j, Y'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.recap',
        );
    }
}
