<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('panel user can view price change notifications', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.price-change-notifications.index'))
        ->assertOk()
        ->assertViewHas('notifications');
});

it('panel only shows sent notifications', function (): void {
    $user  = customerUser();
    $admin = adminUser();

    \App\Models\PriceChangeNotification::create([
        'title'          => 'Draft',
        'body'           => 'B',
        'effective_from' => now()->addMonth(),
        'status'         => 'draft',
        'created_by'     => $admin->id,
    ]);

    \App\Models\PriceChangeNotification::create([
        'title'          => 'Sent',
        'body'           => 'B',
        'effective_from' => now()->addMonth(),
        'status'         => 'sent',
        'created_by'     => $admin->id,
    ]);

    $response = $this->actingAs($user)
        ->get(route('panel.price-change-notifications.index'))
        ->assertOk();

    expect($response->viewData('notifications')->total())->toBe(1);
});

it('notifications are sorted by effective_from descending', function (): void {
    $user  = customerUser();
    $admin = adminUser();

    \App\Models\PriceChangeNotification::create([
        'title'          => 'Older',
        'body'           => 'B',
        'effective_from' => now()->addMonth(),
        'status'         => 'sent',
        'created_by'     => $admin->id,
    ]);

    \App\Models\PriceChangeNotification::create([
        'title'          => 'Newer',
        'body'           => 'B',
        'effective_from' => now()->addMonths(2),
        'status'         => 'sent',
        'created_by'     => $admin->id,
    ]);

    $this->actingAs($user)
        ->get(route('panel.price-change-notifications.index'))
        ->assertOk();
});

it('guest cannot access price change notifications', function (): void {
    $this->get(route('panel.price-change-notifications.index'))
        ->assertRedirect();
});

it('panel notification list is empty when no sent notifications exist', function (): void {
    $user = customerUser();

    $response = $this->actingAs($user)
        ->get(route('panel.price-change-notifications.index'))
        ->assertOk();

    expect($response->viewData('notifications')->total())->toBe(0);
});
