<?php

declare(strict_types=1);

use App\Models\LoginToken;
use Illuminate\Support\Facades\Notification;
use App\Notifications\MagicLinkNotification;

it('magic link request form is accessible', function (): void {
    $this->get(route('magic-link.form'))
         ->assertOk()
         ->assertViewIs('auth.magic-link-request');
});

it('sending magic link to existing email creates token', function (): void {
    Notification::fake();
    $customer = customerUser();

    $this->post(route('magic-link.send'), ['email' => $customer->email])
         ->assertRedirect();

    expect(LoginToken::where('user_id', $customer->id)->exists())->toBeTrue();
    Notification::assertSentTo($customer, MagicLinkNotification::class);
});

it('sending magic link to non-existent email does not reveal existence', function (): void {
    $this->post(route('magic-link.send'), ['email' => 'notreal@example.com'])
         ->assertRedirect()
         ->assertSessionHas('status');
});

it('valid magic link token logs user in', function (): void {
    $customer = customerUser();

    $token = LoginToken::create([
        'user_id'    => $customer->id,
        'token'      => 'valid-test-token-64chars-paddddddddddddddddddddddddddddddddddd',
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->get(route('magic-link.login', $token->token))
         ->assertRedirect('/panel');

    $this->assertAuthenticatedAs($customer);
});

it('expired magic link token redirects to login with error', function (): void {
    $customer = customerUser();

    LoginToken::create([
        'user_id'    => $customer->id,
        'token'      => 'expired-test-token-64charrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrr',
        'expires_at' => now()->subMinutes(5),
    ]);

    $this->get(route('magic-link.login', 'expired-test-token-64charrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrr'))
         ->assertRedirect('/login');
});
