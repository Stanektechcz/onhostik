<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Models\Service;
use App\Models\MaintenanceWindow;
use App\Models\ServiceFirewallRule;
use App\Models\ServiceHealthIncident;

// ── Panel Service 360° sections ───────────────────────────────────────────────

it('panel service detail shows firewall card for hosting services', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    $this->actingAs($user)
        ->get(route('panel.services.show', $service))
        ->assertOk()
        ->assertSee('Firewall');
});

it('panel service detail hides firewall card for domain services', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'         => $user->customer->id,
        'provisioning_driver' => ProvisioningDriver::Wedos,
    ]);

    $this->actingAs($user)
        ->get(route('panel.services.show', $service))
        ->assertOk()
        ->assertDontSee('panel/sluzby/firewall');
});

it('panel service detail lists own firewall rules', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    ServiceFirewallRule::create([
        'service_id' => $service->id,
        'direction'  => 'in',
        'protocol'   => 'tcp',
        'port_from'  => 22,
        'port_to'    => 22,
        'ip_cidr'    => '198.51.100.0/24',
        'action'     => 'deny',
        'is_active'  => true,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('panel.services.show', $service))
        ->assertOk()
        ->assertSee('198.51.100.0/24');
});

it('panel service detail shows open incidents for the service', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    ServiceHealthIncident::create([
        'service_id' => $service->id,
        'severity'   => 'warning',
        'title'      => 'Zpomalené odezvy webu 274',
        'status'     => 'open',
    ]);

    $this->actingAs($user)
        ->get(route('panel.services.show', $service))
        ->assertOk()
        ->assertSee('Zpomalené odezvy webu 274');
});

it('panel service detail hides resolved incidents', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    ServiceHealthIncident::create([
        'service_id'  => $service->id,
        'severity'    => 'info',
        'title'       => 'Vyřešený incident 274',
        'status'      => 'resolved',
        'resolved_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('panel.services.show', $service))
        ->assertOk()
        ->assertDontSee('Vyřešený incident 274');
});

it('panel service detail shows global maintenance windows', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    MaintenanceWindow::create([
        'title'            => 'Globální údržba sítě 274',
        'message'          => 'Údržba páteřní sítě',
        'description'      => 'Údržba páteřní sítě',
        'color'            => 'info',
        'starts_at'        => now()->addDay(),
        'ends_at'          => now()->addDay()->addHours(2),
        'status'           => 'scheduled',
        'show_on_frontend' => false,
        'show_on_admin'    => false,
        'is_active'        => false,
        'notify_customers' => false,
    ]);

    $this->actingAs($user)
        ->get(route('panel.services.show', $service))
        ->assertOk()
        ->assertSee('Globální údržba sítě 274');
});

it('customer cannot see incidents of another customers service', function (): void {
    $user  = customerUser();
    $other = customerUser();

    $foreign = Service::factory()->create(['customer_id' => $other->customer->id]);

    $this->actingAs($user)
        ->get(route('panel.services.show', $foreign))
        ->assertForbidden();
});
