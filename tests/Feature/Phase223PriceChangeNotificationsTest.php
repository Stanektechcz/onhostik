<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view price change notifications', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.price-change-notifications.index'))
        ->assertOk()
        ->assertViewHas('notifications');
});

it('admin can create a price change notification', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.price-change-notifications.store'), [
            'title'          => 'Test',
            'body'           => 'Body text',
            'effective_from' => date('Y-m-d', strtotime('+30 days')),
            'status'         => 'draft',
        ])
        ->assertRedirect();

    expect(\App\Models\PriceChangeNotification::count())->toBe(1);
});

it('admin can delete a price change notification', function (): void {
    $record = \App\Models\PriceChangeNotification::create([
        'title'          => 'Delete me',
        'body'           => 'Some body',
        'effective_from' => now()->addDays(30),
        'status'         => 'draft',
    ]);

    $this->actingAs(adminUser())
        ->delete(route('admin.price-change-notifications.destroy', $record))
        ->assertRedirect();

    $this->assertDatabaseMissing('price_change_notifications', ['title' => 'Delete me']);
});

it('guest cannot access price change notifications', function (): void {
    $this->get(route('admin.price-change-notifications.index'))
        ->assertRedirect();
});

it('store validates title is required', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.price-change-notifications.store'), [
            'body'           => 'Body text',
            'effective_from' => date('Y-m-d', strtotime('+30 days')),
            'status'         => 'draft',
        ])
        ->assertSessionHasErrors('title');
});
