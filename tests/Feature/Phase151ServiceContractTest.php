<?php

declare(strict_types=1);

use App\Domains\Provisioning\Models\Service;

it('admin can download service contract PDF', function (): void {
    $admin    = adminUser();
    $customer = customerUser();
    $service  = Service::factory()->create(['customer_id' => $customer->customer->id]);

    $this->actingAs($admin)
         ->get(route('admin.services.contract', $service))
         ->assertOk()
         ->assertHeader('Content-Type', 'application/pdf');
});

it('contract response has correct content-disposition filename', function (): void {
    $admin    = adminUser();
    $customer = customerUser();
    $service  = Service::factory()->create([
        'customer_id' => $customer->customer->id,
        'label'       => 'Test VPS',
    ]);

    $response = $this->actingAs($admin)
         ->get(route('admin.services.contract', $service));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))
        ->toContain('smlouva-')
        ->toContain((string) $service->id);
});

it('customer cannot download service contract', function (): void {
    adminUser();
    $customer = customerUser();
    $service  = Service::factory()->create(['customer_id' => $customer->customer->id]);

    $this->actingAs($customer)
         ->get(route('admin.services.contract', $service))
         ->assertForbidden();
});

it('non-existent service returns 404 for contract', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.services.contract', 99999999))
         ->assertNotFound();
});

it('unauthenticated user is redirected from contract download', function (): void {
    $customer = customerUser();
    $service  = Service::factory()->create(['customer_id' => $customer->customer->id]);

    $this->get(route('admin.services.contract', $service))
         ->assertRedirect('/login');
});
