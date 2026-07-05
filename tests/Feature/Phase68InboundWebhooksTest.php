<?php

declare(strict_types=1);

use App\Domains\Integration\Models\InboundWebhookLog;
use App\Domains\Integration\Models\WebhookEndpoint;
use App\Domains\Integration\Services\WebhookDispatcher;
use App\Domains\Integration\Services\WebhookSignatureVerifier;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Http\Request;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Model helpers ─────────────────────────────────────────────────────────────

it('InboundWebhookLog statusLabel returns Czech strings', function (): void {
    expect((new InboundWebhookLog(['status' => 'received']))->statusLabel())->toBe('Přijato')
        ->and((new InboundWebhookLog(['status' => 'processed']))->statusLabel())->toBe('Zpracováno')
        ->and((new InboundWebhookLog(['status' => 'ignored']))->statusLabel())->toBe('Ignorováno')
        ->and((new InboundWebhookLog(['status' => 'failed']))->statusLabel())->toBe('Chyba');
});

it('InboundWebhookLog statusBadgeClass returns correct classes', function (): void {
    expect((new InboundWebhookLog(['status' => 'received']))->statusBadgeClass())->toBe('bg-secondary')
        ->and((new InboundWebhookLog(['status' => 'processed']))->statusBadgeClass())->toBe('bg-success')
        ->and((new InboundWebhookLog(['status' => 'ignored']))->statusBadgeClass())->toBe('bg-warning')
        ->and((new InboundWebhookLog(['status' => 'failed']))->statusBadgeClass())->toBe('bg-danger');
});

// ── Signature verifier ────────────────────────────────────────────────────────

it('WebhookSignatureVerifier returns true when no secret configured', function (): void {
    $endpoint = new WebhookEndpoint([
        'secret'           => '',
        'signature_algo'   => 'sha256',
        'signature_header' => 'X-Signature',
    ]);

    $request = Request::create('/webhook/test', 'POST', [], [], [], [], '{"event":"test"}');

    expect(app(WebhookSignatureVerifier::class)->verify($request, $endpoint))->toBeTrue();
});

it('WebhookSignatureVerifier validates correct HMAC signature', function (): void {
    $secret  = 'test-secret-key';
    $body    = '{"event":"payment.success"}';
    $sig     = hash_hmac('sha256', $body, $secret);

    $endpoint = new WebhookEndpoint([
        'secret'           => $secret,
        'signature_algo'   => 'sha256',
        'signature_header' => 'X-Signature',
    ]);

    $request = Request::create('/webhook/test', 'POST', [], [], [], ['HTTP_X_SIGNATURE' => $sig], $body);

    expect(app(WebhookSignatureVerifier::class)->verify($request, $endpoint))->toBeTrue();
});

it('WebhookSignatureVerifier rejects wrong signature', function (): void {
    $endpoint = new WebhookEndpoint([
        'secret'           => 'correct-secret',
        'signature_algo'   => 'sha256',
        'signature_header' => 'X-Signature',
    ]);

    $request = Request::create('/webhook/test', 'POST', [], [], [],
        ['HTTP_X_SIGNATURE' => 'wrong-signature'],
        '{"event":"test"}'
    );

    expect(app(WebhookSignatureVerifier::class)->verify($request, $endpoint))->toBeFalse();
});

it('WebhookSignatureVerifier handles sha256= prefix', function (): void {
    $secret  = 'my-secret';
    $body    = '{"data":1}';
    $sig     = 'sha256=' . hash_hmac('sha256', $body, $secret);

    $endpoint = new WebhookEndpoint([
        'secret'           => $secret,
        'signature_algo'   => 'sha256',
        'signature_header' => 'X-Signature',
    ]);

    $request = Request::create('/webhook/test', 'POST', [], [], [], ['HTTP_X_SIGNATURE' => $sig], $body);

    expect(app(WebhookSignatureVerifier::class)->verify($request, $endpoint))->toBeTrue();
});

// ── Dispatcher ────────────────────────────────────────────────────────────────

it('WebhookDispatcher marks unknown source as ignored', function (): void {
    $log = InboundWebhookLog::create([
        'source'          => 'unknown_source',
        'event_type'      => 'some.event',
        'status'          => 'received',
        'payload'         => ['foo' => 'bar'],
        'signature_valid' => true,
    ]);

    app(WebhookDispatcher::class)->dispatch($log);

    expect($log->fresh()->status)->toBe('ignored');
});

it('WebhookDispatcher marks stripe payment_intent.succeeded as processed', function (): void {
    $log = InboundWebhookLog::create([
        'source'          => 'stripe',
        'event_type'      => 'payment_intent.succeeded',
        'status'          => 'received',
        'payload'         => ['type' => 'payment_intent.succeeded'],
        'signature_valid' => true,
    ]);

    app(WebhookDispatcher::class)->dispatch($log);

    expect($log->fresh()->status)->toBe('processed');
});

it('WebhookDispatcher marks comgate as processed', function (): void {
    $log = InboundWebhookLog::create([
        'source'          => 'comgate',
        'event_type'      => null,
        'status'          => 'received',
        'payload'         => ['transId' => 'ABC123'],
        'signature_valid' => true,
    ]);

    app(WebhookDispatcher::class)->dispatch($log);

    expect($log->fresh()->status)->toBe('processed');
});

// ── HTTP receive endpoint ─────────────────────────────────────────────────────

it('receive returns 404 for unknown source', function (): void {
    $this->postJson('/api/webhook/nonexistent_source_xyz', ['test' => 1])
        ->assertJson(['error' => 'Unknown source']);
});

it('receive stores log and returns ok for known endpoint without secret', function (): void {
    WebhookEndpoint::create([
        'name'             => 'Test',
        'source'           => 'test_source',
        'signature_algo'   => 'sha256',
        'signature_header' => 'X-Signature',
        'is_active'        => true,
    ]);

    $this->postJson('/api/webhook/test_source', ['event' => 'test.event', 'data' => 'value'])
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    expect(InboundWebhookLog::where('source', 'test_source')->exists())->toBeTrue();
});

it('receive returns 401 for invalid signature', function (): void {
    WebhookEndpoint::create([
        'name'             => 'Secured',
        'source'           => 'secured_source',
        'secret'           => 'my-hmac-secret',
        'signature_algo'   => 'sha256',
        'signature_header' => 'X-Signature',
        'is_active'        => true,
    ]);

    $this->postJson('/api/webhook/secured_source', ['event' => 'test'], ['X-Signature' => 'wrong'])
        ->assertStatus(401);
});

it('receive deduplicates requests with same idempotency key', function (): void {
    WebhookEndpoint::create([
        'name'             => 'Stripe',
        'source'           => 'stripe',
        'signature_algo'   => 'sha256',
        'signature_header' => 'X-Signature',
        'is_active'        => true,
    ]);

    $payload = ['id' => 'evt_unique_999', 'type' => 'payment_intent.succeeded'];

    $this->postJson('/api/webhook/stripe', $payload)->assertOk();
    $this->postJson('/api/webhook/stripe', $payload)->assertJson(['status' => 'duplicate']);

    expect(InboundWebhookLog::where('source', 'stripe')->count())->toBe(1);
});

it('receive ignores events not in allowed_events list', function (): void {
    WebhookEndpoint::create([
        'name'             => 'Filtered',
        'source'           => 'filtered_source',
        'signature_algo'   => 'sha256',
        'signature_header' => 'X-Signature',
        'is_active'        => true,
        'allowed_events'   => ['payment.success'],
    ]);

    $this->postJson('/api/webhook/filtered_source', ['type' => 'some.other.event'])
        ->assertJson(['status' => 'ignored']);
});

// ── Admin routes ──────────────────────────────────────────────────────────────

it('admin can view inbound webhook log index', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.webhooks.inbound.index'))
        ->assertOk()
        ->assertSee('Příchozí webhooky');
});

it('non-admin cannot view inbound webhook logs', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.webhooks.inbound.index'))
        ->assertStatus(403);
});

it('admin can create webhook endpoint', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.webhooks.endpoint.store'), [
            'name'             => 'My Endpoint',
            'source'           => 'my_service',
            'signature_algo'   => 'sha256',
            'signature_header' => 'X-Hmac',
            'is_active'        => '1',
        ])
        ->assertRedirect(route('admin.webhooks.inbound.index'));

    expect(WebhookEndpoint::where('source', 'my_service')->exists())->toBeTrue();
});

it('admin endpoint store validates unique source', function (): void {
    $admin = adminUser();
    WebhookEndpoint::create([
        'name'             => 'Existing',
        'source'           => 'existing_source',
        'signature_algo'   => 'sha256',
        'signature_header' => 'X-Sig',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.webhooks.endpoint.store'), [
            'name'             => 'Duplicate',
            'source'           => 'existing_source',
            'signature_algo'   => 'sha256',
            'signature_header' => 'X-Sig',
        ])
        ->assertSessionHasErrors('source');
});

it('admin can toggle endpoint active state', function (): void {
    $admin = adminUser();
    $ep    = WebhookEndpoint::create([
        'name'             => 'Toggle me',
        'source'           => 'toggle_src',
        'signature_algo'   => 'sha256',
        'signature_header' => 'X-Sig',
        'is_active'        => true,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.webhooks.endpoint.toggle', $ep))
        ->assertRedirect();

    expect($ep->fresh()->is_active)->toBeFalse();
});

it('admin can delete endpoint', function (): void {
    $admin = adminUser();
    $ep    = WebhookEndpoint::create([
        'name'             => 'Delete me',
        'source'           => 'delete_src',
        'signature_algo'   => 'sha256',
        'signature_header' => 'X-Sig',
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.webhooks.endpoint.destroy', $ep))
        ->assertRedirect();

    expect(WebhookEndpoint::find($ep->id))->toBeNull();
});
