<?php

declare(strict_types=1);

use App\Models\CustomerCommunicationLog;
use App\Domains\Customer\Models\Customer;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can list communication logs', function () {
    $admin = adminUser();
    $this->actingAs($admin)
        ->get(route('admin.customer-communication-logs.index'))
        ->assertOk()
        ->assertViewIs('admin.customer-communication-logs.index');
});

it('admin can filter logs by customer', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create();
    CustomerCommunicationLog::create([
        'customer_id'   => $customer->id,
        'channel'       => 'email',
        'direction'     => 'outbound',
        'body'          => 'Test body',
        'created_by'    => $admin->id,
        'admin_user_id' => $admin->id,
    ]);
    $response = $this->actingAs($admin)
        ->get(route('admin.customer-communication-logs.index', ['customer_id' => $customer->id]));
    $response->assertOk()->assertViewHas('customerId', (string) $customer->id);
});

it('admin can create a communication log', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create();
    $this->actingAs($admin)->post(route('admin.customer-communication-logs.store'), [
        'customer_id' => $customer->id,
        'channel'     => 'phone',
        'direction'   => 'inbound',
        'subject'     => 'Support call',
        'body'        => 'Customer called about billing.',
    ])->assertRedirect();
    $this->assertDatabaseHas('customer_communication_logs', ['customer_id' => $customer->id, 'channel' => 'phone']);
});

it('admin communication log store fails without body', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create();
    $this->actingAs($admin)->post(route('admin.customer-communication-logs.store'), [
        'customer_id' => $customer->id,
        'channel'     => 'chat',
        'direction'   => 'outbound',
    ])->assertSessionHasErrors('body');
});

it('panel customer can view own communication logs', function () {
    $user = customerUser();
    CustomerCommunicationLog::create([
        'customer_id'   => $user->customer->id,
        'channel'       => 'note',
        'direction'     => 'inbound',
        'body'          => 'Internal note.',
        'admin_user_id' => null,
        'created_by'    => null,
    ]);
    $this->actingAs($user)
        ->get(route('panel.customer-communication-logs.index'))
        ->assertOk()
        ->assertViewIs('panel.customer-communication-logs.index');
});
