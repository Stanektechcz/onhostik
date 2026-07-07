<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('authenticated customer can request a DNS health check', function (): void {
    $user = customerUser();

    $response = $this->actingAs($user)
        ->getJson(route('panel.tools.dns.check') . '?domain=example.com&type=A')
        ->assertOk();

    expect($response->json())->toHaveKey('domain');
    expect($response->json())->toHaveKey('records');
    expect($response->json())->toHaveKey('match');
});

it('invalid DNS record type is rejected', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->getJson(route('panel.tools.dns.check') . '?domain=example.com&type=INVALID')
        ->assertUnprocessable();
});

it('domain parameter is required', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->getJson(route('panel.tools.dns.check') . '?type=A')
        ->assertUnprocessable();
});

it('type parameter is required', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->getJson(route('panel.tools.dns.check') . '?domain=example.com')
        ->assertUnprocessable();
});

it('unauthenticated user is redirected from DNS check', function (): void {
    $this->get(route('panel.tools.dns.check') . '?domain=example.com&type=A')
        ->assertRedirect();
});
