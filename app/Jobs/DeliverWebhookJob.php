<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\OutgoingWebhook;
use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

final class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 15;

    /** @param array<string, mixed> $payload */
    public function __construct(
        public readonly int $webhookId,
        public readonly string $event,
        public readonly array $payload,
    ) {}

    public function handle(): void
    {
        $webhook = OutgoingWebhook::find($this->webhookId);

        if ($webhook === null || !$webhook->is_active) {
            return;
        }

        $body      = (string) json_encode(['event' => $this->event, 'data' => $this->payload, 'timestamp' => now()->toIso8601String()]);
        $signature = 'sha256=' . hash_hmac('sha256', $body, $webhook->secret);

        $delivery = WebhookDelivery::create([
            'outgoing_webhook_id' => $this->webhookId,
            'event'               => $this->event,
            'payload'             => $this->payload,
            'status'              => 'pending',
        ]);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type'      => 'application/json',
                    'X-OnHost-Event'    => $this->event,
                    'X-OnHost-Signature' => $signature,
                ])
                ->post($webhook->url, json_decode($body, true));

            $delivery->update([
                'status'        => $response->successful() ? 'delivered' : 'failed',
                'response_code' => $response->status(),
                'response_body' => mb_substr($response->body(), 0, 2000),
                'delivered_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            $delivery->update([
                'status'       => 'failed',
                'response_body' => mb_substr($e->getMessage(), 0, 2000),
            ]);
        }
    }
}
