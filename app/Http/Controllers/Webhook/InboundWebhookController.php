<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhook;

use App\Domains\Integration\Models\InboundWebhookLog;
use App\Domains\Integration\Models\WebhookEndpoint;
use App\Domains\Integration\Services\WebhookDispatcher;
use App\Domains\Integration\Services\WebhookSignatureVerifier;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InboundWebhookController extends Controller
{
    public function __construct(
        private readonly WebhookSignatureVerifier $verifier,
        private readonly WebhookDispatcher $dispatcher,
    ) {}

    public function receive(Request $request, string $source): JsonResponse
    {
        $endpoint = WebhookEndpoint::where('source', $source)
            ->where('is_active', true)
            ->first();

        if ($endpoint === null) {
            return response()->json(['error' => 'Unknown source'], 404);
        }

        $signatureValid = $this->verifier->verify($request, $endpoint);

        $payload = $request->json()->all();

        $eventType = $this->resolveEventType($source, $payload, $request);

        // De-duplicate via idempotency key
        $idempotencyKey = $this->resolveIdempotencyKey($source, $payload, $request);

        if ($idempotencyKey) {
            $existing = InboundWebhookLog::where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return response()->json(['status' => 'duplicate']);
            }
        }

        // Filter by allowed events
        $allowedEvents = $endpoint->allowed_events;
        if (!empty($allowedEvents) && $eventType && !in_array($eventType, $allowedEvents, true)) {
            $log = InboundWebhookLog::create([
                'source'           => $source,
                'event_type'       => $eventType,
                'status'           => 'ignored',
                'payload'          => $payload,
                'signature_valid'  => $signatureValid,
                'idempotency_key'  => $idempotencyKey,
                'ip_address'       => $request->ip(),
            ]);

            return response()->json(['status' => 'ignored']);
        }

        $log = InboundWebhookLog::create([
            'source'           => $source,
            'event_type'       => $eventType,
            'status'           => 'received',
            'payload'          => $payload,
            'signature_valid'  => $signatureValid,
            'idempotency_key'  => $idempotencyKey,
            'ip_address'       => $request->ip(),
        ]);

        if (!$signatureValid) {
            $log->update([
                'status'        => 'failed',
                'error_message' => 'Invalid signature',
            ]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $this->dispatcher->dispatch($log);

        return response()->json(['status' => 'ok']);
    }

    /** @param array<string, mixed> $payload */
    private function resolveEventType(string $source, array $payload, Request $request): ?string
    {
        return match ($source) {
            'stripe'  => $payload['type'] ?? null,
            'github'  => $request->header('X-GitHub-Event'),
            default   => $payload['event'] ?? $payload['type'] ?? null,
        };
    }

    /** @param array<string, mixed> $payload */
    private function resolveIdempotencyKey(string $source, array $payload, Request $request): ?string
    {
        return match ($source) {
            'stripe' => $payload['id'] ?? null,
            'github' => $request->header('X-GitHub-Delivery'),
            default  => $payload['transId'] ?? null,
        };
    }
}
