<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Onhost\Domain\Identity\Models\EmailVerificationToken;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\Notifications\PasswordResetNotification;
use Onhost\Domain\Identity\Notifications\VerifyEmailNotification;
use Onhost\Domain\Identity\StepUp\Totp;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Outbox\OutboxMessage;

const STRONG = 'Correct-Horse-Battery-9-Staple';

it('registers a customer with an organization, verification token and a stateful session', function () {
    Notification::fake();
    $response = $this->withHeaders(['Referer' => 'http://localhost'])->postJson('/v1/auth/register', ['name' => 'Jana Nováková', 'email' => 'Jana@Example.cz', 'password' => STRONG, 'organization' => 'Skladomat s.r.o.', 'ico' => '12345678', 'terms' => true]);
    $response->assertCreated()->assertJsonPath('data.user.email', 'jana@example.cz')->assertJsonPath('data.organization.name', 'Skladomat s.r.o.')->assertJsonPath('data.organization.role', 'owner')->assertJsonPath('data.organization.customer_class', 'b2b');
    $user = User::query()->where('email', 'jana@example.cz')->firstOrFail();
    expect(EmailVerificationToken::query()->where('user_id', $user->id)->where('purpose', 'verify')->exists())->toBeTrue();
    Notification::assertSentTo($user, VerifyEmailNotification::class);
    expect(OutboxMessage::query()->where('name', 'identity.registered')->exists())->toBeTrue();
    expect(AuditEvent::query()->where('action', 'auth.register')->exists())->toBeTrue();
    expect(Organization::query()->where('owner_user_id', $user->id)->count())->toBe(1);

    $this->withHeaders(['Referer' => 'http://localhost'])->getJson('/v1/me')->assertOk()->assertJsonPath('data.user.id', $user->id); // cookie session carried over
});

it('registers and signs in a second account in a browser that still sends the previous account\'s organization', function () {
    Notification::fake();
    [, $previous] = $this->customerWithOrganization(['email' => 'first@example.cz']);
    $stale = ['Referer' => 'http://localhost', 'X-Organization' => $previous->id]; // the bridge caches the last signed-in user's organization
    $this->withHeaders($stale)->postJson('/v1/auth/register', ['name' => 'Druhý Účet', 'email' => 'second@example.cz', 'password' => STRONG, 'terms' => true])
        ->assertCreated()->assertJsonPath('data.user.email', 'second@example.cz')->assertJsonPath('data.organization.name', 'Druhý Účet');
    $this->withHeaders(['Referer' => 'http://localhost'])->postJson('/v1/auth/logout')->assertOk();
    $this->withHeaders($stale)->postJson('/v1/auth/login', ['email' => 'second@example.cz', 'password' => STRONG])->assertOk()->assertJsonPath('data.organization.name', 'Druhý Účet');
    $this->withHeaders($stale)->getJson('/v1/me')->assertForbidden(); // outside the sign-in the header is still enforced
});

it('returns field errors for weak passwords and duplicate e-mails', function () {
    $this->customer(['email' => 'taken@example.cz']);
    $this->postJson('/v1/auth/register', ['name' => 'X', 'email' => 'taken@example.cz', 'password' => 'short', 'terms' => true])
        ->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
});

it('signs in with password, locks after repeated failures and audits every attempt', function () {
    $this->withoutMiddleware(ThrottleRequests::class); // the per-e-mail throttle would trip before the account lock
    $user = $this->customer(['email' => 'user@example.cz']);
    $this->postJson('/v1/auth/login', ['email' => 'user@example.cz', 'password' => 'wrong'])->assertUnprocessable()->assertJsonPath('error', 'invalid_credentials')->assertJsonPath('errors.password.0', 'E-mail nebo heslo nesouhlasí.');
    $this->postJson('/v1/auth/login', ['email' => 'user@example.cz', 'password' => 'Correct-Horse-Battery-9'])->assertOk()->assertJsonPath('data.user.email', 'user@example.cz')->assertJsonPath('data.surface', 'panel');
    expect(AuditEvent::query()->where('action', 'auth.login')->where('result', 'failed')->count())->toBe(1)->and(AuditEvent::query()->where('action', 'auth.login')->where('result', 'succeeded')->count())->toBe(1);

    config()->set('onhost.identity.max_failed_logins', 3);
    foreach ([1, 2, 3] as $i) {
        $this->postJson('/v1/auth/login', ['email' => 'user@example.cz', 'password' => 'wrong'])->assertUnprocessable();
    }
    $this->postJson('/v1/auth/login', ['email' => 'user@example.cz', 'password' => 'Correct-Horse-Battery-9'])->assertStatus(423)->assertJsonPath('error', 'account_locked');
    expect(OutboxMessage::query()->where('name', 'security.account_locked')->exists())->toBeTrue();
});

it('enrols TOTP, requires the code at login and grants step-up for high-risk commands', function () {
    $user = $this->customer(['email' => 'mfa@example.cz']);
    $this->actingAs($user, 'sanctum');
    $secret = $this->postJson('/v1/me/totp/enroll')->assertOk()->json('data.secret');
    $this->postJson('/v1/me/totp/confirm', ['code' => '000000'])->assertUnprocessable()->assertJsonPath('errors.code.0', 'Kód z autentikátoru nesouhlasí.');
    $codes = $this->postJson('/v1/me/totp/confirm', ['code' => Totp::code($secret)])->assertOk()->json('data.recovery_codes');
    expect($codes)->toHaveCount(8);
    $this->postJson('/v1/me/totp/enroll')->assertStatus(409); // enabled: cannot re-enrol without disabling first

    $this->app['auth']->forgetGuards();
    $this->postJson('/v1/auth/login', ['email' => 'mfa@example.cz', 'password' => 'Correct-Horse-Battery-9'])->assertForbidden()->assertJsonPath('error', 'mfa_required');
    $this->postJson('/v1/auth/login', ['email' => 'mfa@example.cz', 'password' => 'Correct-Horse-Battery-9', 'totp' => Totp::code($secret, time() + 30)])->assertOk(); // next window: the confirmed code cannot be replayed

    $this->actingAs($user->fresh(), 'sanctum');
    $this->postJson('/v1/auth/step-up', ['method' => 'totp', 'code' => '123456'])->assertForbidden()->assertJsonPath('error', 'step_up_failed');
    $this->postJson('/v1/auth/step-up', ['method' => 'recovery', 'code' => $codes[0]])->assertOk()->assertJsonPath('data.method', 'recovery');
    $this->postJson('/v1/auth/step-up', ['method' => 'recovery', 'code' => $codes[0]])->assertForbidden(); // single use
});

it('resets a password through a single-use token and revokes sessions', function () {
    Notification::fake();
    $user = $this->customer(['email' => 'reset@example.cz']);
    $this->postJson('/v1/auth/password/reset', ['email' => 'nobody@example.cz'])->assertOk(); // never reveals existence
    $this->postJson('/v1/auth/password/reset', ['email' => 'reset@example.cz'])->assertOk();
    $token = null;
    Notification::assertSentTo($user, PasswordResetNotification::class, function (PasswordResetNotification $n) use (&$token) {
        $token = $n->token;

        return true;
    });
    expect($token)->not->toBeEmpty();
    expect(json_encode(OutboxMessage::query()->where('name', 'identity.password_reset_requested')->firstOrFail()->payload))->not->toContain($token); // the secret never enters the outbox

    $this->postJson('/v1/auth/password/reset/confirm', ['token' => 'bogus', 'password' => STRONG])->assertUnprocessable()->assertJsonPath('error', 'reset_token_invalid');
    $this->postJson('/v1/auth/password/reset/confirm', ['token' => $token, 'password' => STRONG])->assertOk();
    $this->postJson('/v1/auth/password/reset/confirm', ['token' => $token, 'password' => STRONG])->assertUnprocessable(); // used
    $this->postJson('/v1/auth/login', ['email' => 'reset@example.cz', 'password' => STRONG])->assertOk();
    expect(OutboxMessage::query()->where('name', 'security.password_changed')->exists())->toBeTrue();
});

it('refuses staff accounts without MFA and unauthenticated access to protected routes', function () {
    $staff = $this->staff('sre', ['email' => 'sre@onhost.cz']);
    $this->postJson('/v1/auth/login', ['email' => 'sre@onhost.cz', 'password' => 'Correct-Horse-Battery-9'])->assertForbidden()->assertJsonPath('error', 'mfa_enrolment_required');
    $this->getJson('/v1/me')->assertUnauthorized()->assertJsonPath('error', 'unauthenticated');
    $this->getJson('/v1/services')->assertUnauthorized();
});

it('grants a password step-up to accounts without TOTP and refuses it once staff MFA is enforced', function () {
    [$owner] = $this->customerWithOrganization(['password' => STRONG]);
    $this->actingAs($owner, 'sanctum');
    $this->postJson('/v1/auth/step-up', ['method' => 'password', 'code' => 'wrong-password-1234'])->assertForbidden()->assertJsonPath('error', 'step_up_failed');
    $this->postJson('/v1/auth/step-up', ['method' => 'password', 'code' => STRONG])->assertOk()->assertJsonPath('data.method', 'password');

    config()->set('onhost.security.staff_mfa_required', false);
    $staff = $this->staff('platform_owner', ['password' => STRONG]);
    $this->actingAs($staff, 'sanctum');
    $this->postJson('/v1/auth/step-up', ['method' => 'password', 'code' => STRONG])->assertOk()->assertJsonPath('data.method', 'password');
    config()->set('onhost.security.staff_mfa_required', true);
    $this->postJson('/v1/auth/step-up', ['method' => 'password', 'code' => STRONG])->assertForbidden(); // production: staff need TOTP or a recovery code
    $this->postJson('/v1/auth/step-up', ['method' => 'sms', 'code' => '1'])->assertUnprocessable();
});
