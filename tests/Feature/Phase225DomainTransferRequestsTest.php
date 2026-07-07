<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view domain transfer requests', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.domain-transfer-requests.index'))
        ->assertOk()
        ->assertViewHas('requests');
});

it('admin can update domain transfer request status', function (): void {
    $customer = \App\Domains\Customer\Models\Customer::factory()->create();
    $record   = \App\Models\DomainTransferRequest::create([
        'customer_id' => $customer->id,
        'domain_name' => 'example.cz',
        'status'      => 'pending',
    ]);

    $this->actingAs(adminUser())
        ->patch(route('admin.domain-transfer-requests.update', $record), [
            'status'     => 'processing',
            'admin_note' => 'Working on it',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('domain_transfer_requests', ['status' => 'processing']);
});

it('status update also sets handled_by', function (): void {
    $admin    = adminUser();
    $customer = \App\Domains\Customer\Models\Customer::factory()->create();
    $record   = \App\Models\DomainTransferRequest::create([
        'customer_id' => $customer->id,
        'domain_name' => 'example2.cz',
        'status'      => 'pending',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.domain-transfer-requests.update', $record), [
            'status'     => 'processing',
            'admin_note' => 'In progress',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('domain_transfer_requests', ['handled_by' => $admin->id]);
});

it('guest cannot access domain transfers', function (): void {
    $this->get(route('admin.domain-transfer-requests.index'))
        ->assertRedirect();
});

it('update validates status enum', function (): void {
    $customer = \App\Domains\Customer\Models\Customer::factory()->create();
    $record   = \App\Models\DomainTransferRequest::create([
        'customer_id' => $customer->id,
        'domain_name' => 'example3.cz',
        'status'      => 'pending',
    ]);

    $this->actingAs(adminUser())
        ->patch(route('admin.domain-transfer-requests.update', $record), [
            'status' => 'invalid_status',
        ])
        ->assertSessionHasErrors('status');
});
