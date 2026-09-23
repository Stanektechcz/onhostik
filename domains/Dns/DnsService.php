<?php

declare(strict_types=1);

namespace Onhost\Domain\Dns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Onhost\Domain\Dns\Models\DnsChange;
use Onhost\Domain\Dns\Models\DnsRecord;
use Onhost\Domain\Dns\Models\DnsTemplate;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Dns\Models\DnsZoneVersion;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Support\Hostname;
use Onhost\Providers\Contracts\DnsProvider;

/**
 * Canonical DNS (blueprint §48, S39): the platform database is the truth, the
 * provider (PowerDNS by default) is an executor. Edits are staged as changes,
 * committed atomically into a new zone version, and any version can be rolled back.
 */
final class DnsService
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly RecordValidator $validator,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    // ── zones ────────────────────────────────────────────────────────────────

    /**
     * Idempotent: an existing zone with the same name is returned untouched.
     *
     * @param  array<string,string|null>  $vars  template placeholders
     */
    public function ensureZone(string $organizationId, string $name, CommandContext $actor, ?string $domainId = null, ?string $templateKey = 'web_basic', array $vars = [], string $provider = 'powerdns', ?string $providerInstanceId = null): DnsZone
    {
        $name = Hostname::canonical($name);
        $existing = DnsZone::query()->where('name', $name)->first();
        if ($existing !== null) {
            if ($existing->organization_id !== $organizationId) {
                throw new DomainError('dns_zone_taken', "Zone {$name} is managed by another organization.", 409);
            }
            if ($existing->state === 'active') {
                return $existing;
            }
            $zone = $existing;
        } else {
            $zone = DnsZone::query()->create([
                'organization_id' => $organizationId, 'domain_id' => $domainId, 'name' => $name, 'provider' => $provider, 'provider_instance_id' => $providerInstanceId ?? $this->instanceFor($provider)?->id,
                'serial' => 0, 'version' => 0, 'state' => 'pending', 'kind' => 'primary', 'nameservers' => $this->nameservers($provider),
            ]);
            $template = $templateKey === null ? null : DnsTemplate::query()->where('key', $templateKey)->where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $organizationId))->orderByRaw('organization_id is null')->first();
            foreach ($template?->render(array_merge(['domain' => $name], $vars)) ?? [] as $record) {
                $normalized = $this->validator->normalize($record, $name);
                DnsRecord::query()->create(array_merge($normalized, ['zone_id' => $zone->id, 'managed_by' => $record['managed_by'], 'protected' => $record['protected']]));
            }
        }

        $adapter = $this->adapter($zone);
        try {
            $adapter->createZone($name, ['nameservers' => $zone->nameservers, 'kind' => 'Native', 'dnssec' => false]);
            $records = $zone->records()->get()->map(fn (DnsRecord $r) => $r->normalized())->all();
            $this->validator->assertConsistent($records);
            $remote = $adapter->listRecords($name);
            $diff = $this->diff($remote, $records);
            if ($diff !== []) {
                $adapter->applyChanges($name, $diff);
            }
        } catch (ProviderException $e) {
            $zone->forceFill(['state' => 'error'])->save();
            throw $e;
        }
        $serial = $zone->nextSerial();
        $zone->forceFill(['state' => 'active', 'serial' => $serial, 'version' => $zone->version + 1, 'committed_at' => now()])->save();
        $this->snapshot($zone, $actor, 'zone created');
        $this->audit->record($actor->withScope($organizationId), 'dns.zone.create', 'succeeded', ['zone' => $name, 'template' => $templateKey], 'dns_zone', $zone->id);
        $this->outbox->publish(GenericEvent::of('dns.zone.created', 'dns_zone', $zone->id, ['name' => $name, 'domain_id' => $domainId], $organizationId));

        return $zone;
    }

    public function deleteZone(DnsZone $zone, CommandContext $actor, string $reason): void
    {
        $this->adapter($zone)->deleteZone($zone->name);
        $zone->forceFill(['state' => 'deleted'])->save();
        $zone->delete();
        $this->audit->record($actor->withScope($zone->organization_id), 'dns.zone.delete', 'succeeded', ['zone' => $zone->name, 'reason' => $reason], 'dns_zone', $zone->id);
        $this->outbox->publish(GenericEvent::of('dns.zone.deleted', 'dns_zone', $zone->id, ['name' => $zone->name, 'reason' => $reason], $zone->organization_id));
    }

    // ── staged edits ─────────────────────────────────────────────────────────

    /** @param array<string,mixed> $record */
    public function stageAdd(DnsZone $zone, array $record, CommandContext $actor, ?string $reason = null): DnsChange
    {
        $normalized = $this->validator->normalize($record, $zone->name);
        $this->assertEditable($zone);
        $this->assertRoom($zone, adding: true);

        return DnsChange::query()->create(['zone_id' => $zone->id, 'op' => 'add', 'record' => $normalized, 'requested_by' => $actor->actorId, 'reason' => $reason, 'state' => 'pending']);
    }

    /** @param array<string,mixed> $record */
    public function stageUpdate(DnsZone $zone, DnsRecord $existing, array $record, CommandContext $actor, ?string $reason = null, bool $confirmProtected = false): DnsChange
    {
        $this->assertEditable($zone);
        $this->assertOwn($zone, $existing);
        $this->assertProtected($existing, $confirmProtected);
        $this->assertRoom($zone, adding: false);
        $normalized = $this->validator->normalize(array_merge($existing->normalized(), $record), $zone->name);

        return DnsChange::query()->create(['zone_id' => $zone->id, 'op' => 'update', 'record' => array_merge($normalized, ['id' => $existing->id]), 'previous' => $existing->normalized(), 'requested_by' => $actor->actorId, 'reason' => $reason, 'state' => 'pending']);
    }

    public function stageDelete(DnsZone $zone, DnsRecord $existing, CommandContext $actor, ?string $reason = null, bool $confirmProtected = false): DnsChange
    {
        $this->assertEditable($zone);
        $this->assertOwn($zone, $existing);
        $this->assertProtected($existing, $confirmProtected);

        return DnsChange::query()->create(['zone_id' => $zone->id, 'op' => 'delete', 'record' => array_merge($existing->normalized(), ['id' => $existing->id]), 'previous' => $existing->normalized(), 'requested_by' => $actor->actorId, 'reason' => $reason, 'state' => 'pending']);
    }

    public function discard(DnsZone $zone, CommandContext $actor): int
    {
        $count = DnsChange::query()->where('zone_id', $zone->id)->where('state', 'pending')->update(['state' => 'discarded']);
        $this->audit->record($actor->withScope($zone->organization_id), 'dns.changes.discard', 'succeeded', ['count' => $count], 'dns_zone', $zone->id);

        return $count;
    }

    /** Preview of the resulting record set for the pending change batch. @return array{records:list<array<string,mixed>>, changes:list<array<string,mixed>>} */
    public function preview(DnsZone $zone): array
    {
        $pending = $zone->pendingChanges()->get();
        $records = $this->applyToSet($zone->records()->get()->map(fn (DnsRecord $r) => array_merge($r->normalized(), ['id' => $r->id]))->all(), $pending);

        return ['records' => $records, 'changes' => $pending->map(fn (DnsChange $c) => ['id' => $c->id, 'op' => $c->op, 'record' => $c->record, 'previous' => $c->previous])->all()];
    }

    /**
     * Atomic commit (S39): validate the resulting set, push to the provider in ONE batch, then
     * persist records + a new version. A provider failure leaves the DB untouched and the batch
     * marked `failed` — nothing is half-applied.
     */
    public function commit(DnsZone $zone, CommandContext $actor, ?string $reason = null): DnsZoneVersion
    {
        $this->assertEditable($zone);
        $pending = $zone->pendingChanges()->get();
        if ($pending->isEmpty()) {
            throw new DomainError('dns_nothing_to_commit', 'There are no pending DNS changes.', 409);
        }
        $current = $zone->records()->get()->map(fn (DnsRecord $r) => array_merge($r->normalized(), ['id' => $r->id]))->all();
        $result = $this->applyToSet($current, $pending);
        $this->validator->assertConsistent($result);

        $providerChanges = $pending->map(fn (DnsChange $c) => ['op' => $c->op, 'record' => $c->record, 'previous' => $c->previous])->all();
        try {
            $this->adapter($zone)->applyChanges($zone->name, $providerChanges);
        } catch (ProviderException $e) {
            DnsChange::query()->whereIn('id', $pending->pluck('id'))->update(['state' => 'failed']);
            $this->audit->record($actor->withScope($zone->organization_id), 'dns.commit', 'failed', ['error' => $e->getMessage(), 'code' => $e->errorCode->value], 'dns_zone', $zone->id);
            throw $e;
        }

        return DB::transaction(function () use ($zone, $pending, $result, $actor, $reason) {
            foreach ($pending as $change) {
                match ($change->op) {
                    'add' => DnsRecord::query()->create(array_merge($this->strip($change->record), ['zone_id' => $zone->id, 'managed_by' => ($change->record['managed_by'] ?? 'customer') === 'system' ? 'system' : 'customer', 'comment' => isset($change->record['comment']) ? mb_substr((string) $change->record['comment'], 0, 250) : null])),
                    'update' => DnsRecord::query()->whereKey($change->record['id'] ?? '')->update($this->strip($change->record)),
                    'delete' => DnsRecord::query()->whereKey($change->record['id'] ?? '')->delete(),
                    default => null,
                };
                $change->forceFill(['state' => 'committed', 'committed_at' => now()])->save();
            }
            $zone->forceFill(['serial' => $zone->nextSerial(), 'version' => $zone->version + 1, 'committed_at' => now()])->save();
            $version = $this->snapshot($zone, $actor, $reason ?? 'commit');
            $this->audit->record($actor->withScope($zone->organization_id), 'dns.commit', 'succeeded', ['version' => $version->version, 'changes' => count($pending), 'reason' => $reason], 'dns_zone', $zone->id);
            $this->outbox->publish(GenericEvent::of('dns.zone.committed', 'dns_zone', $zone->id, ['name' => $zone->name, 'version' => $version->version, 'serial' => $zone->serial, 'records' => count($result)], $zone->organization_id));

            return $version;
        });
    }

    /** Stage the diff between the live set and a historical version, then commit it as a new version. */
    public function rollback(DnsZone $zone, int $toVersion, CommandContext $actor): DnsZoneVersion
    {
        $target = DnsZoneVersion::query()->where('zone_id', $zone->id)->where('version', $toVersion)->first();
        if ($target === null) {
            throw new DomainError('dns_version_not_found', "Version {$toVersion} does not exist for {$zone->name}.", 404);
        }
        if ($zone->pendingChanges()->exists()) {
            throw new DomainError('dns_pending_changes', 'Discard or commit pending changes before rolling back.', 409);
        }
        $current = $zone->records()->get();
        $desired = array_map(fn ($r) => $this->strip($r), (array) $target->records);
        $keep = [];
        $staged = 0;
        foreach ($desired as $d) {
            $match = $current->first(fn (DnsRecord $r) => $this->same($r->normalized(), $d));
            if ($match !== null) {
                $keep[$match->id] = true;
            } else {
                DnsChange::query()->create(['zone_id' => $zone->id, 'op' => 'add', 'record' => $d, 'requested_by' => $actor->actorId, 'reason' => "rollback to v{$toVersion}", 'state' => 'pending']);
                $staged++;
            }
        }
        foreach ($current as $r) {
            if (! isset($keep[$r->id])) {
                DnsChange::query()->create(['zone_id' => $zone->id, 'op' => 'delete', 'record' => array_merge($r->normalized(), ['id' => $r->id]), 'previous' => $r->normalized(), 'requested_by' => $actor->actorId, 'reason' => "rollback to v{$toVersion}", 'state' => 'pending']);
                $staged++;
            }
        }
        if ($staged === 0) {
            throw new DomainError('dns_rollback_noop', "The zone already matches version {$toVersion}.", 409);
        }

        return $this->commit($zone, $actor, "rollback to v{$toVersion}");
    }

    /** Replace system-managed records (hosting IP change, mail move) without touching customer rows. @param list<array<string,mixed>> $records */
    /**
     * Make the zone's system records equal to `$records`. With `$owner` (e.g. `service:<id>`) only the system records
     * carrying that owner in their comment are considered — several services share a platform zone without touching
     * each other's rows; an empty `$records` with an owner removes that owner's records.
     */
    public function syncSystemRecords(DnsZone $zone, array $records, CommandContext $actor, string $reason, ?string $owner = null): ?DnsZoneVersion
    {
        $desired = array_map(fn ($r) => $this->validator->normalize($r, $zone->name), $records);
        // What a publisher may remove: its own records, and any record of the very kind it is publishing — a site
        // that moves takes over the parking address, and a record written before publishers had names is replaced
        // rather than doubled. What it never touches is a record of a kind it does not publish: the site's A record
        // is not the mail saga's to delete, nor the domain's MX the website saga's, and each of them used to take
        // the other off the internet.
        $kinds = array_map(fn (array $d) => $d['name'].'|'.$d['type'], $desired);
        $names = array_map(fn (array $d) => $d['name'], $desired);
        $aliased = array_values(array_map(fn (array $d) => $d['name'], array_filter($desired, fn (array $d) => $d['type'] === 'CNAME')));
        $existing = $zone->records()->where('managed_by', 'system')->get()
            ->filter(function (DnsRecord $r) use ($owner, $kinds, $names, $aliased) {
                $row = $r->normalized();

                // and whatever cannot stand beside what is being published: a name is either a CNAME or everything
                // else, so publishing `www A` takes the parked `www CNAME` with it — and the other way round
                return $owner === null || (string) $r->comment === $owner
                    || in_array($row['name'].'|'.$row['type'], $kinds, true)
                    || ($row['type'] === 'CNAME' && in_array($row['name'], $names, true))
                    || in_array($row['name'], $aliased, true);
            })
            ->values();
        foreach ($desired as $d) {
            if ($existing->first(fn (DnsRecord $r) => $this->same($r->normalized(), $d)) === null) {
                $conflict = $zone->records()->where('name', $d['name'])->where('type', $d['type'])->where('managed_by', 'customer')->first();
                if ($conflict !== null) {
                    continue; // the customer overrode this record on purpose; do not fight them
                }
                DnsChange::query()->create(['zone_id' => $zone->id, 'op' => 'add', 'record' => array_merge($d, ['managed_by' => 'system', 'comment' => $owner]), 'requested_by' => $actor->actorId, 'reason' => $reason, 'state' => 'pending']);
            }
        }
        foreach ($existing as $r) {
            $still = array_filter($desired, fn ($d) => $this->same($r->normalized(), $d)) !== [];
            if (! $still) {
                DnsChange::query()->create(['zone_id' => $zone->id, 'op' => 'delete', 'record' => array_merge($r->normalized(), ['id' => $r->id]), 'previous' => $r->normalized(), 'requested_by' => $actor->actorId, 'reason' => $reason, 'state' => 'pending']);
            }
        }

        return $zone->pendingChanges()->exists() ? $this->commit($zone, $actor, $reason) : null;
    }

    public function export(DnsZone $zone): string
    {
        $lines = ["\$ORIGIN {$zone->name}.", '$TTL 3600', sprintf('@ IN SOA %s. hostmaster.%s. %d 10800 3600 604800 3600', rtrim((string) (($zone->nameservers ?? ['ns1.onhost.cz'])[0]), '.'), $zone->name, $zone->serial)];
        foreach ((array) $zone->nameservers as $ns) {
            $lines[] = sprintf('@ 3600 IN NS %s.', rtrim((string) $ns, '.'));
        }
        foreach ($zone->records()->get() as $r) {
            $content = $r->type === 'TXT' ? '"'.str_replace('"', '\"', $r->content).'"' : $r->content;
            $lines[] = sprintf('%s %d IN %s %s%s', $r->name, $r->ttl, $r->type, $r->prio !== null ? $r->prio.' ' : '', $content);
        }

        return implode("\n", $lines)."\n";
    }

    /** @return array{enabled:bool,ds:list<string>} */
    public function enableDnssec(DnsZone $zone, CommandContext $actor): array
    {
        $adapter = $this->adapter($zone);
        $adapter->enableDnssec($zone->name);
        $status = $adapter->dnssecStatus($zone->name);
        $zone->forceFill(['dnssec' => true, 'dnssec_ds' => $status['ds']])->save();
        $this->audit->record($actor->withScope($zone->organization_id), 'dns.dnssec.enable', 'succeeded', ['ds' => $status['ds']], 'dns_zone', $zone->id);
        $this->outbox->publish(GenericEvent::of('dns.dnssec.enabled', 'dns_zone', $zone->id, ['name' => $zone->name, 'ds' => $status['ds'], 'domain_id' => $zone->domain_id], $zone->organization_id));

        return ['enabled' => true, 'ds' => $status['ds']];
    }

    public function disableDnssec(DnsZone $zone, CommandContext $actor): void
    {
        $this->adapter($zone)->disableDnssec($zone->name);
        $zone->forceFill(['dnssec' => false, 'dnssec_ds' => null])->save();
        $this->audit->record($actor->withScope($zone->organization_id), 'dns.dnssec.disable', 'succeeded', [], 'dns_zone', $zone->id);
        $this->outbox->publish(GenericEvent::of('dns.dnssec.disabled', 'dns_zone', $zone->id, ['name' => $zone->name, 'domain_id' => $zone->domain_id], $zone->organization_id));
    }

    /**
     * A zone has a size, and so has its waiting list. Nothing bounded either: one API token could stage records without
     * end — every one a row here, and a commit then pushes the whole zone to the provider (row by row at the WEDOS zone API).
     */
    private function assertRoom(DnsZone $zone, bool $adding): void
    {
        $pending = DnsChange::query()->where('zone_id', $zone->id)->where('state', 'pending')->get(['op']);
        $maxPending = max(1, (int) config('onhost.dns.max_pending_changes', 200));
        if ($pending->count() >= $maxPending) {
            throw new DomainError('dns_pending_limit', "A zone holds at most {$maxPending} changes waiting to be published; publish or discard them first.", 409, ['limit' => $maxPending]);
        }
        if (! $adding) {
            return;
        }
        $max = max(1, (int) config('onhost.dns.max_records_per_zone', 500));
        $after = $zone->records()->count() + $pending->where('op', 'add')->count() - $pending->where('op', 'delete')->count() + 1;
        if ($after > $max) {
            throw new DomainError('dns_record_limit', "A zone holds at most {$max} records.", 409, ['limit' => $max]);
        }
    }

    /**
     * The nightly comparison of what the provider serves with what we hold (`onhost:dns:drift`). `drift()` existed and nobody
     * called it. Zones of the platform's own DNS only: a zone mirrored from a customer's registrar account is edited there
     * too, and its import runs on its own schedule. Oldest comparison first, a batch a night.
     *
     * @return array{checked:int, drifted:int, errors:int}
     */
    public function checkDrift(int $limit = 200): array
    {
        $stats = ['checked' => 0, 'drifted' => 0, 'errors' => 0];
        $platform = ProviderInstance::query()->whereNull('organization_id')->pluck('id')->all();
        $zones = DnsZone::query()->where('state', 'active')->where('kind', 'primary')
            ->where(fn ($q) => $q->whereNull('provider_instance_id')->orWhereIn('provider_instance_id', $platform))
            ->orderByRaw('drift_checked_at is not null')->orderBy('drift_checked_at')->limit(max(1, $limit))->get();
        foreach ($zones as $zone) {
            $gone = false;
            try {
                $changes = $this->withoutOwnTtlSpread($zone, $this->drift($zone));
            } catch (\Throwable $e) {
                // a zone that is not at the provider at all is the largest difference there is — not a comparison that failed
                $gone = $e instanceof ProviderException && in_array($e->errorCode, [ProviderErrorCode::NOT_FOUND, ProviderErrorCode::VALIDATION], true) && $this->goneAtProvider($zone);
                if (! $gone) {
                    $stats['errors']++;
                    $why = $e instanceof ProviderException ? $e->errorCode->value : 'UNEXPECTED';
                    // moves to the end of the queue (a provider that does not answer tonight does not hold the others back); what differed
                    // the last time stays as it was — nobody knows whether it still does
                    $zone->forceFill(['drift_checked_at' => now(), 'drift_error' => $why])->save();
                    Log::warning('dns drift comparison failed', ['zone' => $zone->name, 'provider' => $zone->provider, 'error' => $why]);

                    continue;
                }
                $changes = [];
            }
            $stats['checked']++;
            $had = $zone->getAttribute('drift') !== null;
            $summary = match (true) {
                $gone => ['zone_missing' => true, 'missing_at_provider' => $zone->records()->count(), 'unknown_at_provider' => 0, 'sample' => []],
                $changes === [] => null,
                default => $this->driftSummary($changes),
            };
            $zone->forceFill(['drift_checked_at' => now(), 'drift' => $summary, 'drift_error' => null])->save();
            if ($summary !== null) {
                $stats['drifted']++;
                if (! $had) { // said once when it appears; the doctor keeps showing it until it is gone
                    $this->outbox->publish(GenericEvent::of('dns.drift.detected', 'dns_zone', $zone->id, ['name' => $zone->name, 'provider' => $zone->provider] + $summary, $zone->organization_id));
                }
            }
        }

        return $stats;
    }

    /**
     * Makes the provider serve what the platform holds — the repair after the nightly comparison found a difference. The platform
     * database is the truth (§48): what is missing at the provider is added, what nobody here knows is removed, a zone that is
     * gone is created again. Changes that wait to be published are not part of it. The provider is read once more afterwards:
     * the zone is clean only when nothing is left.
     *
     * @return array{created:bool, added:int, removed:int, updated:int, verified:bool, left:int, dnssec_attention:bool}
     */
    public function republish(DnsZone $zone, CommandContext $actor, ?string $reason = null): array
    {
        $this->assertEditable($zone);
        $adapter = $this->adapter($zone);
        $created = false;
        try {
            if (! $adapter->zoneExists($zone->name)) {
                $adapter->createZone($zone->name, ['nameservers' => $zone->nameservers, 'kind' => 'Native', 'dnssec' => false]);
                $created = true;
            }
            $changes = $this->asUpdates($this->withoutOwnTtlSpread($zone, $this->drift($zone)));
            if ($changes !== []) {
                $adapter->applyChanges($zone->name, $changes);
            }
            $left = $this->withoutOwnTtlSpread($zone, $this->drift($zone));
        } catch (ProviderException $e) {
            $this->audit->record($actor->withScope($zone->organization_id), 'dns.zone.republish', 'failed', ['zone' => $zone->name, 'code' => $e->errorCode->value], 'dns_zone', $zone->id);
            throw $e;
        }
        $count = fn (string $op) => count(array_filter($changes, fn (array $c) => $c['op'] === $op));
        $result = [
            'created' => $created, 'added' => $count('add'), 'removed' => $count('delete'), 'updated' => $count('update'), 'verified' => $left === [], 'left' => count($left),
            // a signed zone that was created again has new keys (or none): the DS at the registry points at keys that are gone
            'dnssec_attention' => $created && (bool) $zone->dnssec,
        ];
        $zone->forceFill(['drift_checked_at' => now(), 'drift_error' => null, 'drift' => $left === [] ? null : $this->driftSummary($left)])->save();
        $this->audit->record($actor->withScope($zone->organization_id), 'dns.zone.republish', 'succeeded', ['zone' => $zone->name, 'reason' => $reason] + $result, 'dns_zone', $zone->id);
        $this->outbox->publish(GenericEvent::of('dns.zone.republished', 'dns_zone', $zone->id, ['name' => $zone->name, 'provider' => $zone->provider] + $result, $zone->organization_id));

        return $result;
    }

    /**
     * A record that is on both sides and differs only in its TTL is ONE update. Sent as a row added and a row removed, PowerDNS
     * would take the record out of its set altogether — it tells records apart by their data, not by their TTL.
     *
     * @param  list<array{op:string,record:array<string,mixed>}>  $changes
     * @return list<array{op:string,record:array<string,mixed>,previous?:array<string,mixed>}>
     */
    private function asUpdates(array $changes): array
    {
        $identity = fn (array $r) => strtolower((string) $r['name']).'|'.strtoupper((string) $r['type']).'|'.rtrim(strtolower((string) $r['content']), '.').'|'.($r['prio'] ?? '');
        $removed = [];
        foreach ($changes as $i => $change) {
            if ($change['op'] === 'delete') {
                $removed[$identity($change['record'])] = $i;
            }
        }
        $out = [];
        $paired = [];
        foreach ($changes as $change) {
            $key = $identity($change['record']);
            if ($change['op'] === 'add' && isset($removed[$key])) {
                $out[] = ['op' => 'update', 'record' => $change['record'], 'previous' => $changes[$removed[$key]]['record']];
                $paired[$removed[$key]] = true;
            } elseif ($change['op'] === 'add') {
                $out[] = $change;
            }
        }
        foreach ($changes as $i => $change) {
            if ($change['op'] === 'delete' && ! isset($paired[$i])) {
                $out[] = $change;
            }
        }

        return $out;
    }

    /**
     * @param  list<array{op:string,record:array<string,mixed>}>  $changes
     * @return array{missing_at_provider:int, unknown_at_provider:int, sample:list<string>}
     */
    private function driftSummary(array $changes): array
    {
        return ['missing_at_provider' => count(array_filter($changes, fn (array $c) => $c['op'] === 'add')), 'unknown_at_provider' => count(array_filter($changes, fn (array $c) => $c['op'] === 'delete')),
            'sample' => array_map(fn (array $c) => ($c['op'] === 'add' ? '− ' : '+ ').($c['record']['name'] ?? '').' '.($c['record']['type'] ?? ''), array_slice($changes, 0, 5))];
    }

    /**
     * A set of records (a name and a type) has ONE TTL on the wire (RFC 2181 §5.2), and PowerDNS keeps one per set. The platform
     * holds a TTL per record, so a customer who adds a second A record with another TTL makes a set the provider cannot
     * represent — that is ours, not a difference at the provider, and it would be reported every night for ever. A record that
     * differs only in its TTL is left out when its own set holds more than one TTL here; a TTL somebody changed at the provider
     * is still a difference.
     *
     * @param  list<array{op:string,record:array<string,mixed>}>  $changes
     * @return list<array{op:string,record:array<string,mixed>}>
     */
    private function withoutOwnTtlSpread(DnsZone $zone, array $changes): array
    {
        if ($changes === []) {
            return [];
        }
        $spread = [];
        foreach ($zone->records()->get(['name', 'type', 'ttl']) as $record) {
            $spread[strtolower((string) $record->name).'|'.strtoupper((string) $record->type)][(int) $record->ttl] = true;
        }
        $identity = fn (array $r) => strtolower((string) $r['name']).'|'.strtoupper((string) $r['type']).'|'.rtrim(strtolower((string) $r['content']), '.').'|'.($r['prio'] ?? '');
        $byIdentity = [];
        foreach ($changes as $i => $change) {
            $byIdentity[$identity($change['record'])][$change['op']][] = $i;
        }
        $drop = [];
        foreach ($byIdentity as $ops) {
            $set = $changes[($ops['add'] ?? $ops['delete'])[0]]['record'];
            if (isset($ops['add'], $ops['delete']) && count($spread[strtolower((string) $set['name']).'|'.strtoupper((string) $set['type'])] ?? []) > 1) {
                $drop = array_merge($drop, $ops['add'], $ops['delete']); // the same record on both sides, only the TTL apart, in a set we hold with several TTLs
            }
        }

        return array_values(array_diff_key($changes, array_flip($drop)));
    }

    /** Asked a second way before anybody is told that a zone is gone: the provider says itself that it does not have it. */
    private function goneAtProvider(DnsZone $zone): bool
    {
        try {
            return ! $this->adapter($zone)->zoneExists($zone->name);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Drift check for the reconciler: provider records vs canonical. @return list<array{op:string,record:array<string,mixed>}> */
    public function drift(DnsZone $zone): array
    {
        $remote = $this->adapter($zone)->listRecords($zone->name);

        return $this->diff($remote, $zone->records()->get()->map(fn (DnsRecord $r) => $r->normalized())->all());
    }

    /** @return list<string> */
    public function nameservers(string $provider = 'powerdns'): array
    {
        return (array) config("onhost.dns.nameservers.{$provider}", config('onhost.dns.nameservers.powerdns', ['ns1.onhost.cz', 'ns2.onhost.cz']));
    }

    // ── internals ────────────────────────────────────────────────────────────

    /**
     * The platform zone a hosting hostname belongs to (`688mr1zw.web.onhost.cz` → zone `onhost.cz`, relative `688mr1zw.web`),
     * or null when the hostname is not under an adopted platform zone.
     *
     * @return array{0:DnsZone,1:string}|null
     */
    public function platformZoneFor(string $hostname): ?array
    {
        $hostname = strtolower(rtrim(trim($hostname), '.'));
        $names = array_map(fn ($z) => strtolower(rtrim((string) $z, '.')), (array) config('onhost.dns.platform_zones', []));
        usort($names, fn ($a, $b) => strlen($b) <=> strlen($a));
        foreach ($names as $name) {
            if ($name === '' || ! str_ends_with($hostname, '.'.$name)) {
                continue;
            }
            $zone = DnsZone::query()->where('name', $name)->where('state', 'active')->first();
            if ($zone !== null) {
                return [$zone, substr($hostname, 0, -strlen($name) - 1)];
            }
        }

        return null;
    }

    /** A/AAAA rows of one hosting hostname in a platform zone, owned by the service (`service:<id>`); empty addresses remove them. */
    public function syncHostname(DnsZone $zone, string $relative, ?string $ipv4, ?string $ipv6, CommandContext $actor, string $owner, string $reason): ?DnsZoneVersion
    {
        $records = [];
        if ($ipv4 !== null && $ipv4 !== '') {
            $records[] = ['name' => $relative, 'type' => 'A', 'content' => $ipv4, 'ttl' => 600];
        }
        if ($ipv6 !== null && $ipv6 !== '') {
            $records[] = ['name' => $relative, 'type' => 'AAAA', 'content' => $ipv6, 'ttl' => 600];
        }

        return $this->syncSystemRecords($zone, $records, $actor, $reason, $owner);
    }

    /**
     * The reverse zone the platform holds for an address, and the label of that address inside it.
     *
     * A PTR lives in a zone the operator was delegated (`2.0.192.in-addr.arpa` for 192.0.2.x, the /64 nibble zone for
     * IPv6). The longest delegation wins, so a /24, a /16 and an RFC 2317 style sub-zone all work as long as the zone
     * row exists here — without one there is nothing to publish into and the caller says so.
     *
     * @return array{0:DnsZone, 1:string}|null
     */
    public function reverseZoneFor(string $ip): ?array
    {
        $labels = self::reverseLabels($ip);
        if ($labels === null) {
            return null;
        }
        [$nibbles, $suffix] = $labels;
        for ($keep = 0; $keep < count($nibbles); $keep++) { // longest delegation first: the zone with the fewest labels left for the record
            $name = implode('.', array_slice($nibbles, $keep)).'.'.$suffix;
            $zone = DnsZone::query()->where('name', $name)->where('state', 'active')->first();
            if ($zone !== null) {
                return [$zone, $keep === 0 ? '@' : implode('.', array_slice($nibbles, 0, $keep))];
            }
        }

        return null;
    }

    /**
     * The PTR of one address, owned by whoever asked for it (`ip:<address>`); a null hostname removes it.
     * Returns null when the platform holds no reverse zone for the address — then nothing was published.
     */
    public function syncPtr(string $ip, ?string $hostname, CommandContext $actor, string $reason): ?DnsZoneVersion
    {
        $found = $this->reverseZoneFor($ip);
        if ($found === null) {
            return null;
        }
        [$zone, $relative] = $found;
        $records = $hostname === null || trim($hostname) === '' ? [] : [['name' => $relative, 'type' => 'PTR', 'content' => $hostname, 'ttl' => 3600]];

        return $this->syncSystemRecords($zone, $records, $actor, $reason, 'ip:'.strtolower($ip));
    }

    /**
     * The nibble/octet labels of an address, most specific first, and the arpa suffix they hang under.
     *
     * @return array{0:list<string>, 1:string}|null
     */
    public static function reverseLabels(string $ip): ?array
    {
        $ip = trim($ip);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return [array_reverse(explode('.', $ip)), 'in-addr.arpa'];
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return null;
        }
        $packed = inet_pton($ip);
        if ($packed === false) {
            return null;
        }

        return [array_reverse(str_split(bin2hex($packed))), 'ip6.arpa'];
    }

    public function adapter(DnsZone $zone): DnsProvider
    {
        $instance = $zone->provider_instance_id ? ProviderInstance::query()->find($zone->provider_instance_id) : null;
        $instance ??= $this->instanceFor($zone->provider);
        if ($instance === null) {
            throw new DomainError('dns_provider_unavailable', "No enabled DNS provider instance for {$zone->provider}.", 503);
        }
        $adapter = $this->providers->forInstance($instance);
        if (! $adapter instanceof DnsProvider) {
            throw new DomainError('dns_provider_invalid', get_class($adapter).' is not a DnsProvider.', 500);
        }

        return $adapter;
    }

    private function instanceFor(string $provider): ?ProviderInstance
    {
        return $this->providers->findInstance($provider, null, 'dns');
    }

    private function snapshot(DnsZone $zone, CommandContext $actor, ?string $reason): DnsZoneVersion
    {
        return DnsZoneVersion::query()->create([
            'zone_id' => $zone->id, 'version' => $zone->version, 'serial' => $zone->serial,
            'records' => $zone->records()->get()->map(fn (DnsRecord $r) => array_merge($r->normalized(), ['managed_by' => $r->managed_by, 'protected' => $r->protected]))->all(),
            'committed_by' => $actor->actorId, 'reason' => $reason, 'committed_at' => now(),
        ]);
    }

    /** @param list<array<string,mixed>> $set */
    private function applyToSet(array $set, iterable $changes): array
    {
        foreach ($changes as $change) {
            $record = $change->record;
            switch ($change->op) {
                case 'add':
                    $set[] = $record;
                    break;
                case 'update':
                    foreach ($set as $i => $r) {
                        if (($r['id'] ?? null) === ($record['id'] ?? '')) {
                            $set[$i] = $record;
                        }
                    }
                    break;
                case 'delete':
                    $set = array_values(array_filter($set, fn ($r) => ($r['id'] ?? null) !== ($record['id'] ?? '')));
                    break;
            }
        }

        return array_values($set);
    }

    /** Provider changes that turn `$remote` into `$desired`. */
    private function diff(array $remote, array $desired): array
    {
        $changes = [];
        foreach ($desired as $d) {
            if (array_filter($remote, fn ($r) => $this->same($r, $d)) === []) {
                $changes[] = ['op' => 'add', 'record' => $this->strip($d)];
            }
        }
        foreach ($remote as $r) {
            if (in_array($r['type'], ['SOA', 'NS'], true) && $r['name'] === '@') {
                continue; // apex SOA/NS belong to the provider zone definition
            }
            if (array_filter($desired, fn ($d) => $this->same($r, $d)) === []) {
                $changes[] = ['op' => 'delete', 'record' => $this->strip($r)];
            }
        }

        return $changes;
    }

    private function same(array $a, array $b): bool
    {
        return strtolower((string) $a['name']) === strtolower((string) $b['name']) && strtoupper((string) $a['type']) === strtoupper((string) $b['type'])
            && rtrim(strtolower((string) $a['content']), '.') === rtrim(strtolower((string) $b['content']), '.') && ($a['prio'] ?? null) === ($b['prio'] ?? null) && (int) ($a['ttl'] ?? 3600) === (int) ($b['ttl'] ?? 3600);
    }

    private function strip(array $record): array
    {
        return ['name' => $record['name'], 'type' => $record['type'], 'content' => $record['content'], 'ttl' => (int) ($record['ttl'] ?? 3600), 'prio' => $record['prio'] ?? null];
    }

    private function assertEditable(DnsZone $zone): void
    {
        if (! in_array($zone->state, ['active', 'error'], true)) {
            throw new DomainError('dns_zone_not_editable', "Zone {$zone->name} is {$zone->state}.", 409);
        }
    }

    private function assertOwn(DnsZone $zone, DnsRecord $record): void
    {
        if ($record->zone_id !== $zone->id) {
            throw new DomainError('dns_record_mismatch', 'Record does not belong to this zone.', 422);
        }
    }

    private function assertProtected(DnsRecord $record, bool $confirmed): void
    {
        if ($record->protected && ! $confirmed) {
            throw new DomainError('dns_protected_record', "{$record->type} {$record->name} is protected (mail/NS/system); confirm the change explicitly.", 409, ['record_id' => $record->id, 'confirm_required' => true]);
        }
    }
}
