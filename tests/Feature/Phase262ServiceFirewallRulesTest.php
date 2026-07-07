<?php

declare(strict_types=1);

use App\Models\ServiceFirewallRule;
use App\Domains\Provisioning\Models\Service;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can list service firewall rules', function () {
    $admin = adminUser();
    $this->actingAs($admin)
        ->get(route('admin.service-firewall-rules.index'))
        ->assertOk()
        ->assertViewIs('admin.service-firewall-rules.index');
});

it('admin can create a firewall rule', function () {
    $admin = adminUser();
    $service = Service::factory()->create();
    $this->actingAs($admin)->post(route('admin.service-firewall-rules.store'), [
        'service_id' => $service->id,
        'direction'  => 'in',
        'protocol'   => 'tcp',
        'port_from'  => 80,
        'port_to'    => 80,
        'ip_cidr'    => '0.0.0.0/0',
        'action'     => 'allow',
        'is_active'  => true,
    ])->assertRedirect();
    $this->assertDatabaseHas('service_firewall_rules', ['service_id' => $service->id, 'action' => 'allow']);
});

it('admin can toggle firewall rule active state', function () {
    $admin = adminUser();
    $service = Service::factory()->create();
    $rule = ServiceFirewallRule::create([
        'service_id'  => $service->id,
        'direction'   => 'in',
        'protocol'    => 'tcp',
        'ip_cidr'     => '10.0.0.0/8',
        'action'      => 'deny',
        'is_active'   => true,
        'created_by'  => $admin->id,
    ]);
    $this->actingAs($admin)
        ->patch(route('admin.service-firewall-rules.update', $rule))
        ->assertRedirect();
    $this->assertDatabaseHas('service_firewall_rules', ['id' => $rule->id, 'is_active' => false]);
});

it('admin can delete a firewall rule', function () {
    $admin = adminUser();
    $service = Service::factory()->create();
    $rule = ServiceFirewallRule::create([
        'service_id'  => $service->id,
        'direction'   => 'out',
        'protocol'    => 'udp',
        'ip_cidr'     => '192.168.0.0/16',
        'action'      => 'allow',
        'is_active'   => true,
        'created_by'  => $admin->id,
    ]);
    $this->actingAs($admin)
        ->delete(route('admin.service-firewall-rules.destroy', $rule))
        ->assertRedirect();
    $this->assertDatabaseMissing('service_firewall_rules', ['id' => $rule->id]);
});

it('panel customer can view own service firewall rules', function () {
    $user = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);
    ServiceFirewallRule::create([
        'service_id' => $service->id,
        'direction'  => 'in',
        'protocol'   => 'tcp',
        'ip_cidr'    => '0.0.0.0/0',
        'action'     => 'allow',
        'is_active'  => true,
    ]);
    $this->actingAs($user)
        ->get(route('panel.service-firewall-rules.index'))
        ->assertOk()
        ->assertViewIs('panel.service-firewall-rules.index');
});
