<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\BulkCustomerEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BulkCustomerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly BulkCustomerEmail $campaign,
        public readonly string $recipientName = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->campaign->subject);
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->campaign->body_html);
    }
}
