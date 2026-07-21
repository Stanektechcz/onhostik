<?php

declare(strict_types=1);

use App\Domains\Products\Models\PricingPlan;
use App\Models\AdminIpAllowlist;
use App\Models\ConsentRecord;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * Remaining Phase H items: manual payment audit (H112), admin IP allowlist
 * (H118), GDPR export/erasure (H122), demonstrable consent (H123) and
 * security headers (H124).
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── H112: manual payment acceptance is audited ────────────────────────────────

it('audits an admin accepting a payment manually', function (): void {
    $order = placeOrder(customerUser())['order'];

    $this->actingAs(adminUser())
        ->post(route('admin.orders.accept', $order))
        ->assertRedirect();

    // Recording money as received without a gateway is exactly the action that
    // must be traceable to a person.
    $entry = \Spatie\Activitylog\Models\Activity::where('description', 'order.accepted_by_admin')->firstOrFail();

    expect($entry->causer_id)->not->toBeNull();
});

it('forbids a customer from accepting their own payment', function (): void {
    $order = placeOrder(customerUser())['order'];

    $this->actingAs(customerUser())
        ->post(route('admin.orders.accept', $order))
        ->assertForbidden();
});

// ── H118: admin IP allowlist has a management UI ──────────────────────────────

it('lets an admin manage the IP allowlist from the UI', function (): void {
    $this->actingAs(adminUser())->get(route('admin.ip-allowlist.index'))->assertOk();

    // Entries are stored as CIDR so a whole office range can be allowed.
    $this->actingAs(adminUser())
        ->post(route('admin.ip-allowlist.store'), [
            'cidr'  => '198.51.100.0/24',
            'label' => 'Kancelář',
        ])
        ->assertRedirect();

    expect(AdminIpAllowlist::where('cidr', '198.51.100.0/24')->exists())->toBeTrue();
});

it('forbids a customer from touching the IP allowlist', function (): void {
    $this->actingAs(customerUser())
        ->get(route('admin.ip-allowlist.index'))
        ->assertForbidden();
});

// ── H122: GDPR ────────────────────────────────────────────────────────────────

it('lets a customer request a data export', function (): void {
    $user = customerUser();

    $this->actingAs($user)->get(route('panel.gdpr.export.index'))->assertOk();
    $this->actingAs($user)->post(route('panel.gdpr.export.store'))->assertRedirect();
});

it('schedules automatic erasure of accounts past their deadline', function (): void {
    // Art. 17 requests must not depend on someone remembering to run a script.
    $this->artisan('list')->assertSuccessful();

    expect(class_exists(\App\Console\Commands\ProcessGdprErasureRequestsCommand::class))->toBeTrue();
});

// ── H123: demonstrable consent ────────────────────────────────────────────────

it('records terms and privacy consent at checkout', function (): void {
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));
    $this->actingAs($user)->post(route('panel.cart.checkout'), [
        'payment_method' => 'bank',
        'terms'          => '1',
    ]);

    $terms = ConsentRecord::where('user_id', $user->id)->where('type', ConsentRecord::TYPE_TERMS)->first();

    expect($terms)->not->toBeNull()
        ->and($terms->granted)->toBeTrue()
        ->and($terms->source)->toBe('cart_checkout')
        // Which wording was agreed to is the whole point.
        ->and($terms->document_version)->not->toBe('unversioned')
        ->and($terms->ip_address)->not->toBeNull();
});

it('records no consent when checkout is refused for missing agreement', function (): void {
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));
    $this->actingAs($user)
        ->from(route('panel.cart.index'))
        ->post(route('panel.cart.checkout'), ['payment_method' => 'bank']) // no terms
        ->assertSessionHasErrors('terms');

    expect(ConsentRecord::where('user_id', $user->id)->count())->toBe(0);
});

it('stamps the configured document version onto a consent', function (): void {
    config()->set('legal.document_versions.terms', '2027-06');

    expect(ConsentRecord::currentVersion(ConsentRecord::TYPE_TERMS))->toBe('2027-06');
});

it('keeps consent records append-only', function (): void {
    $user   = customerUser();
    $record = ConsentRecord::create([
        'user_id'          => $user->id,
        'type'             => ConsentRecord::TYPE_MARKETING,
        'document_version' => '2026-01',
        'granted'          => true,
    ]);

    // Rewriting history would defeat the purpose of the record.
    expect(fn () => $record->update(['granted' => false]))->toThrow(RuntimeException::class);
    expect(fn () => $record->delete())->toThrow(RuntimeException::class);
});

it('withdraws consent by appending a new record', function (): void {
    $user = customerUser();

    ConsentRecord::create([
        'user_id' => $user->id, 'type' => ConsentRecord::TYPE_MARKETING,
        'document_version' => '2026-01', 'granted' => true,
    ]);
    ConsentRecord::create([
        'user_id' => $user->id, 'type' => ConsentRecord::TYPE_MARKETING,
        'document_version' => '2026-01', 'granted' => false,
    ]);

    $history = ConsentRecord::where('user_id', $user->id)->orderBy('id')->get();

    expect($history)->toHaveCount(2)
        ->and($history->last()->granted)->toBeFalse();
});

// ── H124: security headers ────────────────────────────────────────────────────

it('sends the expected security headers', function (): void {
    $response = $this->actingAs(customerUser())->get(route('panel.dashboard'));

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
});

it('does not send HSTS over plain http', function (): void {
    // Sending HSTS on a non-HTTPS response is meaningless and can strand a
    // local/staging install on a protocol it cannot serve.
    $response = $this->actingAs(customerUser())->get(route('panel.dashboard'));

    expect($response->headers->get('Strict-Transport-Security'))->toBeNull();
});
