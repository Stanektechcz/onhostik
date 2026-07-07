<?php

declare(strict_types=1);

namespace App\Domains\Support\Services;

use App\Domains\Support\Models\HelpdeskWebhook;
use App\Domains\Support\Models\HelpdeskWebhookDelivery;
use App\Domains\Support\Models\SupportTicket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class HelpdeskWebhookService
{
    /** Fire all active webhooks subscribed to this event. */
    public function fire(string $event, SupportTicket $ticket): void
    {
        $payload = $this->buildPayload($event, $ticket);

        HelpdeskWebhook::where('is_active', true)->get()
            ->filter(fn (HelpdeskWebhook $wh): bool => $wh->listensTo($event))
            ->each(function (HelpdeskWebhook $wh) use ($event, $payload): void {
                $this->deliver($wh, $event, $payload);
            });
    }

    /** @param array<string, mixed> $payload */
    private function deliver(HelpdeskWebhook $webhook, string $event, array $payload): void
    {
        $start   = hrtime(true);
        $headers = [
            'Content-Type'      => 'application/json',
            'X-Onhost-Event'    => $event,
            'X-Onhost-Delivery' => \Illuminate\Support\Str::uuid()->toString(),
        ];

        if ($webhook->secret) {
            $body = json_encode($payload) ?: '';
            $headers['X-Onhost-Signature'] = 'sha256=' . hash_hmac('sha256', $body, $webhook->secret);
        }

        $success    = false;
        $statusCode = null;
        $body       = null;

        try {
            $response = Http::withHeaders($headers)
                ->timeout($webhook->timeout_seconds)
                ->post($webhook->url, $payload);

            $statusCode = $response->status();
            $body       = mb_substr($response->body(), 0, 2000);
            $success    = $response->successful();
        } catch (\Throwable $e) {
            $body = mb_substr($e->getMessage(), 0, 2000);
            Log::warning("Helpdesk webhook #{$webhook->id} delivery failed: {$e->getMessage()}");
        }

        $duration = (int) round((hrtime(true) - $start) / 1_000_000);

        HelpdeskWebhookDelivery::create([
            'helpdesk_webhook_id' => $webhook->id,
            'event'               => $event,
            'payload'             => $payload,
            'status_code'         => $statusCode,
            'response_body'       => $body,
            'success'             => $success,
            'duration_ms'         => $duration,
            'fired_at'            => now(),
        ]);

        $webhook->update([
            'last_fired_at' => now(),
            'failure_count' => $success ? 0 : ($webhook->failure_count + 1),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(string $event, SupportTicket $ticket): array
    {
        $ticket->loadMissing('customer');

        return [
            'event'     => $event,
            'fired_at'  => now()->toIso8601String(),
            'ticket'    => [
                'id'          => $ticket->id,
                'uuid'        => $ticket->uuid,
                'subject'     => $ticket->subject,
                'status'      => $ticket->status->value,
                'priority'    => $ticket->priority->value,
                'department'  => $ticket->department,
                'customer_id' => $ticket->customer_id,
                'created_at'  => $ticket->created_at?->toIso8601String(),
                'updated_at'  => $ticket->updated_at?->toIso8601String(),
                'closed_at'   => $ticket->closed_at?->toIso8601String(),
            ],
            'customer'  => $ticket->customer ? [
                'id'           => $ticket->customer->id,
                'company_name' => $ticket->customer->company_name,
            ] : null,
        ];
    }
}
