<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Middleware\RememberReferral;
use App\Http\Presenters\Presenters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Onhost\Domain\Identity\Models\EmailVerificationToken;
use Onhost\Domain\Identity\Models\PersonalAccessToken;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\Notifications\PasswordResetNotification;
use Onhost\Domain\Identity\Notifications\VerifyEmailNotification;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Loyalty\ReferralService;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Domain\Risk\Turnstile;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Session endpoints (blueprint §17): cookie session for the surfaces (Sanctum SPA),
 * lockout after repeated failures, TOTP as second factor when enrolled, staff MFA mandatory,
 * step-up grants for high-risk commands. Errors are per field (`errors.{field}`) as the UI renders them.
 */
final class AuthController extends ApiController
{
    public function register(Request $request, OrganizationService $organizations, AuditRecorder $audit, OutboxPublisher $outbox, Turnstile $turnstile): JsonResponse
    {
        $turnstile->requireForRegistration($request); // §5q-6: no account without the human check while it is enforced
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'], 'password' => ['required', Password::min(12)->letters()->numbers()->uncompromised()],
            'organization' => ['nullable', 'string', 'max:190'], 'type' => ['nullable', 'in:person,company'], 'ico' => ['nullable', 'string', 'max:20'], 'vat_id' => ['nullable', 'string', 'max:20'], 'country' => ['nullable', 'string', 'size:2'], 'locale' => ['nullable', 'in:cs,sk,en'], 'partner_code' => ['nullable', 'string', 'max:24'],
            'terms' => ['accepted'], 'ref' => ['nullable', 'string', 'max:16'],
        ]);
        $token = Str::random(48);
        $ip = $request->ip();
        $agent = mb_substr((string) $request->userAgent(), 0, 250);
        $sessionId = $this->api->sessionId($request);
        $refCookie = $request->cookie(RememberReferral::COOKIE);
        [$user, $organization, $context] = DB::transaction(function () use ($data, $organizations, $token, $ip, $agent, $sessionId, $refCookie): array {
            $user = User::query()->create(['name' => $data['name'], 'email' => strtolower($data['email']), 'password' => $data['password'], 'locale' => $data['locale'] ?? 'cs', 'timezone' => 'Europe/Prague', 'is_staff' => false, 'state' => 'active']);
            $context = new CommandContext('user', $user->id, null, null, $ip, $agent, $sessionId, correlationId: CommandContext::currentCorrelationId());
            $organization = $organizations->create($user, ['name' => $data['organization'] ?? $data['name'], 'type' => $data['type'] ?? (! empty($data['ico']) ? 'company' : 'person'), 'ico' => $data['ico'] ?? null, 'vat_id' => $data['vat_id'] ?? null, 'country' => strtoupper($data['country'] ?? 'CZ'), 'billing_email' => $user->email], $context);
            if (! empty($data['partner_code'])) {
                app(PartnerService::class)->attribute($organization, (string) $data['partner_code'], $context);
            }
            $ref = $data['ref'] ?? $refCookie; // the form's code, else the code a landing visit remembered (audit §5k-4)
            if (! empty($ref)) { // customer-to-customer invite code (audit §5j-2); unknown codes are ignored, registration never fails on them
                app(ReferralService::class)->attach($organization, (string) $ref, $ip, $context);
            }
            EmailVerificationToken::query()->create(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'purpose' => 'verify', 'expires_at' => now()->addDays(3)]);

            return [$user, $organization, $context];
        }, 3);
        $user->notify(new VerifyEmailNotification($token, $organization->name)); // the secret goes by mail only, never into the redacted outbox
        $outbox->publish(GenericEvent::of('identity.registered', 'user', $user->id, ['email' => $user->email, 'name' => $user->name, 'organization_id' => $organization->id, 'locale' => $user->locale], $organization->id));
        $audit->record($context->withScope($organization->id), 'auth.register', 'succeeded', ['email' => $user->email], 'user', $user->id);
        $this->startSession($request, $user);

        return response()->json($this->me($request)->getData(true), 201);
    }

    public function login(Request $request, StepUpService $stepUp, AuditRecorder $audit, OutboxPublisher $outbox): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string'], 'totp' => ['nullable', 'string', 'max:16'], 'remember' => ['nullable', 'boolean']]);
        $user = User::query()->where('email', strtolower($data['email']))->first();
        $context = new CommandContext('user', $user?->id, null, null, $request->ip(), mb_substr((string) $request->userAgent(), 0, 250), null, correlationId: CommandContext::currentCorrelationId());
        // The lock is asked BEFORE the password. It used to come after: a locked account kept judging guesses, and the answer
        // told them apart — 422 for a wrong password, 423 for the right one. Lock the account on purpose, then test passwords at leisure.
        if ($user !== null && $user->isLocked()) {
            throw new DomainError('account_locked', 'Účet je dočasně uzamčen po opakovaných neúspěšných pokusech.', 423, ['field' => 'email', 'locked_until' => $user->locked_until?->toIso8601String()]);
        }
        if ($user === null || ! Hash::check($data['password'], (string) $user->password)) {
            if ($user !== null) {
                $this->countFailedLogin($user, $context, $audit, $outbox, $request, 'password');
            }
            throw new DomainError('invalid_credentials', 'E-mail nebo heslo nesouhlasí.', 422, ['field' => 'password']);
        }
        if (! $user->isActive()) {
            throw new DomainError('account_inactive', 'Účet není aktivní.', 403, ['field' => 'email']);
        }
        if ($user->is_staff && config('onhost.identity.staff_mfa_required', true) && ! $user->hasMfa()) {
            throw new DomainError('mfa_enrolment_required', 'Interní účty musí mít zapnuté dvoufázové ověření.', 403, ['field' => 'totp', 'enrol' => '/v1/me/totp/enroll']);
        }
        if ($user->hasTotp()) {
            if (empty($data['totp'])) {
                throw new DomainError('mfa_required', 'Zadejte kód z autentikátoru.', 403, ['field' => 'totp', 'mfa' => 'totp']);
            }
            if (! $stepUp->verifyTotp($user, (string) $data['totp']) && ! $stepUp->consumeRecoveryCode($user, (string) $data['totp'])) {
                $this->countFailedLogin($user, $context, $audit, $outbox, $request, 'totp'); // six digits are guessable when nothing counts the guesses
                throw new DomainError('mfa_invalid', 'Kód z autentikátoru nesouhlasí.', 422, ['field' => 'totp']);
            }
        }
        $this->startSession($request, $user, (bool) ($data['remember'] ?? false));
        $user->forceFill(['failed_login_attempts' => 0, 'locked_until' => null, 'last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $audit->record($context, 'auth.login', 'succeeded', ['email' => $user->email, 'mfa' => $user->hasTotp() ? 'totp' : null], 'user', $user->id);
        $outbox->publish(GenericEvent::of('security.login', 'user', $user->id, ['email' => $user->email, 'ip' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 120), 'at' => now()->toIso8601String()]));

        return $this->me($request);
    }

    public function logout(Request $request, AuditRecorder $audit): JsonResponse
    {
        $user = $request->user();
        if ($user instanceof User) {
            $token = $user->currentAccessToken();
            if ($token instanceof PersonalAccessToken) {
                $token->forceFill(['revoked_at' => now()])->save();
            }
            $audit->record($this->api->context($request), 'auth.logout', 'succeeded', [], 'user', $user->id);
        }
        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['data' => ['signed_out' => true]]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->api->user($request);
        $memberships = OrganizationMembership::query()->with('organization')->where('user_id', $user->id)->current()->get();
        $current = $this->api->organization($request, false);
        $grant = app(StepUpService::class)->activeGrant($user, $this->api->sessionId($request));

        return response()->json(['data' => [
            'user' => Presenters::user($user),
            'organizations' => $memberships->map(fn ($m) => $m->organization ? Presenters::organization($m->organization, $m->role_key) : null)->filter()->values()->all(),
            'organization' => $current ? Presenters::organization($current, $memberships->firstWhere('organization_id', $current->id)?->role_key) : null,
            'step_up' => $grant ? ['method' => $grant->method, 'expires_at' => $grant->expires_at?->toIso8601String()] : null,
            'surface' => $user->is_staff ? 'admin' : 'panel',
        ]]);
    }

    public function stepUp(Request $request, StepUpService $stepUp, AuditRecorder $audit): JsonResponse
    {
        $data = $request->validate(['method' => ['required', 'in:totp,recovery,password'], 'code' => ['required', 'string', 'max:200']]); // password: StepUpService decides who may use it
        $user = $this->api->user($request);
        $grant = $stepUp->verifyAndGrant($user, $data['method'], $data['code'], $this->api->sessionId($request), $request->ip());
        $audit->record($this->api->context($request), 'auth.step_up', 'succeeded', ['method' => $grant->method], 'user', $user->id, stepUp: $grant->method);

        return response()->json(['data' => ['method' => $grant->method, 'expires_at' => $grant->expires_at?->toIso8601String()]]);
    }

    private function countFailedLogin(User $user, CommandContext $context, AuditRecorder $audit, OutboxPublisher $outbox, Request $request, string $reason): void
    {
        $attempts = $user->failed_login_attempts + 1;
        $lock = $attempts >= (int) config('onhost.identity.max_failed_logins', 8);
        $user->forceFill(['failed_login_attempts' => $lock ? 0 : $attempts, 'locked_until' => $lock ? now()->addMinutes((int) config('onhost.identity.lockout_minutes', 15)) : $user->locked_until])->save();
        $audit->record($context, 'auth.login', 'failed', ['email' => $user->email, 'locked' => $lock, 'reason' => $reason], 'user', $user->id);
        if ($lock) {
            $outbox->publish(GenericEvent::of('security.account_locked', 'user', $user->id, ['email' => $user->email, 'ip' => $request->ip()]));
        }
    }

    public function requestPasswordReset(Request $request, OutboxPublisher $outbox, AuditRecorder $audit): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::query()->where('email', strtolower($data['email']))->first();
        if ($user !== null && $user->isActive()) {
            $token = Str::random(48);
            EmailVerificationToken::query()->where('user_id', $user->id)->where('purpose', 'reset')->whereNull('used_at')->update(['used_at' => now()]);
            EmailVerificationToken::query()->create(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'purpose' => 'reset', 'expires_at' => now()->addHours(2)]);
            $user->notify(new PasswordResetNotification($token, $request->ip()));
            $outbox->publish(GenericEvent::of('identity.password_reset_requested', 'user', $user->id, ['email' => $user->email, 'ip' => $request->ip(), 'locale' => $user->locale]));
            $audit->record(new CommandContext('user', $user->id, ip: $request->ip()), 'auth.password_reset.request', 'succeeded', [], 'user', $user->id);
        }

        return response()->json(['data' => ['sent' => true]]); // never reveals whether the address exists
    }

    public function confirmPasswordReset(Request $request, StepUpService $stepUp, AuditRecorder $audit, OutboxPublisher $outbox): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string'], 'password' => ['required', Password::min(12)->letters()->numbers()->uncompromised()]]);
        $row = EmailVerificationToken::query()->where('token_hash', hash('sha256', $data['token']))->where('purpose', 'reset')->whereNull('used_at')->where('expires_at', '>', now())->first();
        if ($row === null) {
            throw new DomainError('reset_token_invalid', 'Odkaz pro obnovu hesla je neplatný nebo vypršel.', 422, ['field' => 'token']);
        }
        $user = User::query()->findOrFail($row->user_id);
        $user->forceFill(['password' => $data['password'], 'password_changed_at' => now(), 'failed_login_attempts' => 0, 'locked_until' => null, 'remember_token' => Str::random(60)])->save();
        $row->forceFill(['used_at' => now()])->save();
        $stepUp->revokeAll($user);
        $user->tokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $audit->record(new CommandContext('user', $user->id, ip: $request->ip()), 'auth.password_reset.confirm', 'succeeded', [], 'user', $user->id);
        $outbox->publish(GenericEvent::of('security.password_changed', 'user', $user->id, ['email' => $user->email, 'ip' => $request->ip(), 'api_access' => 'revoked']));
        // The single-use link plus the new password signs the browser in — for an account whose only factor is the password.
        // With an authenticator enrolled (and for staff, who must have one) the link proves the mailbox and nothing more:
        // signing in here would make a read of somebody's mail enough to take over an account the second factor protects.
        $secondFactor = $user->hasTotp() || ($user->is_staff && config('onhost.identity.staff_mfa_required', true));
        if ($secondFactor || ! $user->isActive()) {
            return response()->json(['data' => ['reset' => true, 'signed_in' => false, 'surface' => 'login']]);
        }
        $this->startSession($request, $user);

        return response()->json(['data' => ['reset' => true, 'signed_in' => true, 'user' => Presenters::user($user), 'surface' => $user->is_staff ? 'admin' : 'panel']]);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string']]);
        $row = EmailVerificationToken::query()->where('token_hash', hash('sha256', $data['token']))->where('purpose', 'verify')->whereNull('used_at')->where('expires_at', '>', now())->first();
        if ($row === null) {
            throw new DomainError('verify_token_invalid', 'Ověřovací odkaz je neplatný nebo vypršel.', 422, ['field' => 'token']);
        }
        User::query()->whereKey($row->user_id)->update(['email_verified_at' => now()]);
        $row->forceFill(['used_at' => now()])->save();

        return response()->json(['data' => ['verified' => true]]);
    }

    private function startSession(Request $request, User $user, bool $remember = false): void
    {
        $request->headers->remove('X-Organization'); // stale header from the previously signed-in account
        $request->query->remove('organization');
        if ($request->hasSession()) {
            Auth::guard('web')->login($user, $remember);
            $request->session()->regenerate();
        } else {
            Auth::guard('web')->setUser($user);
        }
    }
}
