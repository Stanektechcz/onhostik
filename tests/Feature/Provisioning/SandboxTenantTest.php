<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Loyalty\LoyaltyService;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Scheduling\NodeScheduler;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Sandbox tenants (audit §5j-9): the flag routes placements to lab instances only (and keeps everyone else off them),
 * grants a promo credit to test with, and earns no loyalty points.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('places sandbox tenants on lab instances only, credits them for testing and skips loyalty', function () {
    $prod = pveLab();
    $_ENV['PROXMOX_LAB_TOKEN_ID'] = 'onhost@pve!lab';
    $_ENV['PROXMOX_LAB_TOKEN_SECRET'] = 'deadbeef-lab';
    $lab = ProviderInstance::query()->create([
        'key' => 'proxmox-lab', 'provider' => 'proxmox', 'name' => 'PVE lab', 'region_code' => 'cz1', 'base_url' => 'https://pve-lab.mgmt.test:8006', 'secret_ref' => 'env://PROXMOX_LAB', 'state' => 'active',
        'capabilities' => ['vm.create' => true, 'compute' => true], 'options' => ['default_node' => 'lab-n1', 'storage' => 'local-zfs', 'templates' => ['debian-13' => 9001], 'template_node' => 'lab-n1', 'sandbox' => true], 'adapter_version' => '1.0.0',
    ]);
    Node::query()->create(['provider_instance_id' => $lab->id, 'name' => 'lab-n1', 'region_code' => 'cz1', 'role' => 'compute', 'state' => 'active', 'capacity' => ['cpu_cores' => 16, 'ram_mb' => 65536, 'disk_gb' => 1000], 'usage' => ['cpu_pct' => 5, 'ram_used_mb' => 1024, 'disk_used_gb' => 10]]);

    $scheduler = app(NodeScheduler::class);
    expect($scheduler->pick(['role' => 'compute', 'ram_mb' => 2048])['node']->name)->toBe('prg1-n2')->and($scheduler->pick(['role' => 'compute', 'ram_mb' => 2048, 'sandbox' => false])['node']->name)->toBe('prg1-n2');
    expect($scheduler->pick(['role' => 'compute', 'ram_mb' => 2048, 'sandbox' => true])['node']->name)->toBe('lab-n1');
    Node::query()->where('name', 'lab-n1')->update(['state' => 'maintenance']);
    expect(fn () => $scheduler->pick(['role' => 'compute', 'ram_mb' => 2048, 'sandbox' => true]))->toThrow(DomainError::class, 'No schedulable node matches the constraints.'); // never falls back to production
    Node::query()->where('name', 'lab-n1')->update(['state' => 'active']);

    // staff switch the flag; the organization gets the sandbox credit once and is placed on the lab
    [$owner, $org] = $this->customerWithOrganization(['email' => 'dev@integrator.cz'], ['name' => 'Integrátor s.r.o.']);
    expect(NodeScheduler::sandboxFor($org->id))->toBeFalse()->and(NodeScheduler::sandboxFor(null))->toBeFalse();
    $this->actingAs($this->staff('platform_owner'), 'sanctum');
    $this->withHeader('Idempotency-Key', 'sb-1')->postJson("/v1/staff/customers/{$org->id}/sandbox", ['enabled' => true, 'reason' => 'API integrace'])->assertOk()->assertJsonPath('sandbox', true)->assertJsonPath('credit', 500000);
    $this->flushHeaders();
    expect(NodeScheduler::sandboxFor($org->id))->toBeTrue()->and(app(WalletService::class)->balances($org, 'CZK')['promo']->minor)->toBe(500000);
    expect($this->getJson("/v1/staff/customers/{$org->id}")->assertOk()->json('data.feature_flags.sandbox'))->toBeTrue();
    $this->withHeader('Idempotency-Key', 'sb-2')->postJson("/v1/staff/customers/{$org->id}/sandbox", ['enabled' => true])->assertOk();
    $this->flushHeaders();
    expect(app(WalletService::class)->balances($org, 'CZK')['promo']->minor)->toBe(500000); // the credit is booked once

    // no loyalty for sandbox tenants; switching the flag off restores it
    app(OutboxPublisher::class)->publish(GenericEvent::of('payment.succeeded', 'payment', 'pay-sb-1', [], $org->id));
    app(OutboxPublisher::class)->relayPending();
    expect(app(LoyaltyService::class)->points($org->id))->toBe(0);
    expect(Notification::query()->where('organization_id', $org->id)->where('event', 'tenant.sandbox')->exists())->toBeTrue();
    $this->withHeader('Idempotency-Key', 'sb-3')->postJson("/v1/staff/customers/{$org->id}/sandbox", ['enabled' => false])->assertOk()->assertJsonPath('sandbox', false);
    $this->flushHeaders();
    app(OutboxPublisher::class)->publish(GenericEvent::of('payment.succeeded', 'payment', 'pay-sb-2', [], $org->id));
    app(OutboxPublisher::class)->relayPending();
    expect(app(LoyaltyService::class)->points($org->id))->toBe(10);
    $this->actingAs($owner, 'sanctum')->withHeader('Idempotency-Key', 'sb-4')->postJson("/v1/staff/customers/{$org->id}/sandbox", ['enabled' => true])->assertForbidden();
});
