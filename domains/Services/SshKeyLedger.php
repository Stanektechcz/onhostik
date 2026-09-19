<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\SshKeyGrant;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Throwable;

/**
 * The life of a public key on a shell account (Brain card H185). A panel stores the key as text on the account and
 * knows nothing about people; this ledger knows whose key it is, by fingerprint, so that a person who leaves the
 * organization loses the keys that were theirs on every site the organization has — and a panel that could not be
 * reached keeps the revocation visibly open instead of looking done.
 *
 *  • the action step reports what it did (`installed()`, `removed()`, or `removalRequested()` when the panel only
 *    accepted the change and applies it later); only the fingerprint is kept. An install is recorded when the panel
 *    accepts it: believing in a key that never arrived costs one harmless revocation, the opposite would hide a key;
 *  • `revokeForUser()` runs when a member is removed: every active key of that person is taken off its account through
 *    the ordinary audited `shell.key` operation; a grant stays `revoking` until the panel has taken the change;
 *  • `settle()` runs on a schedule: a revocation whose operation failed, or could not even be queued, is asked for again,
 *    and one that keeps failing is put in front of staff.
 *
 * Scope: shell accounts of web sites, the only place where the platform manages keys after creation. Keys given to a
 * virtual server at its creation live under the customer's own root; nothing here can, or pretends to, recall them.
 */
final class SshKeyLedger
{
    public const RETRY_AFTER_MINUTES = 10;

    public const STUCK_AFTER_ATTEMPTS = 3;

    public function __construct(private readonly ServiceService $services, private readonly OutboxPublisher $outbox) {}

    /** @return ?array{type:string, fingerprint:string, comment:?string} */
    public static function describe(string $publicKey): ?array
    {
        $parts = preg_split('/\s+/', trim($publicKey), 3) ?: [];
        $blob = isset($parts[1]) ? base64_decode($parts[1], true) : false;
        if (count($parts) < 2 || $blob === false || $blob === '') {
            return null;
        }

        return ['type' => (string) $parts[0], 'fingerprint' => 'SHA256:'.rtrim(base64_encode(hash('sha256', $blob, true)), '='), 'comment' => isset($parts[2]) && $parts[2] !== '' ? mb_substr($parts[2], 0, 190) : null];
    }

    /** A key now sits on the account: whatever was there before is no longer the account's key. */
    public function installed(Service $service, string $targetRemoteId, ?string $targetLabel, string $publicKey, ?string $ownerUserId, string $actorType, ?string $actorId): ?SshKeyGrant
    {
        $key = self::describe($publicKey);
        if ($key === null || $targetRemoteId === '') {
            return null;
        }
        $previous = SshKeyGrant::query()->where('service_id', $service->id)->where('target_remote_id', $targetRemoteId)->whereIn('state', [SshKeyGrant::ACTIVE, SshKeyGrant::REVOKING])->get();
        $label = $targetLabel ?? $previous->first()?->target_label;
        foreach ($previous as $old) {
            $old->forceFill(['state' => SshKeyGrant::REPLACED, 'revoked_at' => now(), 'last_error' => null])->save();
        }

        return SshKeyGrant::query()->create([
            'organization_id' => $service->organization_id, 'service_id' => $service->id, 'target_remote_id' => $targetRemoteId, 'target_label' => $label,
            'owner_user_id' => $ownerUserId, 'installed_by_type' => $actorType, 'installed_by_id' => $actorId,
            'key_type' => $key['type'], 'fingerprint' => $key['fingerprint'], 'comment' => $key['comment'], 'state' => SshKeyGrant::ACTIVE, 'installed_at' => now(),
        ]);
    }

    /**
     * The panel accepted the removal but has not carried it out yet (ISPConfig applies changes from a job queue): the
     * grant is open until the operation that asked for it has succeeded — `confirmed()` closes it then.
     */
    public function removalRequested(Service $service, string $targetRemoteId, string $operationId, string $reason = 'key removed'): int
    {
        return SshKeyGrant::query()->where('service_id', $service->id)->where('target_remote_id', $targetRemoteId)->whereIn('state', [SshKeyGrant::ACTIVE, SshKeyGrant::REVOKING])->get()
            ->each(fn (SshKeyGrant $g) => $g->forceFill(['state' => SshKeyGrant::REVOKING, 'revoke_operation_id' => $operationId, 'revoke_reason' => $g->getAttribute('revoke_reason') ?? $reason, 'revoke_requested_at' => $g->revoke_requested_at ?? now()])->save())->count();
    }

    /** The operation that asked a panel to drop keys has succeeded: those keys are gone. */
    public function confirmed(string $operationId): int
    {
        return SshKeyGrant::query()->where('revoke_operation_id', $operationId)->where('state', SshKeyGrant::REVOKING)
            ->update(['state' => SshKeyGrant::REVOKED, 'revoked_at' => now(), 'last_error' => null, 'updated_at' => now()]);
    }

    /** The panel took the key off the account, or the account is gone. */
    public function removed(Service $service, string $targetRemoteId): int
    {
        return SshKeyGrant::query()->where('service_id', $service->id)->where('target_remote_id', $targetRemoteId)->whereIn('state', [SshKeyGrant::ACTIVE, SshKeyGrant::REVOKING])
            ->update(['state' => SshKeyGrant::REVOKED, 'revoked_at' => now(), 'last_error' => null, 'updated_at' => now()]);
    }

    /** The default owner of a key nobody named: the member who installed it. Staff and automation own nothing. */
    public static function ownerFor(Service $service, ?string $requestedOwnerId, string $actorType, ?string $actorId): ?string
    {
        $candidate = $requestedOwnerId ?: ($actorType === 'user' ? $actorId : null);
        if ($candidate === null || $candidate === '') {
            return null;
        }

        return OrganizationMembership::query()->where('organization_id', $service->organization_id)->where('user_id', $candidate)->exists() ? $candidate : null;
    }

    /**
     * @param  ?list<string>  $serviceIds
     * @return array{keys:int, requested:int, pending:int, accounts:list<array{service_id:string, service:string, account:?string, fingerprint:string}>}
     */
    public function revokeForUser(Organization $organization, string $userId, CommandContext $context, string $reason = 'member removed', ?array $serviceIds = null): array
    {
        $report = ['keys' => 0, 'requested' => 0, 'pending' => 0, 'accounts' => []];
        $grants = SshKeyGrant::query()->where('organization_id', $organization->id)->where('owner_user_id', $userId)->where('state', SshKeyGrant::ACTIVE)
            ->when($serviceIds !== null, fn ($q) => $q->whereIn('service_id', $serviceIds))->get(); // null = every site of the organization; a list = the services of one project
        foreach ($grants as $grant) {
            $report['keys']++;
            $grant->forceFill(['state' => SshKeyGrant::REVOKING, 'revoke_reason' => mb_substr($reason, 0, 190), 'revoke_requested_at' => now()])->save();
            $service = Service::query()->find($grant->service_id);
            $this->request($grant, $service, $context) ? $report['requested']++ : $report['pending']++;
            $report['accounts'][] = ['service_id' => $grant->service_id, 'service' => $service === null ? $grant->service_id : (string) ($service->label ?: ($service->hostname ?: $service->name)), 'account' => $grant->target_label, 'fingerprint' => $grant->fingerprint];
        }
        if ($report['keys'] > 0) {
            $this->outbox->publish(GenericEvent::of('access.ssh_keys.revoked', 'organization', $organization->id, ['user_id' => $userId, 'count' => $report['keys'], 'pending' => $report['pending'], 'accounts' => array_slice($report['accounts'], 0, 20)], $organization->id));
        }

        return $report;
    }

    /**
     * Revocations the panel has not taken yet (scheduled). A failed or never-queued one is asked for again; one that
     * keeps failing is reported to staff once per crossing of the threshold — until then the key may still work.
     *
     * @return array{open:int, confirmed:int, retried:int, stuck:int}
     */
    public function settle(): array
    {
        $stats = ['open' => 0, 'confirmed' => 0, 'retried' => 0, 'stuck' => 0];
        $context = CommandContext::system('ssh key revocation');
        foreach (SshKeyGrant::query()->where('state', SshKeyGrant::REVOKING)->orderBy('revoke_requested_at')->limit(500)->get() as $grant) {
            $stats['open']++;
            $service = Service::query()->withTrashed()->find($grant->service_id);
            if ($service === null || $service->trashed() || $service->terminated_at !== null) { // the site and its accounts are gone with the service (H346)
                $grant->forceFill(['state' => SshKeyGrant::REVOKED, 'revoked_at' => now(), 'last_error' => null])->save();
                $stats['confirmed']++;

                continue;
            }
            $operation = $grant->revoke_operation_id === null ? null : Operation::query()->find($grant->revoke_operation_id);
            if ($operation !== null && $operation->state === Operation::SUCCEEDED) { // the step reports this itself; a row that missed it is closed here
                $grant->forceFill(['state' => SshKeyGrant::REVOKED, 'revoked_at' => $operation->finished_at ?? now(), 'last_error' => null])->save();
                $stats['confirmed']++;

                continue;
            }
            if ($operation !== null && ! in_array($operation->state, [Operation::FAILED, Operation::CANCELLED, 'COMPENSATED'], true)) {
                continue; // queued, running or waiting for the panel: still open, nothing to repeat
            }
            if ($grant->updated_at !== null && $grant->updated_at->gt(now()->subMinutes(self::RETRY_AFTER_MINUTES))) {
                continue;
            }
            if ($operation !== null) { // the run failed at the panel: keep why, in the panel's own safe words
                $grant->forceFill(['last_error' => mb_substr((string) (data_get($operation->error, 'message') ?: 'the operation failed'), 0, 300)])->save();
            }
            if ($grant->revoke_attempts === self::STUCK_AFTER_ATTEMPTS) { // said once, when the threshold is crossed
                $this->outbox->publish(GenericEvent::of('security.ssh_key.revocation.stuck', 'service', $grant->service_id, ['grant_id' => $grant->id, 'fingerprint' => $grant->fingerprint, 'account' => $grant->target_label, 'attempts' => $grant->revoke_attempts, 'error' => $grant->last_error], $grant->organization_id));
                $stats['stuck']++;
            }
            $this->request($grant, $service, $context);
            $stats['retried']++;
        }

        return $stats;
    }

    /** @return list<array<string,mixed>> */
    public function forService(Service $service): array
    {
        $grants = SshKeyGrant::query()->where('service_id', $service->id)->orderByRaw("case state when 'revoking' then 0 when 'active' then 1 else 2 end")->orderByDesc('installed_at')->limit(200)->get();
        $owners = User::query()->whereIn('id', $grants->pluck('owner_user_id')->filter()->unique()->all())->get()->keyBy('id');
        $members = OrganizationMembership::query()->where('organization_id', $service->organization_id)->pluck('user_id')->all();

        return $grants->map(fn (SshKeyGrant $g) => self::present($g, $g->owner_user_id === null ? null : $owners->get($g->owner_user_id), $members))->values()->all();
    }

    /**
     * @param  list<string>  $memberIds
     * @return array<string,mixed>
     */
    public static function present(SshKeyGrant $grant, ?User $owner, array $memberIds): array
    {
        return [
            'id' => $grant->id, 'service_id' => $grant->service_id, 'account' => ['remote_id' => $grant->target_remote_id, 'user' => $grant->target_label],
            'key_type' => $grant->getAttribute('key_type'), 'fingerprint' => $grant->fingerprint, 'comment' => $grant->getAttribute('comment'),
            'owner' => $grant->owner_user_id === null ? null : ['user_id' => $grant->owner_user_id, 'name' => $owner?->name, 'email' => $owner?->email, 'member' => in_array($grant->owner_user_id, $memberIds, true)],
            // wanted and observed are told apart: `revoking` means the key may still open a session
            'state' => $grant->state, 'revocation' => $grant->state !== SshKeyGrant::REVOKING ? null : ['requested_at' => $grant->revoke_requested_at?->toIso8601String(), 'attempts' => $grant->revoke_attempts, 'operation_id' => $grant->revoke_operation_id, 'last_error' => $grant->last_error, 'reason' => $grant->getAttribute('revoke_reason')],
            'installed_at' => $grant->installed_at?->toIso8601String(), 'revoked_at' => $grant->revoked_at?->toIso8601String(),
        ];
    }

    /** Ask the panel to take the key off; false = it could not even be queued, the grant stays open with the reason. */
    private function request(SshKeyGrant $grant, ?Service $service, CommandContext $context): bool
    {
        $attempt = $grant->revoke_attempts + 1;
        try {
            if ($service === null) {
                throw new \RuntimeException('the service is not known any more');
            }
            $operation = $this->services->requestAction($service, 'shell.key', $context->withScope($grant->organization_id), "ssh-key-revoke:{$grant->id}:{$attempt}", ['remote_id' => $grant->target_remote_id, 'ssh_key' => '']);
            $grant = $grant->fresh() ?? $grant; // on a synchronous queue the step has already closed the grant
            $grant->forceFill(['revoke_operation_id' => $operation->id, 'revoke_attempts' => $attempt, 'last_error' => $grant->state === SshKeyGrant::REVOKED ? null : $grant->last_error])->save();

            return true;
        } catch (Throwable $e) { // the panel is locked, the service is busy with another operation, the account is gone
            $grant->forceFill(['revoke_attempts' => $attempt, 'last_error' => mb_substr($e->getMessage(), 0, 300)])->save();

            return false;
        }
    }
}
