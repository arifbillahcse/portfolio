<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SmtpTestMessage extends Mailable
{
    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Portfolio SMTP test email',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: '<p>Your portfolio email delivery is working.</p><p>New contact and Hire form leads will be emailed to your notification recipient when lead notifications and the queue worker are enabled.</p>',
        );
    }
}
