<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\NewsletterCampaign;
use App\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly NewsletterCampaign $campaign,
        public readonly Subscriber $subscriber,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->campaign->subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter',
            with: [
                'campaign'        => $this->campaign,
                'subscriber'      => $this->subscriber,
                'unsubscribeUrl'  => route('unsubscribe', ['token' => $this->subscriber->unsubscribe_token]),
            ],
        );
    }
}
