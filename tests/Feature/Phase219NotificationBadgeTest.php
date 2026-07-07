<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('customer can get notification badge count as json', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->getJson(route('panel.notifications.badge.count'))
        ->assertOk()
        ->assertJsonStructure(['count']);
});

it('badge count returns zero when no unread notifications', function (): void {
    $user = customerUser();

    $response = $this->actingAs($user)
        ->getJson(route('panel.notifications.badge.count'))
        ->assertOk();

    expect($response->json('count'))->toBe(0);
});

it('badge count reflects unread database notifications', function (): void {
    $user = customerUser();

    $user->notifications()->create([
        'id'   => \Illuminate\Support\Str::uuid()->toString(),
        'type' => 'App\Notifications\GenericNotification',
        'data' => ['title' => 'Test'],
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('panel.notifications.badge.count'))
        ->assertOk();

    expect($response->json('count'))->toBe(1);
});

it('guest cannot access badge count endpoint', function (): void {
    $this->getJson(route('panel.notifications.badge.count'))
        ->assertUnauthorized();
});

it('badge count drops to zero after marking all as read', function (): void {
    $user = customerUser();

    $user->notifications()->create([
        'id'   => \Illuminate\Support\Str::uuid()->toString(),
        'type' => 'App\Notifications\GenericNotification',
        'data' => ['title' => 'hello'],
    ]);

    $this->actingAs($user)->post(route('panel.notifications.read-all'));

    $response = $this->actingAs($user)
        ->getJson(route('panel.notifications.badge.count'))
        ->assertOk();

    expect($response->json('count'))->toBe(0);
});
