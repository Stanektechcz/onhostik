<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\BulkCustomerMail;
use App\Models\BulkCustomerEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendBulkCustomerEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly int    $campaignId,
        public readonly string $recipientEmail,
        public readonly string $recipientName = '',
    ) {}

    public function handle(): void
    {
        $campaign = BulkCustomerEmail::find($this->campaignId);

        if ($campaign === null) {
            return;
        }

        Mail::to($this->recipientEmail)->send(
            new BulkCustomerMail($campaign, $this->recipientName),
        );

        $campaign->increment('sent_count');
    }

    public function failed(\Throwable $e): void
    {
        $campaign = BulkCustomerEmail::find($this->campaignId);
        if ($campaign?->isSending()) {
            $campaign->update(['status' => 'failed']);
        }
    }
}
