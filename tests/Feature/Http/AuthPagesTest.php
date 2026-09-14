<?php

declare(strict_types=1);

use Onhost\Domain\Identity\Models\EmailVerificationToken;
use Onhost\Domain\Identity\Models\User;

/* The links the platform mails land on pages that exist: set a new password (reset / guest account) and confirm the e-mail. */

it('serves the set-password page for a token and the prototype reset card without one', function () {
    $this->get('/obnova-hesla?token=abc123')->assertOk()->assertSee('Nastavit nové heslo')->assertSee('data-token="abc123"', false)->assertSee('/v1/auth/password/reset/confirm', false);
    $this->get('/obnova-hesla')->assertOk()->assertSee('onhost-shell.js', false)->assertDontSee('data-token=', false);
    $this->get('/overeni-emailu?token=t0k')->assertOk()->assertSee('Potvrzení e-mailu')->assertSee('data-token="t0k"', false)->assertSee('/v1/auth/verify-email', false);
});

it('sets the password from a single-use token, signs the browser in, and confirms e-mails', function () {
    $user = $this->customer(['email' => 'reset@example.cz']);
    EmailVerificationToken::query()->create(['user_id' => $user->id, 'token_hash' => hash('sha256', 'reset-token'), 'purpose' => 'reset', 'expires_at' => now()->addHours(48)]);
    $this->withHeaders(['Referer' => 'http://localhost', 'Accept-Language' => 'cs'])->postJson('/v1/auth/password/reset/confirm', ['token' => 'reset-token', 'password' => 'short'])->assertUnprocessable()->assertJsonPath('errors.password.0', 'Heslo musí mít alespoň 12 znaků.');
    $this->withHeaders(['Referer' => 'http://localhost'])->postJson('/v1/auth/password/reset/confirm', ['token' => 'reset-token', 'password' => 'Correct-Horse-Battery-9-Staple'])
        ->assertOk()->assertJsonPath('data.reset', true)->assertJsonPath('data.user.email', 'reset@example.cz')->assertJsonPath('data.surface', 'panel');
    $this->withHeaders(['Referer' => 'http://localhost'])->getJson('/v1/me')->assertOk()->assertJsonPath('data.user.id', $user->id); // signed in after setting the password
    $this->withHeaders(['Referer' => 'http://localhost'])->postJson('/v1/auth/password/reset/confirm', ['token' => 'reset-token', 'password' => 'Correct-Horse-Battery-9-Staple'])->assertUnprocessable()->assertJsonPath('error', 'reset_token_invalid'); // single use

    $verify = $this->customer(['email' => 'verify@example.cz', 'email_verified_at' => null]);
    EmailVerificationToken::query()->create(['user_id' => $verify->id, 'token_hash' => hash('sha256', 'verify-token'), 'purpose' => 'verify', 'expires_at' => now()->addDays(3)]);
    $this->postJson('/v1/auth/verify-email', ['token' => 'verify-token'])->assertOk()->assertJsonPath('data.verified', true);
    expect(User::query()->find($verify->id)->email_verified_at)->not->toBeNull();
});
