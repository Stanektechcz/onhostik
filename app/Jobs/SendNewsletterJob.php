<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\NewsletterMail;
use App\Models\NewsletterCampaign;
use App\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendNewsletterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly int $campaignId,
        public readonly int $subscriberId,
    ) {}

    public function handle(): void
    {
        $campaign   = NewsletterCampaign::find($this->campaignId);
        $subscriber = Subscriber::find($this->subscriberId);

        if ($campaign === null || $subscriber === null) {
            return;
        }

        if (! $subscriber->is_active) {
            return;
        }

        Mail::to($subscriber->email)->send(new NewsletterMail($campaign, $subscriber));

        $campaign->increment('sent_count');
    }

    public function failed(\Throwable $e): void
    {
        $campaign = NewsletterCampaign::find($this->campaignId);
        if ($campaign !== null && $campaign->isSending()) {
            $campaign->update(['status' => 'failed']);
        }
    }
}
