<?php

declare(strict_types=1);

namespace App\Console\Commands\Forensics;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who was a member, who owned, who was staff — as the stored data tells it, for `onhost:forensics:lookback` (TASK-0038,
 * permission program P0-01 / IF-0). Read-only: SELECTs, cached per run. The audit's attach/remove rows are the history,
 * the current membership row its last word; nothing recorded is `null`, never silently "not a member".
 */
final class MembershipHistory
{
    /** @var array<string, list<array{kind:string, user:string, at:CarbonImmutable}>> */
    private array $events = [];

    /** @var array<string, ?array{created_at:CarbonImmutable, expires_at:?CarbonImmutable, role:string}> */
    private array $memberships = [];

    /** @var array<string, bool> */
    private array $staff = [];

    /** @var array<string, ?string> user id => lower-case e-mail, null when the account is gone */
    private array $emails = [];

    /** @var array<string, bool> organization id => the audit reaches back to its creation (every membership left a row) */
    private array $covered = [];

    /** the oldest audit row ('' when the audit is empty), read once */
    private ?string $oldestAudit = null;

    /** @var array<string, true> the (organization, person) pairs judged since the last reset — the `checked` count of a source */
    private array $judged = [];

    public function resetJudged(): void
    {
        $this->judged = [];
    }

    public function judgedCount(): int
    {
        return count($this->judged);
    }

    /**
     * Was the person a member of the organization at that moment? null when nothing recorded says either way.
     *
     * @return array{0:?bool, 1:?CarbonImmutable} [member, when the membership had ended]
     */
    public function membershipAt(string $organizationId, string $userId, CarbonImmutable $at): array
    {
        $this->judged["{$organizationId}#{$userId}"] = true;
        $last = null;
        foreach ($this->events($organizationId) as $event) {
            if ($event['user'] === $userId && $event['at']->lessThanOrEqualTo($at)) {
                $last = $event;
            }
        }
        $current = $this->currentMembership($organizationId, $userId);
        if ($last !== null && $last['kind'] === 'remove') {
            return [false, $last['at']];
        }
        if ($current !== null && $current['expires_at'] !== null && $current['expires_at']->lessThan($at)) { // access that ended on its date (H343)
            return [false, $current['expires_at']];
        }
        if ($last !== null) {
            return [true, null];
        }

        return $current === null ? [null, null] : [$current['created_at']->lessThanOrEqualTo($at), null];
    }

    /** Removed after that moment and admitted again later: a stretch without membership that a later event covers. */
    public function readmittedSince(string $organizationId, string $userId, CarbonImmutable $since): bool
    {
        $removed = false;
        foreach ($this->events($organizationId) as $event) {
            if ($event['user'] !== $userId || $event['at']->lessThan($since)) {
                continue;
            }
            if ($event['kind'] === 'remove') {
                $removed = true;
            } elseif ($removed) {
                return true;
            }
        }

        return false;
    }

    /** Somebody (anybody) had been removed from the organization by that moment. */
    public function removedBefore(string $organizationId, CarbonImmutable $at): bool
    {
        foreach ($this->events($organizationId) as $event) {
            if ($event['kind'] === 'remove' && $event['at']->lessThanOrEqualTo($at)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A membership nothing recorded means "never a member" only where the audit reaches back to the organization's
     * creation — every membership since then left an attach row. Otherwise it is unknown (review round 1).
     */
    public function historyCovered(string $organizationId): bool
    {
        if (! array_key_exists($organizationId, $this->covered)) {
            $this->oldestAudit ??= (string) DB::table('audit_events')->min('created_at');
            $created = DB::table('organizations')->where('id', $organizationId)->value('created_at');
            $this->covered[$organizationId] = $this->oldestAudit !== '' && $created !== null && ! CarbonImmutable::parse((string) $created)->lessThan(CarbonImmutable::parse($this->oldestAudit));
        }

        return $this->covered[$organizationId];
    }

    /**
     * Who owned the organization at that moment: the `from` of the first transfer after it, or today's owner when none
     * followed. '' when the organization is gone.
     *
     * @param  Collection<int, array{organization_id:string, from:string, to:string, at:CarbonImmutable}>  $transfers  in time order
     */
    public function ownerAt(?object $organization, Collection $transfers, CarbonImmutable $at): string
    {
        if ($organization === null) {
            return '';
        }
        foreach ($transfers as $transfer) {
            if ($transfer['at']->greaterThan($at) && $transfer['from'] !== '') {
                return $transfer['from'];
            }
        }

        return (string) $organization->owner_user_id;
    }

    /** @return ?array{created_at:CarbonImmutable, expires_at:?CarbonImmutable, role:string} */
    public function currentMembership(string $organizationId, string $userId): ?array
    {
        $key = "{$organizationId}#{$userId}";
        if (! array_key_exists($key, $this->memberships)) {
            $row = DB::table('organization_memberships')->where('organization_id', $organizationId)->where('user_id', $userId)->first(['created_at', 'expires_at', 'role_key']);
            $this->memberships[$key] = $row === null ? null : ['created_at' => CarbonImmutable::parse($row->created_at ?? '1970-01-01'),
                'expires_at' => $row->expires_at === null ? null : CarbonImmutable::parse($row->expires_at), 'role' => (string) $row->role_key];
        }

        return $this->memberships[$key];
    }

    public function isStaff(string $userId): bool
    {
        return $this->staff[$userId] ??= (bool) DB::table('users')->where('id', $userId)->value('is_staff');
    }

    public function emailOf(string $userId): ?string
    {
        if (! array_key_exists($userId, $this->emails)) {
            $email = DB::table('users')->where('id', $userId)->value('email');
            $this->emails[$userId] = $email === null ? null : mb_strtolower((string) $email);
        }

        return $this->emails[$userId];
    }

    /** @param list<string> $emails lower-case @return int how many of them belong to no account */
    public function addressesWithoutAccount(array $emails): int
    {
        $known = [];
        foreach (array_chunk($emails, 500) as $chunk) {
            $known = array_merge($known, DB::table('users')->whereIn(DB::raw('lower(email)'), $chunk)->selectRaw('lower(email) as e')->pluck('e')->map(fn ($e) => (string) $e)->all());
        }

        return count(array_diff($emails, $known));
    }

    /** @return list<array{kind:string, user:string, at:CarbonImmutable}> */
    private function events(string $organizationId): array
    {
        return $this->events[$organizationId] ??= DB::table('audit_events')->where('organization_id', $organizationId)
            ->whereIn('action', ['organization.member.attach', 'organization.member.remove'])->orderBy('created_at')->orderBy('id')->get(['action', 'detail', 'created_at'])
            ->map(function ($row) {
                $detail = is_string($row->detail) ? json_decode($row->detail, true) : $row->detail;

                return ['kind' => $row->action === 'organization.member.remove' ? 'remove' : 'attach', 'user' => (string) (is_array($detail) ? ($detail['user_id'] ?? '') : ''), 'at' => CarbonImmutable::parse($row->created_at)];
            })
            ->all();
    }
}
