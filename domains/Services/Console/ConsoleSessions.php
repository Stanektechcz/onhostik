<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Console;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Models\ServiceAccount;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\SessionEnds;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\CommandScope;

/**
 * A console stays open only while the person it was issued to may still open it (permission program D20, S1-08, TASK-0044).
 *
 * Before: a console ticket (`con_…`, ServiceService::consoleAccess) was a bearer value for its two minutes — the relay resolved
 * it for whoever presented it, whatever had happened to its person since — and once resolved the relay proxied the VNC / Wings
 * socket for up to two hours with nobody asking again. A member removed mid-session, an owner whose account was taken out by
 * an owner recovery, a person whose second factor support just reset because somebody else held it: still typing in a root
 * shell. Revoking bindings and credentials did not end a channel that was already open.
 *
 * Now:
 *  · resolving a ticket asks the person behind it (mayRun): their account is active, they still hold `service.console` on that
 *    service (or `staff.console` as staff), and their consoles were not ended after the ticket was issued (endFor);
 *  · a resolved ticket leaves a LIVE record, and the relay asks `GET /console/ws/{token}/alive` every aliveSeconds() — the same
 *    questions again; a "no" closes the socket (infra/console-relay), so a removal reaches an open console within that window;
 *  · endFor($person, $organization|null) is the explicit kill (SessionKill): every ticket issued and every console opened before
 *    it — in that organization, or everywhere — is refused from now on, even though the person may still hold the permission
 *    (an MFA reset: the account is the one in doubt).
 *
 * A descriptor with no person (a system-issued ticket, a descriptor older than TASK-0039) is not judged by a person; an open
 * console of a service deleted since closes either way. Panel sessions the platform cannot close (a panel's own SSO login) are the residual window,
 * stated in docs/runbooks/console-relay.md.
 *
 * TASK-0067 (PR #53 review): the kill marks were cache entries only — a flush or an eviction let every ticket and console they had
 * ended run again. They are rows of `session_ends` now (SessionEnds), the cache a copy. A console whose live record is gone
 * (expired past the relay's two-hour cap, or flushed) closes as before, and the close is audited like every other (the relay
 * controller). A ticket a service account was issued (`issued_to_account`, a pipeline's token) is judged by that account: it closes
 * when the account is disabled or deleted, or belongs to another organization than the service.
 */
final class ConsoleSessions
{
    private const LIVE_SECONDS = 2 * 3600 + 300;

    public function __construct(private readonly Authorizer $authorizer, private readonly SessionEnds $ends) {}

    /** How often the relay asks whether an open console may stay open (onhost.console.alive_check_seconds, never below 5). */
    public static function aliveSeconds(): int
    {
        return max(5, (int) config('onhost.console.alive_check_seconds', 15));
    }

    /**
     * Whether the console a descriptor describes may run now. Null = yes; otherwise why not (a word for the audit and the relay).
     *
     * @param  array<string,mixed>  $descriptor
     */
    public function refusal(array $descriptor, ?CarbonInterface $since = null, bool $open = false): ?string
    {
        $serviceId = $descriptor['service_id'] ?? null;
        $service = is_string($serviceId) && $serviceId !== '' ? Service::query()->find($serviceId) : null;
        if ($open && $service === null) {
            return 'service_gone'; // an open console of a service deleted since closes; a ticket is judged by its person below
        }
        $organizationId = $service !== null ? (string) $service->organization_id : (is_string($descriptor['organization_id'] ?? null) ? $descriptor['organization_id'] : null);
        $issuedTo = $descriptor['issued_to'] ?? null;
        if (! is_string($issuedTo) || $issuedTo === '') {
            return self::accountRefusal($descriptor['issued_to_account'] ?? null, $organizationId);
        }
        $person = User::query()->find($issuedTo);
        if ($person === null || ! $person->isActive()) {
            return 'account_inactive';
        }
        if ($this->endedSince($issuedTo, $organizationId, $since ?? self::issuedAt($descriptor))) {
            return 'session_ended';
        }
        if ($service !== null && ! $this->mayOpen($person, $service)) {
            return 'access_ended';
        }

        return null;
    }

    /**
     * The relay resolved a ticket: remember who the open console is for, so that the relay's alive checks can ask again.
     *
     * @param  array<string,mixed>  $descriptor
     */
    public function opened(string $token, array $descriptor): void
    {
        Cache::put(self::liveKey($token), [
            'service_id' => $descriptor['service_id'] ?? null, 'organization_id' => $descriptor['organization_id'] ?? null,
            'issued_to' => $descriptor['issued_to'] ?? null, 'issued_to_account' => $descriptor['issued_to_account'] ?? null,
            'issued_at' => self::issuedAt($descriptor)->toIso8601String(),
        ], self::LIVE_SECONDS);
    }

    /**
     * The relay's alive check of an open console: null = keep it open; otherwise why it closes and the service it was for (the
     * live record goes with it). `session_unknown` — no live record (expired, flushed) — names no service: nothing remembers it.
     *
     * @return array{reason: string, service_id: ?string}|null
     */
    public function alive(string $token): ?array
    {
        $live = Cache::get(self::liveKey($token));
        if (! is_array($live)) {
            return ['reason' => 'session_unknown', 'service_id' => null];
        }
        $refusal = $this->refusal($live, self::issuedAt($live), open: true);
        if ($refusal === null) {
            return null;
        }
        Cache::forget(self::liveKey($token));
        $serviceId = $live['service_id'] ?? null;

        return ['reason' => $refusal, 'service_id' => is_string($serviceId) && $serviceId !== '' ? $serviceId : null];
    }

    /**
     * Ends the consoles of `$userId`: every ticket issued and every console opened before now — in `$organizationId`, or in every
     * organization when null — is refused from now on (resolve and the next alive check).
     */
    public function endFor(string $userId, ?string $organizationId = null): void
    {
        $this->ends->mark($userId, SessionEnds::CONSOLE, $organizationId); // in the database: a cache flush must not revive it
    }

    private function endedSince(string $userId, ?string $organizationId, CarbonInterface $issuedAt): bool
    {
        $ended = $this->ends->endedAt($userId, SessionEnds::CONSOLE, $organizationId);

        return $ended !== null && ! $issuedAt->isAfter($ended);
    }

    /**
     * A ticket with no person: a service account's (a pipeline's API token) is judged by the account — disabled or deleted, or of
     * another organization than the service, it opens nothing and an open console closes. A ticket the system issued (no person,
     * no account) is not judged by anybody, as before.
     */
    private static function accountRefusal(mixed $accountId, ?string $organizationId): ?string
    {
        if (! is_string($accountId) || $accountId === '') {
            return null;
        }
        $account = ServiceAccount::query()->find($accountId); // a deleted account (soft delete) is not found
        if ($account === null || ! $account->isActive()) {
            return 'account_inactive';
        }
        if ($account->organization_id !== null && $organizationId !== null && (string) $account->organization_id !== $organizationId) {
            return 'access_ended';
        }

        return null;
    }

    private function mayOpen(User $person, Service $service): bool
    {
        $this->authorizer->forget($person); // asked again on every check: the binding may have gone a second ago
        $scope = CommandScope::resource($service->id, (string) $service->organization_id, $service->project_id !== null ? (string) $service->project_id : null);

        return $this->authorizer->can($person, 'service.console', $scope) || $this->authorizer->can($person, 'staff.console', CommandScope::global());
    }

    /**
     * When the ticket was issued. ServiceService stamps it since TASK-0044; an older descriptor counts as issued a ticket's
     * lifetime before it expires, or a lifetime ago — the earliest it can have been, so a kill since still refuses it.
     *
     * @param  array<string,mixed>  $descriptor
     */
    private static function issuedAt(array $descriptor): CarbonInterface
    {
        $ttl = max(1, (int) config('onhost.console.token_ttl_seconds', 120));
        foreach (['issued_at', 'expires_at'] as $field) {
            $value = $descriptor[$field] ?? null;
            if (is_string($value) && $value !== '') {
                $at = CarbonImmutable::parse($value);

                return $field === 'issued_at' ? $at : $at->subSeconds($ttl);
            }
        }

        return CarbonImmutable::now()->subSeconds($ttl);
    }

    private static function liveKey(string $token): string
    {
        return "onhost:console:live:{$token}";
    }
}
