<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Notifications\Models\WebhookDelivery;
use Onhost\Domain\Notifications\Models\WebhookEndpoint;
use Onhost\Domain\Notifications\Webhooks\WebhookCommand;
use Onhost\Domain\Notifications\Webhooks\WebhookEvents;
use Onhost\Domain\Notifications\Webhooks\WebhookSigner;
use Onhost\Domain\Notifications\Webhooks\WebhookView;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/**
 * Customer webhooks (D4): endpoints, their deliveries, a test event and a delivery sent again. Every write is a
 * WebhookCommand on the bus; the keys are per minute without an `Idempotency-Key` (onceKey), so the same action can be
 * asked for again later — a suspended endpoint turned on twice in a day, a delivery sent again tomorrow. A test event is keyed
 * per request (F12a): two pings inside the cooldown are two requests, and the second one hears the cooldown's 429.
 */
final class WebhookController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'organization.manage', CommandScope::organization($organization->id));
        $endpoints = WebhookEndpoint::query()->where('organization_id', $organization->id)->orderBy('created_at')->get();

        return response()->json([
            'data' => $endpoints->map(fn (WebhookEndpoint $e) => WebhookView::endpoint($e))->all(),
            'events' => WebhookEvents::catalog(),
            'families' => WebhookEvents::FAMILIES,
            'signature' => ['headers' => ['X-ONhost-Timestamp', 'X-ONhost-Signature'], 'scheme' => WebhookSigner::VERSION.'=hex(hmac_sha256(secret, timestamp + "." + body))', 'tolerance_seconds' => WebhookSigner::TOLERANCE_SECONDS,
                'during_rotation' => ['header' => 'X-ONhost-Signature-Previous', 'accept' => 'either signature verifying with the secret you hold', 'overlap_minutes' => (int) config('onhost.webhooks.secret_overlap_minutes', 60)]],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['url' => ['required', 'string', 'url', 'starts_with:https://', 'max:500'], 'events' => ['nullable', 'array', 'max:50'], 'events.*' => ['string', 'max:80']]);

        return $this->command($request, $this->onceKey($request, 'webhook.create'), ['op' => 'create', 'url' => $data['url'], 'events' => array_values((array) ($data['events'] ?? []))], 201);
    }

    public function destroy(Request $request, string $endpoint): JsonResponse
    {
        return $this->command($request, $this->onceKey($request, 'webhook.disable'), ['op' => 'disable', 'endpoint_id' => $endpoint]);
    }

    public function enable(Request $request, string $endpoint): JsonResponse
    {
        return $this->command($request, $this->onceKey($request, 'webhook.enable'), ['op' => 'enable', 'endpoint_id' => $endpoint]);
    }

    public function rotateSecret(Request $request, string $endpoint): JsonResponse
    {
        // G7: `overlap: false` ends the replaced secret at once (a leaked secret must not stay valid for the overlap window)
        $data = $request->validate(['overlap' => ['sometimes', 'boolean']]);

        return $this->command($request, $this->onceKey($request, 'webhook.rotate'), ['op' => 'rotate_secret', 'endpoint_id' => $endpoint, 'overlap' => (bool) ($data['overlap'] ?? true)]);
    }

    public function ping(Request $request, string $endpoint): JsonResponse
    {
        return $this->command($request, $this->eachRequestKey($request, 'webhook.ping'), ['op' => 'ping', 'endpoint_id' => $endpoint], 202);
    }

    public function redeliver(Request $request, string $endpoint, string $delivery): JsonResponse
    {
        return $this->command($request, $this->onceKey($request, 'webhook.redeliver'), ['op' => 'redeliver', 'endpoint_id' => $endpoint, 'delivery_id' => $delivery], 202);
    }

    public function deliveries(Request $request, string $endpoint): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'organization.manage', CommandScope::organization($organization->id));
        $model = WebhookEndpoint::query()->where('organization_id', $organization->id)->find($endpoint);
        if ($model === null) {
            throw DomainError::notFound('webhook');
        }

        return $this->api->paginate($request, WebhookDelivery::query()->where('endpoint_id', $model->id)->latest(), fn (WebhookDelivery $d) => WebhookView::delivery($d));
    }

    /**
     * Every caller names its key explicitly (F12a review): `onceKey()` for the writes that must not repeat within a minute without
     * an `Idempotency-Key` (create, rotate, enable, disable, redeliver), `eachRequestKey()` for the ping alone, whose repetition is
     * bounded by its cooldown and must be heard as a 429. A new write chooses one of the two here, never a bare string.
     *
     * @param  array<string, mixed>  $payload
     */
    private function command(Request $request, string $idempotencyKey, array $payload, int $status = 200): JsonResponse
    {
        $organization = $this->api->organization($request);
        $command = new WebhookCommand($organization->id, $idempotencyKey, $payload);
        $this->api->assertTokenScope($request, $command->permission());

        return response()->json(['data' => $this->bus->dispatch($command, $this->api->context($request, $organization))], $status);
    }
}
