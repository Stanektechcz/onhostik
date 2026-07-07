<?php

declare(strict_types=1);

use App\Domains\Provisioning\Models\Server;
use App\Domains\Provisioning\Models\Service;

it('admin can view server capacity page', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.servers.capacity'))
         ->assertOk()
         ->assertViewIs('admin.server-capacity');
});

it('server capacity page shows rows with usage data', function (): void {
    $admin  = adminUser();
    $server = Server::factory()->create(['max_services' => 10]);

    $response = $this->actingAs($admin)
         ->get(route('admin.servers.capacity'))
         ->assertOk();

    $rows = $response->viewData('rows');
    expect($rows)->not->toBeEmpty();
});

it('server capacity calculates percentage usage', function (): void {
    $admin    = adminUser();
    $server   = Server::factory()->create(['max_services' => 10]);
    $customer = customerUser();

    Service::factory()->count(5)->create([
        'server_id'   => $server->id,
        'customer_id' => $customer->customer->id,
        'status'      => 'active',
    ]);

    $response = $this->actingAs($admin)
         ->get(route('admin.servers.capacity'))
         ->assertOk();

    $rows     = $response->viewData('rows');
    $serverRow = $rows->firstWhere('server.id', $server->id);
    expect($serverRow['pct'])->toBe(50);
});

it('server capacity flags alert at 80 percent usage', function (): void {
    $admin    = adminUser();
    $server   = Server::factory()->create(['max_services' => 10]);
    $customer = customerUser();

    Service::factory()->count(9)->create([
        'server_id'   => $server->id,
        'customer_id' => $customer->customer->id,
        'status'      => 'active',
    ]);

    $response = $this->actingAs($admin)
         ->get(route('admin.servers.capacity'))
         ->assertOk();

    $rows      = $response->viewData('rows');
    $serverRow = $rows->firstWhere('server.id', $server->id);
    expect($serverRow['alert'])->toBeTrue();
});

it('customer cannot view server capacity', function (): void {
    $customer = customerUser();

    $this->actingAs($customer)
         ->get(route('admin.servers.capacity'))
         ->assertForbidden();
});
