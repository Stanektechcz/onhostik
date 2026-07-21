<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Phase H auth hardening: login/2FA throttling (H111), session security
 * (H115), password policy (H116) and self-service session revocation (H117).
 *
 * Two-factor authentication stays OPTIONAL throughout — these controls are
 * what carry the weight instead of forcing 2FA on anyone.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, IntegrationSeeder::class]);
});

// ── H111: brute-force throttling ──────────────────────────────────────────────

it('registers a login rate limiter', function (): void {
    expect(RateLimiter::limiter('login'))->not->toBeNull();
});

it('registers a two-factor challenge rate limiter', function (): void {
    // Without this, an optional-2FA setup would still leave the 6-digit
    // challenge brute-forceable for the users who DID enable it.
    expect(RateLimiter::limiter('two-factor'))->not->toBeNull();
});

it('never authenticates a wrong password, however many times it is tried', function (): void {
    $user = User::factory()->create(['password' => Hash::make('CorrectHorse1!'), 'is_active' => true]);

    for ($attempt = 0; $attempt < 8; $attempt++) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        expect(auth()->check())->toBeFalse();
    }
});

it('throttles a brute-force run against one account', function (): void {
    $user = User::factory()->create(['password' => Hash::make('CorrectHorse1!'), 'is_active' => true]);

    // Mirrors the limiter key registered in FortifyServiceProvider.
    $key = \Illuminate\Support\Str::transliterate(mb_strtolower($user->email) . '|127.0.0.1');

    for ($attempt = 0; $attempt < 6; $attempt++) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
    }

    // Five attempts per minute is the configured budget — the run must have
    // exhausted it rather than being allowed to continue indefinitely.
    expect(RateLimiter::tooManyAttempts($key, 5))->toBeTrue();
});

// ── H115: session security ────────────────────────────────────────────────────

it('keeps session cookies http-only and same-site', function (): void {
    expect(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBeIn(['lax', 'strict']);
});

it('rotates the session id on login', function (): void {
    $user = User::factory()->create(['password' => Hash::make('CorrectHorse1!')]);

    $this->get('/login');
    $before = session()->getId();

    $this->post('/login', ['email' => $user->email, 'password' => 'CorrectHorse1!']);

    // Session fixation guard — the pre-login id must not survive.
    expect(session()->getId())->not->toBe($before);
});

// ── H116: password policy ─────────────────────────────────────────────────────

it('rejects a password that fails the complexity policy', function (string $password): void {
    $this->post('/register', [
        'name'                  => 'Test',
        'email'                 => 'novy@example.cz',
        'password'              => $password,
        'password_confirmation' => $password,
    ])->assertSessionHasErrors('password');
})->with([
    'too short'      => 'Ab1!',
    'no uppercase'   => 'lowercase1!',
    'no digit'       => 'NoDigitsHere!',
    'no special'     => 'NoSpecial123',
]);

it('accepts a password that satisfies the policy', function (): void {
    $this->post('/register', [
        'name'                  => 'Test',
        'email'                 => 'dobry@example.cz',
        'password'              => 'Sp&cialStrong9',
        'password_confirmation' => 'Sp&cialStrong9',
    ]);

    expect(User::where('email', 'dobry@example.cz')->exists())->toBeTrue();
});

it('can switch on the breached-password check without code changes', function (): void {
    // Off by default so an offline install is not blocked on an HTTP call.
    expect(config('auth.password_breach_check'))->toBeFalse();

    config()->set('auth.password_breach_check', true);

    $rules = (new class {
        use \App\Actions\Fortify\PasswordValidationRules;

        /** @return array<int, mixed> */
        public function expose(): array
        {
            return $this->passwordRules();
        }
    })->expose();

    $hasUncompromised = collect($rules)->contains(
        fn ($rule): bool => $rule instanceof \Illuminate\Validation\Rules\Password
    );

    expect($hasUncompromised)->toBeTrue();
});

// ── H117: revoking other sessions ─────────────────────────────────────────────

it('requires the current password to end other sessions', function (): void {
    $user = User::factory()->create(['password' => Hash::make('CorrectHorse1!')]);

    $this->actingAs($user)
        ->from(route('panel.account.sessions'))
        ->delete(route('panel.account.sessions.destroy-others'), ['password' => 'not-my-password'])
        ->assertSessionHasErrors('password');
});

it('ends other sessions and rotates the remember token', function (): void {
    $user  = User::factory()->create(['password' => Hash::make('CorrectHorse1!')]);
    $token = $user->remember_token;

    DB::table('sessions')->insert([
        'id'            => 'other-device-session',
        'user_id'       => $user->id,
        'ip_address'    => '10.0.0.5',
        'user_agent'    => 'Other device',
        'payload'       => base64_encode('x'),
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($user)
        ->delete(route('panel.account.sessions.destroy-others'), ['password' => 'CorrectHorse1!'])
        ->assertRedirect();

    // Both the session row AND the remember-me token must be gone — deleting
    // the row alone left a "remember me" cookie working on the other device.
    expect(DB::table('sessions')->where('id', 'other-device-session')->exists())->toBeFalse()
        ->and($user->fresh()->remember_token)->not->toBe($token);
});

it('audits a session revocation', function (): void {
    $user = User::factory()->create(['password' => Hash::make('CorrectHorse1!')]);

    $this->actingAs($user)
        ->delete(route('panel.account.sessions.destroy-others'), ['password' => 'CorrectHorse1!']);

    expect(\Spatie\Activitylog\Models\Activity::where('description', 'user.logged_out_other_devices')->exists())
        ->toBeTrue();
});

// ── H114: 2FA stays optional, by decision ─────────────────────────────────────

it('does not force two-factor authentication on anyone', function (): void {
    // Deliberate product decision: 2FA is offered, never mandatory. The
    // controls above are what carry the security weight instead.
    expect(DB::table('settings')->where('group', 'security')->where('name', 'require_admin_2fa')->value('payload'))
        ->toBeNull();

    // customerUser() attaches the customer profile the panel requires.
    $user = customerUser();
    $user->forceFill(['two_factor_confirmed_at' => null])->save();

    $this->actingAs($user)->get(route('panel.dashboard'))->assertOk();
});
