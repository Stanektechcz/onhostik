<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\OutgoingWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WebhookController extends Controller
{
    /** Allowed events customers may subscribe to. */
    private const ALLOWED_EVENTS = [
        'service.created',
        'service.status_changed',
        'service.suspended',
        'service.terminated',
        'invoice.created',
        'invoice.paid',
        'invoice.overdue',
        'monitor.down',
        'monitor.up',
        'ticket.created',
        'ticket.replied',
        'ticket.closed',
        '*',
    ];

    public function index(Request $request): JsonResponse
    {
        $customer = $request->user()?->customer;

        if ($customer === null) {
            return response()->json(['error' => 'No customer account.'], 403);
        }

        $webhooks = OutgoingWebhook::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (OutgoingWebhook $w) => $this->toArray($w));

        return response()->json(['data' => $webhooks]);
    }

    public function store(Request $request): JsonResponse
    {
        $customer = $request->user()?->customer;

        if ($customer === null) {
            return response()->json(['error' => 'No customer account.'], 403);
        }

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'url'      => ['required', 'url', 'max:500'],
            'events'   => ['required', 'array', 'min:1'],
            'events.*' => ['required', 'string', Rule::in(self::ALLOWED_EVENTS)],
            'secret'   => ['nullable', 'string', 'max:255'],
        ]);

        $webhook = OutgoingWebhook::create([
            'customer_id' => $customer->id,
            'name'        => $validated['name'],
            'url'         => $validated['url'],
            'events'      => $validated['events'],
            'secret'      => $validated['secret'] ?? '',
            'is_active'   => true,
        ]);

        return response()->json(['data' => $this->toArray($webhook)], 201);
    }

    public function destroy(Request $request, OutgoingWebhook $webhook): JsonResponse
    {
        $customer = $request->user()?->customer;

        if ($customer === null || $webhook->customer_id !== $customer->id) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        $webhook->delete();

        return response()->json(null, 204);
    }

    public function deliveries(Request $request, OutgoingWebhook $webhook): JsonResponse
    {
        $customer = $request->user()?->customer;

        if ($customer === null || $webhook->customer_id !== $customer->id) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        $deliveries = $webhook->deliveries()
            ->orderByDesc('created_at')
            ->paginate(25);

        return response()->json([
            'data' => $deliveries->map(fn ($d) => [
                'id'            => $d->id,
                'event'         => $d->event,
                'status'        => $d->status,
                'response_code' => $d->response_code,
                'delivered_at'  => $d->delivered_at?->toIso8601String(),
                'created_at'    => $d->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $deliveries->currentPage(),
                'last_page'    => $deliveries->lastPage(),
                'total'        => $deliveries->total(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function toArray(OutgoingWebhook $webhook): array
    {
        return [
            'id'         => $webhook->id,
            'name'       => $webhook->name,
            'url'        => $webhook->url,
            'events'     => $webhook->events,
            'is_active'  => $webhook->is_active,
            'created_at' => $webhook->created_at?->toIso8601String(),
        ];
    }
}
