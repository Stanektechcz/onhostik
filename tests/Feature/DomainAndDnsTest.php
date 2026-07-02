<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\DomainRegistration;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

function makeDomainForUser(\App\Models\User $user, array $overrides = []): DomainRegistration
{
    $productId = \App\Domains\Products\Models\Product::value('id');

    $service = Service::create([
        'customer_id'         => $user->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Active,
        'label'               => 'dns-test-service',
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    return DomainRegistration::create(array_merge([
        'service_id' => $service->id,
        'domain'     => 'dnstest',
        'tld'        => 'cz',
        'registrar'  => 'wedos',
        'auto_renew' => true,
        'expires_at' => now()->addYear()->toDateString(),
    ], $overrides));
}

// ── DNS show ──────────────────────────────────────────────────────────────────

it('customer can view DNS records for their domain in mock mode', function (): void {
    $user   = customerUser();
    $domain = makeDomainForUser($user);

    $this->actingAs($user)
        ->get(route('panel.domains.dns', $domain))
        ->assertOk()
        ->assertSee('DNS');
});

it('customer cannot view DNS records for another customer\'s domain', function (): void {
    $owner    = customerUser();
    $intruder = customerUser();
    $domain   = makeDomainForUser($owner);

    $this->actingAs($intruder)
        ->get(route('panel.domains.dns', $domain))
        ->assertForbidden();
});

// ── DNS store ─────────────────────────────────────────────────────────────────

it('customer can add a DNS record in mock mode', function (): void {
    $user   = customerUser();
    $domain = makeDomainForUser($user);

    $this->actingAs($user)
        ->post(route('panel.domains.dns.store', $domain), [
            'type'  => 'A',
            'name'  => 'test',
            'rdata' => '1.2.3.4',
            'ttl'   => 3600,
        ])
        ->assertRedirect()
        ->assertSessionHas('status');
});

it('DNS store rejects invalid record type', function (): void {
    $user   = customerUser();
    $domain = makeDomainForUser($user);

    $this->actingAs($user)
        ->post(route('panel.domains.dns.store', $domain), [
            'type'  => 'INVALID',
            'name'  => 'test',
            'rdata' => '1.2.3.4',
        ])
        ->assertSessionHasErrors('type');
});

it('customer cannot add DNS records for another customer\'s domain', function (): void {
    $owner    = customerUser();
    $intruder = customerUser();
    $domain   = makeDomainForUser($owner);

    $this->actingAs($intruder)
        ->post(route('panel.domains.dns.store', $domain), [
            'type'  => 'A',
            'name'  => 'hack',
            'rdata' => '10.0.0.1',
        ])
        ->assertForbidden();
});

// ── Domain auto-renew toggle ──────────────────────────────────────────────────

it('customer can toggle domain auto-renew on', function (): void {
    $user   = customerUser();
    $domain = makeDomainForUser($user, ['auto_renew' => false]);

    $this->actingAs($user)
        ->post(route('panel.domains.auto-renew', $domain))
        ->assertRedirect();

    expect($domain->fresh()->auto_renew)->toBeTrue();
});

it('customer can toggle domain auto-renew off', function (): void {
    $user   = customerUser();
    $domain = makeDomainForUser($user, ['auto_renew' => true]);

    $this->actingAs($user)
        ->post(route('panel.domains.auto-renew', $domain))
        ->assertRedirect();

    expect($domain->fresh()->auto_renew)->toBeFalse();
});

it('customer cannot toggle auto-renew on another customer\'s domain', function (): void {
    $owner    = customerUser();
    $intruder = customerUser();
    $domain   = makeDomainForUser($owner);

    $this->actingAs($intruder)
        ->post(route('panel.domains.auto-renew', $domain))
        ->assertForbidden();
});
