<?php

declare(strict_types=1);

use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Panel activity feed ───────────────────────────────────────────────────────

it('customer can view the activity feed page', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.activity-feed'))
        ->assertOk()
        ->assertSee('Historie aktivit');
});

it('activity feed shows events after an order is placed', function (): void {
    $user   = customerUser();
    placeOrder($user);

    $this->actingAs($user)
        ->get(route('panel.activity-feed'))
        ->assertOk()
        ->assertSee('Objednávka');
});

it('activity feed shows invoice events', function (): void {
    $user   = customerUser();
    placeOrder($user);

    $this->actingAs($user)
        ->get(route('panel.activity-feed'))
        ->assertOk()
        ->assertSee('Faktura');
});

it('activity feed can be filtered by invoices', function (): void {
    $user   = customerUser();
    placeOrder($user);

    $this->actingAs($user)
        ->get(route('panel.activity-feed') . '?filter=invoices')
        ->assertOk()
        ->assertSee('Faktura');
});

it('activity feed can be filtered by tickets', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.activity-feed') . '?filter=tickets')
        ->assertOk();
});

it('unauthenticated user is redirected from activity feed', function (): void {
    $this->get(route('panel.activity-feed'))
        ->assertRedirect();
});

it('activity feed shows empty state when customer has no events', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.activity-feed'))
        ->assertOk()
        ->assertSee('Žádné aktivity');
});
