<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('panel user can view their domain transfer requests', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.domain-transfer-requests.index'))
        ->assertOk()
        ->assertViewHas('requests');
});

it('panel user can submit a domain transfer request', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->post(route('panel.domain-transfer-requests.store'), [
            'domain_name' => 'mycompany.cz',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('domain_transfer_requests', ['domain_name' => 'mycompany.cz']);
});

it('transfer request is created with pending status', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->post(route('panel.domain-transfer-requests.store'), [
            'domain_name' => 'mycompany.cz',
        ]);

    expect(\App\Models\DomainTransferRequest::first()->status)->toBe('pending');
});

it('panel user only sees their own transfer requests', function (): void {
    $user      = customerUser();
    $otherUser = customerUser();

    \App\Models\DomainTransferRequest::create([
        'customer_id' => $otherUser->customer->id,
        'domain_name' => 'other.cz',
        'status'      => 'pending',
    ]);

    $response = $this->actingAs($user)
        ->get(route('panel.domain-transfer-requests.index'))
        ->assertOk();

    expect($response->viewData('requests')->count())->toBe(0);
});

it('guest cannot access domain transfer requests', function (): void {
    $this->get(route('panel.domain-transfer-requests.index'))
        ->assertRedirect();
});
