<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Models\DomainRegistration;
use App\Domains\Provisioning\Models\Service;

// ── Domain card on the service detail (Phase 279) ────────────────────────────

function makeDomainService(int $customerId): array
{
    $service = Service::factory()->create([
        'customer_id'         => $customerId,
        'provisioning_driver' => ProvisioningDriver::Wedos,
        'label'               => 'moje-domena.cz',
    ]);

    $domain = DomainRegistration::create([
        'service_id'  => $service->id,
        'domain'      => 'moje-domena',
        'tld'         => 'cz',
        'registrar'   => 'wedos',
        'expires_at'  => now()->addYear(),
        'auto_renew'  => true,
        'nameservers' => ['ns1.onhost.cz', 'ns2.onhost.cz'],
    ]);

    return [$service, $domain];
}

it('panel domain service shows the domain management card', function (): void {
    $user = customerUser();
    [$service] = makeDomainService($user->customer->id);

    $this->actingAs($user)
        ->get(route('panel.services.show', $service))
        ->assertOk()
        ->assertSee('Správa domény')
        ->assertSee('moje-domena.cz')
        ->assertSee('ns1.onhost.cz')
        ->assertSee('DNS záznamy');
});

it('panel domain card shows warning for soon-expiring domain', function (): void {
    $user = customerUser();
    [$service, $domain] = makeDomainService($user->customer->id);
    $domain->update(['expires_at' => now()->addDays(10)]);

    $this->actingAs($user)
        ->get(route('panel.services.show', $service))
        ->assertOk()
        ->assertSee('Brzy expiruje');
});

it('panel domain card shows note when no registration linked', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'         => $user->customer->id,
        'provisioning_driver' => ProvisioningDriver::Wedos,
    ]);

    $this->actingAs($user)
        ->get(route('panel.services.show', $service))
        ->assertOk()
        ->assertSee('není připojena žádná registrace domény');
});

it('non-domain service does not show the domain card', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'         => $user->customer->id,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    $this->actingAs($user)
        ->get(route('panel.services.show', $service))
        ->assertOk()
        ->assertDontSee('Správa domény');
});

it('admin domain service detail shows expiry and domain shortcut', function (): void {
    $admin = adminUser();
    [$service] = makeDomainService(customerUser()->customer->id);

    $this->actingAs($admin)
        ->get(route('admin.services.show', $service))
        ->assertOk()
        ->assertSee('Detail domény')
        ->assertSee('Auto-prodloužení');
});
