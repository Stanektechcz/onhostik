<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\StepUp;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Onhost\Domain\Identity\Models\StepUpGrant;
use Onhost\Domain\Identity\Models\User;
use Onhost\Platform\Errors\DomainError;

/**
 * Step-up authentication (§61.6). A grant is bound to the user + session and
 * lives `onhost.identity.step_up_ttl_minutes` minutes. WebAuthn is preferred,
 * TOTP accepted; password-only step-up is allowed for customers without MFA and
 * always refused for staff (WebAuthn/TOTP required).
 */
final class StepUpService
{
    public function activeGrant(User $user, ?string $sessionId): ?StepUpGrant
    {
        $query = StepUpGrant::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('granted_at');
        if ($sessionId !== null) {
            $query->where(fn ($q) => $q->where('session_id', $sessionId)->orWhereNull('session_id'));
        }

        return $query->first();
    }

    public function grant(User $user, string $method, ?string $sessionId, ?string $ip): StepUpGrant
    {
        $ttl = (int) config('onhost.identity.step_up_ttl_minutes', 10);

        return StepUpGrant::query()->create([
            'user_id' => $user->id,
            'method' => $method,
            'session_id' => $sessionId,
            'ip' => $ip,
            'granted_at' => now(),
            'expires_at' => now()->addMinutes($ttl),
        ]);
    }

    /** Verify a factor and issue a grant. Throws DomainError on failure (rate-limited). */
    public function verifyAndGrant(User $user, string $method, string $secret, ?string $sessionId, ?string $ip): StepUpGrant
    {
        $this->assertNotThrottled($user);
        $ok = match ($method) {
            'totp' => $user->hasTotp() && $this->verifyTotp($user, $secret),
            'recovery' => $this->consumeRecoveryCode($user, $secret),
            // the password is a second verification only for somebody who has no authenticator: once TOTP is enrolled it is the
            // second factor, for customers and staff alike — and staff may use the password only while staff MFA is not enforced
            // (development, first login). The key is `onhost.identity.*`; `onhost.security.*` never existed and read as "off".
            'password' => $user->password !== null && ! $user->hasTotp() && Hash::check($secret, $user->password) && (! $user->is_staff || ! config('onhost.identity.staff_mfa_required', true)),
            default => false,
        };
        if (! $ok) {
            $this->recordFailure($user);
            throw new DomainError('step_up_failed', 'Verification failed.', 403, ['requirement' => 'step_up']);
        }
        Cache::forget($this->throttleKey($user));

        return $this->grant($user, $method, $sessionId, $ip);
    }

    /** Rejects reuse of the same TOTP counter (replay protection). */
    public function verifyTotp(User $user, string $code): bool
    {
        $secret = (string) $user->totp_secret;
        if ($secret === '' || ! Totp::verify($secret, $code)) {
            return false;
        }
        $counter = intdiv(time(), 30);
        $key = "onhost:totp:used:{$user->id}:".preg_replace('/\D/', '', $code);
        if (Cache::has($key)) {
            return false;
        }
        Cache::put($key, $counter, now()->addMinutes(2));

        return true;
    }

    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->recovery_codes ?? [];
        $normalized = strtolower(preg_replace('/[^a-z0-9]/i', '', $code) ?? '');
        foreach ($codes as $index => $hash) {
            if (is_string($hash) && Hash::check($normalized, $hash)) {
                unset($codes[$index]);
                $user->forceFill(['recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    /** @return list<string> plain codes shown once */
    public function issueRecoveryCodes(User $user, int $count = 8): array
    {
        $plain = [];
        $hashed = [];
        for ($i = 0; $i < $count; $i++) {
            $code = strtolower(bin2hex(random_bytes(5)));
            $plain[] = substr($code, 0, 5).'-'.substr($code, 5);
            $hashed[] = Hash::make($code);
        }
        $user->forceFill(['recovery_codes' => $hashed])->save();

        return $plain;
    }

    public function revokeAll(User $user): void
    {
        StepUpGrant::query()->where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    private function assertNotThrottled(User $user): void
    {
        $failures = (int) Cache::get($this->throttleKey($user), 0);
        if ($failures >= 5) {
            throw new DomainError('step_up_throttled', 'Too many failed verifications. Try again in 15 minutes.', 429, ['retry_after_seconds' => 900]);
        }
    }

    private function recordFailure(User $user): void
    {
        $key = $this->throttleKey($user);
        Cache::add($key, 0, now()->addMinutes(15));
        Cache::increment($key);
    }

    private function throttleKey(User $user): string
    {
        return "onhost:stepup:fail:{$user->id}";
    }
}
