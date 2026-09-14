<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Dns\DnsService;
use Onhost\Domain\Dns\Models\DnsChange;
use Onhost\Domain\Dns\Models\DnsRecord;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Dns\Models\DnsZoneVersion;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\RegistrarConnection;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Secrets\DbSecretStore;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;
use Onhost\Platform\Support\Hostname;
use Onhost\Providers\Contracts\DnsProvider;
use Onhost\Providers\Contracts\RegistrarProvider;

/**
 * Bring your own registrar (WEDOS API): the customer connects their own account, the platform stores the WAPI password
 * in the secret store (`db://registrar-connections/<id>`), runs the account through two customer-scoped provider
 * instances (registrar + DNS zones) and mirrors its domains into `domains`. From then on the platform automates what
 * the customer otherwise did by hand: expiry notices, credit watch, zone import, pairing a domain with a hosting plan
 * (DomainPairingService) and certificates once the name resolves. Customer instances never serve platform work
 * (`ProviderInstance::scopePlatform`), and nothing here ever deletes a zone or a domain at the registrar.
 */
final class RegistrarConnectionService
{
    public const RUNS_KEPT = 20;

    public const ZONE_CHECKS_PER_RUN = 25;

    public const ZONE_REFRESH_PER_RUN = 10;

    public const NOTICE_DAYS = [30, 14, 7, 3, 1];

    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly SecretStore $secrets,
        private readonly DnsService $dns,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    /** @param array<string,mixed> $input login, password, label?, customer_number?, provider? */
    public function connect(Organization $organization, array $input, CommandContext $context): RegistrarConnection
    {
        $provider = strtolower(trim((string) ($input['provider'] ?? 'wedos')));
        if ($provider !== 'wedos') {
            throw new DomainError('registrar_unsupported', 'Only WEDOS accounts can be connected for now.', 422, ['field' => 'provider']);
        }
        $login = trim((string) ($input['login'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        if ($login === '' || $password === '') {
            throw new DomainError('registrar_credentials_required', 'Enter the WAPI login and the API password.', 422, ['field' => $login === '' ? 'login' : 'password']);
        }
        if (RegistrarConnection::query()->where('organization_id', $organization->id)->where('provider', $provider)->where('login', $login)->where('state', '!=', 'disabled')->exists()) {
            throw new DomainError('registrar_connection_exists', 'This account is already connected.', 409);
        }
        $label = mb_substr(trim((string) ($input['label'] ?? '')) ?: 'WEDOS · '.$login, 0, 120);
        $customerNumber = mb_substr(trim((string) ($input['customer_number'] ?? '')), 0, 40) ?: null;

        $connection = DB::transaction(function () use ($organization, $provider, $login, $password, $label, $customerNumber, $context) {
            $connection = RegistrarConnection::query()->create([
                'organization_id' => $organization->id, 'provider' => $provider, 'label' => $label, 'login' => $login, 'customer_number' => $customerNumber,
                'secret_ref' => 'db://registrar-connections/pending', 'state' => 'pending', 'settings' => RegistrarConnection::DEFAULT_SETTINGS, 'stats' => [], 'runs' => [], 'created_by' => $context->actorId,
            ]);
            $ref = 'db://registrar-connections/'.$connection->id;
            $this->secrets->write(SecretRef::parse($ref), ['login' => $login, 'wapi_password' => $password]);
            $short = strtolower(substr(preg_replace('/[^A-Za-z0-9]/', '', $connection->id) ?? '', -14));
            $common = [
                'organization_id' => $organization->id, 'region_code' => null, 'base_url' => (string) config('onhost.wapi.endpoint'), 'secret_ref' => $ref, 'state' => 'active',
                'options' => array_filter(['customer_connection_id' => $connection->id, 'force_ip_resolve' => config('onhost.wapi.force_ip_resolve')]),
            ];
            $registrar = ProviderInstance::query()->create($common + ['key' => "wedos-c-{$short}", 'provider' => 'wedos', 'name' => 'WEDOS · '.$login, 'capabilities' => ['registrar' => true, 'credit' => true]]);
            $zones = ProviderInstance::query()->create($common + ['key' => "wedos-zone-c-{$short}", 'provider' => 'wedos_zone', 'name' => 'WEDOS DNS · '.$login, 'capabilities' => ['dns' => true]]);
            $connection->forceFill(['secret_ref' => $ref, 'registrar_instance_id' => $registrar->id, 'dns_instance_id' => $zones->id])->save();

            return $connection;
        });

        try {
            $probe = $this->probe($connection, $context);
        } catch (\Throwable $e) {
            $this->discard($connection);
            throw $e instanceof DomainError ? $e : new DomainError('registrar_connection_failed', 'The registrar refused the credentials: '.$this->reason($e), 422, ['field' => 'password']);
        }
        $this->audit->record($context->withScope($organization->id), 'registrar.connection.connect', 'succeeded', ['provider' => $provider, 'login' => $this->mask($login), 'domains' => $probe['domains']], 'registrar_connection', $connection->id);
        $this->outbox->publish(GenericEvent::of('registrar.connection.linked', 'registrar_connection', $connection->id, ['provider' => $provider, 'provider_label' => 'WEDOS', 'label' => $label, 'login' => $this->mask($login), 'domains' => $probe['domains']], $organization->id));
        try {
            $this->sync($connection, $context);
        } catch (DomainError) {
            // the account is connected; the first sync failed and is recorded on the connection (state error, runs), the hourly pass retries
        }

        return $connection->refresh();
    }

    /** Read-only check of the account: the domain list and the credit. @return array{domains:int, credit:array{balance:string,currency:string}} */
    public function probe(RegistrarConnection $connection, ?CommandContext $context = null): array
    {
        $adapter = $this->registrar($connection);
        try {
            $domains = $adapter->listDomains();
            $credit = $adapter->creditInfo();
        } catch (\Throwable $e) {
            $reason = $this->reason($e);
            $connection->forceFill(['state' => 'error', 'last_error' => $reason, 'last_probed_at' => now()])->save();
            $this->run($connection, 'probe', false, [], $reason);
            throw new DomainError('registrar_connection_failed', 'The registrar account did not answer: '.$reason, 422);
        }
        $connection->forceFill(['state' => 'active', 'last_error' => null, 'last_probed_at' => now(), 'stats' => array_merge((array) $connection->stats, ['domains_remote' => count($domains), 'probed_at' => now()->toIso8601String()])])->save();
        $this->run($connection, 'probe', true, ['domains' => count($domains), 'credit' => $credit['balance'].' '.$credit['currency']]);
        $this->creditWatch($connection, $credit);
        if ($context !== null) {
            $this->audit->record($context->withScope($connection->organization_id), 'registrar.connection.probe', 'succeeded', ['domains' => count($domains)], 'registrar_connection', $connection->id);
        }

        return ['domains' => count($domains), 'credit' => $credit];
    }

    /**
     * Mirrors the account: domains (new, changed, vanished), the zones it hosts (imported with their rows), expiry notices
     * for the customer and the credit watch. @return array{imported:int, updated:int, missing:int, skipped:int, zones:int, rows:int, notices:int}
     */
    public function sync(RegistrarConnection $connection, CommandContext $context): array
    {
        if ($connection->state === 'disabled') {
            throw new DomainError('registrar_connection_disabled', 'Reconnect the account first.', 409);
        }
        $summary = ['imported' => 0, 'updated' => 0, 'missing' => 0, 'skipped' => 0, 'zones' => 0, 'rows' => 0, 'notices' => 0];
        try {
            $adapter = $this->registrar($connection);
            $remote = [];
            foreach ($adapter->listDomains() as $row) {
                $name = $this->canonical((string) ($row['name'] ?? ''));
                if ($name !== null) {
                    $remote[$name] = $row;
                }
            }
            $seen = [];
            foreach ($remote as $fqdn => $row) {
                $domain = Domain::withTrashed()->where('fqdn_ascii', $fqdn)->first();
                if ($domain !== null && ($domain->organization_id !== $connection->organization_id || data_get($domain->meta, 'source') !== 'connection')) {
                    $summary['skipped']++; // another customer's domain, or one the platform registered itself

                    continue;
                }
                if ($domain !== null && $domain->trashed()) {
                    $domain->restore(); // mirrored before, removed by a disconnect: the account is back, its zone is adopted afresh below
                    $domain->forceFill(['dns_zone_id' => null, 'dns_provider' => 'external', 'meta' => array_merge((array) $domain->meta, ['dns_checked_at' => null, 'paired_service_id' => null, 'pairing' => null])])->save();
                }
                $expires = ! empty($row['expires_at']) ? new \DateTimeImmutable((string) $row['expires_at']) : null;
                $registered = ! empty($row['registered_at']) ? new \DateTimeImmutable((string) $row['registered_at']) : null;
                $state = DomainStateMachine::fromRegistryStatus((string) ($row['status'] ?? ''), $expires);
                $mark = ['source' => 'connection', 'connection_id' => $connection->id, 'registrar_account' => $connection->login, 'missing_since' => null];
                if ($domain === null) {
                    $domain = Domain::query()->create([
                        'organization_id' => $connection->organization_id, 'fqdn_ascii' => $fqdn, 'fqdn_unicode' => Hostname::unicode($fqdn), 'tld' => Hostname::tld($fqdn),
                        'registrar_provider' => $connection->provider, 'registrar_remote_id' => $fqdn, 'state' => $state, 'registered_at' => $registered, 'expires_at' => $expires,
                        'auto_renew' => false, 'renewal_period' => 1, 'dns_provider' => 'external', 'transfer_lock' => true, 'registry_status' => $row, 'last_reconciled_at' => now(), 'meta' => $mark,
                    ]);
                    $summary['imported']++;
                    $this->outbox->publish(GenericEvent::of('domain.imported', 'domain', $domain->id, ['fqdn' => $fqdn, 'expires_at' => $expires?->format(DATE_ATOM), 'connection_id' => $connection->id], $connection->organization_id));
                } else {
                    $patch = ['registry_status' => $row, 'last_reconciled_at' => now(), 'meta' => array_merge((array) $domain->meta, $mark)];
                    $changed = false;
                    if ($expires !== null && ($domain->expires_at === null || $domain->expires_at->format('Y-m-d') !== $expires->format('Y-m-d'))) {
                        $patch['expires_at'] = $expires;
                        $changed = true;
                    }
                    if ($registered !== null && $domain->registered_at === null) {
                        $patch['registered_at'] = $registered;
                    }
                    if (! in_array($domain->state, [DomainStateMachine::DELETED, DomainStateMachine::TRANSFERRED_OUT, DomainStateMachine::FAILED], true) && $domain->state !== $state) {
                        $patch['state'] = $state;
                        $changed = true;
                    }
                    $domain->forceFill($patch)->save();
                    if ($changed) {
                        $summary['updated']++;
                    }
                }
                $seen[] = $domain->id;
                if ($domain->state === DomainStateMachine::EXPIRED && ! data_get($domain->meta, 'expired_notified')) {
                    $domain->forceFill(['meta' => array_merge((array) $domain->meta, ['expired_notified' => true])])->save();
                    $this->outbox->publish(GenericEvent::of('domain.expired', 'domain', $domain->id, ['fqdn' => $fqdn, 'expires_at' => $expires?->format(DATE_ATOM)], $connection->organization_id));
                }
            }
            foreach ($this->mirrored($connection)->whereNotIn('id', $seen)->get() as $gone) {
                if (data_get($gone->meta, 'missing_since') === null) {
                    $gone->forceFill(['meta' => array_merge((array) $gone->meta, ['missing_since' => now()->toIso8601String()])])->save();
                    $this->outbox->publish(GenericEvent::of('domain.connection.missing', 'domain', $gone->id, ['fqdn' => $gone->fqdn_ascii, 'connection_id' => $connection->id], $connection->organization_id));
                    $summary['missing']++;
                }
            }
            if ($connection->setting('sync_dns')) {
                $summary = $this->syncZones($connection, $summary);
            }
            if ($connection->setting('notices')) {
                $summary['notices'] = $this->notices($connection);
            }
            $credit = null;
            try {
                $credit = $adapter->creditInfo();
            } catch (\Throwable) {
                // the credit is a courtesy; the sync stands without it
            }
            $connection->forceFill([
                'state' => 'active', 'last_error' => null, 'last_synced_at' => now(),
                'stats' => array_merge((array) $connection->stats, ['domains' => count($seen), 'domains_remote' => count($remote), 'zones' => DnsZone::query()->where('provider_instance_id', $connection->dns_instance_id)->count(), 'synced_at' => now()->toIso8601String()]),
            ])->save();
            if ($credit !== null) {
                $this->creditWatch($connection, $credit);
            }
            $this->run($connection, 'sync', true, $summary);
            $this->audit->record($context->withScope($connection->organization_id), 'registrar.connection.sync', 'succeeded', $summary, 'registrar_connection', $connection->id);

            return $summary;
        } catch (DomainError $e) {
            throw $e;
        } catch (\Throwable $e) {
            $reason = $this->reason($e);
            $stats = (array) $connection->stats;
            $connection->forceFill(['state' => 'error', 'last_error' => $reason])->save();
            $this->run($connection, 'sync', false, $summary, $reason);
            $this->audit->record($context->withScope($connection->organization_id), 'registrar.connection.sync', 'failed', ['error' => $reason], 'registrar_connection', $connection->id);
            if (($stats['sync_failed_notified_on'] ?? null) !== now()->toDateString()) {
                $connection->forceFill(['stats' => array_merge($stats, ['sync_failed_notified_on' => now()->toDateString()])])->save();
                $this->outbox->publish(GenericEvent::of('registrar.connection.sync_failed', 'registrar_connection', $connection->id, ['label' => $connection->label, 'error' => $reason], $connection->organization_id));
            }
            throw new DomainError('registrar_sync_failed', 'Synchronisation with the registrar failed: '.$reason, 502);
        }
    }

    /** @param array<string,mixed> $input */
    public function updateSettings(RegistrarConnection $connection, array $input, CommandContext $context): RegistrarConnection
    {
        $settings = array_merge(RegistrarConnection::DEFAULT_SETTINGS, (array) $connection->settings);
        foreach (['auto_sync', 'sync_dns', 'notices'] as $flag) {
            if (array_key_exists($flag, $input)) {
                $settings[$flag] = filter_var($input[$flag], FILTER_VALIDATE_BOOLEAN);
            }
        }
        if (array_key_exists('credit_threshold_minor', $input)) {
            $settings['credit_threshold_minor'] = max(0, min(100000000, (int) $input['credit_threshold_minor']));
        }
        if (array_key_exists('pair_service_id', $input)) {
            $serviceId = $input['pair_service_id'] === null || $input['pair_service_id'] === '' ? null : (string) $input['pair_service_id'];
            if ($serviceId !== null && ! Service::query()->where('organization_id', $connection->organization_id)->whereIn('family', ['web', 'managed'])->where('id', $serviceId)->exists()) {
                throw new DomainError('pairing_service_invalid', 'Choose one of your web hosting services.', 422, ['field' => 'pair_service_id']);
            }
            $settings['pair_service_id'] = $serviceId;
        }
        $patch = ['settings' => $settings];
        if (array_key_exists('label', $input) && trim((string) $input['label']) !== '') {
            $patch['label'] = mb_substr(trim((string) $input['label']), 0, 120);
        }
        $connection->forceFill($patch)->save();
        $this->audit->record($context->withScope($connection->organization_id), 'registrar.connection.settings', 'succeeded', $settings + ['label' => $connection->label], 'registrar_connection', $connection->id);

        return $connection;
    }

    /** Removes the credentials, the customer instances and the mirrored domains and zones (the registrar account itself is untouched). */
    public function disconnect(RegistrarConnection $connection, CommandContext $context): RegistrarConnection
    {
        $removed = 0;
        DB::transaction(function () use ($connection, &$removed) {
            foreach ($this->mirrored($connection)->whereNull('subscription_id')->get() as $domain) {
                if ($domain->dns_zone_id !== null) {
                    $this->dropZone($domain->dns_zone_id);
                }
                $domain->delete();
                $removed++;
            }
            $this->dropInstances($connection);
            if ($this->secrets instanceof DbSecretStore) {
                $this->secrets->delete($connection->secretRef());
            }
            $connection->forceFill(['state' => 'disabled', 'registrar_instance_id' => null, 'dns_instance_id' => null, 'last_error' => null, 'stats' => array_merge((array) $connection->stats, ['disconnected_at' => now()->toIso8601String()])])->save();
        });
        $this->run($connection, 'disconnect', true, ['domains_removed' => $removed]);
        $this->audit->record($context->withScope($connection->organization_id), 'registrar.connection.disconnect', 'succeeded', ['domains_removed' => $removed], 'registrar_connection', $connection->id);
        $this->outbox->publish(GenericEvent::of('registrar.connection.unlinked', 'registrar_connection', $connection->id, ['label' => $connection->label, 'domains_removed' => $removed], $connection->organization_id));

        return $connection;
    }

    /** Staff switch: the instances stop serving (no sync, no pairing) while everything stays in place for a later re-enable. */
    public function setEnabled(RegistrarConnection $connection, bool $enabled, CommandContext $context, ?string $reason = null): RegistrarConnection
    {
        if ($connection->registrar_instance_id === null) {
            throw new DomainError('registrar_connection_disconnected', 'The customer disconnected this account; it cannot be re-enabled.', 409);
        }
        ProviderInstance::query()->whereIn('id', array_filter([$connection->registrar_instance_id, $connection->dns_instance_id]))->update(['state' => $enabled ? 'active' : 'disabled']);
        $connection->forceFill(['state' => $enabled ? 'active' : 'disabled', 'stats' => array_merge((array) $connection->stats, ['staff_disabled' => $enabled ? null : ['at' => now()->toIso8601String(), 'reason' => $reason, 'by' => $context->actorId]])])->save();
        $this->run($connection, $enabled ? 'enable' : 'disable', true, array_filter(['reason' => $reason]));
        $this->audit->record($context->withScope($connection->organization_id), $enabled ? 'registrar.connection.enable' : 'registrar.connection.disable', 'succeeded', array_filter(['reason' => $reason]), 'registrar_connection', $connection->id);

        return $connection;
    }

    /** Domains mirrored from this account. */
    public function mirrored(RegistrarConnection $connection): Builder
    {
        return Domain::query()->where('organization_id', $connection->organization_id)->where('meta->connection_id', $connection->id);
    }

    public function registrar(RegistrarConnection $connection): RegistrarProvider
    {
        $adapter = $this->providers->forInstance($this->instance($connection, $connection->registrar_instance_id));
        if (! $adapter instanceof RegistrarProvider) {
            throw new DomainError('registrar_invalid', get_class($adapter).' is not a RegistrarProvider.', 500);
        }

        return $adapter;
    }

    public function zones(RegistrarConnection $connection): DnsProvider
    {
        $adapter = $this->providers->forInstance($this->instance($connection, $connection->dns_instance_id));
        if (! $adapter instanceof DnsProvider) {
            throw new DomainError('dns_provider_invalid', get_class($adapter).' is not a DnsProvider.', 500);
        }

        return $adapter;
    }

    // ── internals ────────────────────────────────────────────────────────────

    /** @param array{imported:int, updated:int, missing:int, skipped:int, zones:int, rows:int, notices:int} $summary */
    private function syncZones(RegistrarConnection $connection, array $summary): array
    {
        $adapter = $this->zones($connection);
        $candidates = $this->mirrored($connection)->whereNull('dns_zone_id')->whereIn('state', [DomainStateMachine::ACTIVE, DomainStateMachine::EXPIRED, DomainStateMachine::GRACE])->orderBy('fqdn_ascii')->get()
            ->filter(function (Domain $d) {
                $checked = data_get($d->meta, 'dns_checked_at');

                return $checked === null || CarbonImmutable::parse((string) $checked)->lt(CarbonImmutable::now()->subDay());
            })->take(self::ZONE_CHECKS_PER_RUN);
        foreach ($candidates as $domain) {
            $meta = array_merge((array) $domain->meta, ['dns_checked_at' => now()->toIso8601String()]);
            if (! $adapter->zoneExists($domain->fqdn_ascii)) {
                $domain->forceFill(['meta' => $meta])->save();

                continue;
            }
            $zone = DnsZone::withTrashed()->where('name', $domain->fqdn_ascii)->first();
            if ($zone !== null && $zone->organization_id !== $connection->organization_id) {
                $domain->forceFill(['meta' => $meta])->save();

                continue;
            }
            if ($zone !== null && $zone->trashed()) {
                $zone->restore();
            }
            $zone ??= DnsZone::query()->create([
                'organization_id' => $connection->organization_id, 'domain_id' => $domain->id, 'name' => $domain->fqdn_ascii, 'provider' => 'wedos_zone', 'provider_instance_id' => $connection->dns_instance_id,
                'serial' => 0, 'version' => 0, 'state' => 'active', 'kind' => 'primary', 'nameservers' => $this->dns->nameservers('wedos_zone'), 'committed_at' => now(),
            ]);
            $zone->forceFill(['state' => 'active', 'provider' => 'wedos_zone', 'provider_instance_id' => $connection->dns_instance_id, 'domain_id' => $domain->id])->save();
            $summary['rows'] += $this->importRows($zone, $adapter);
            $summary['zones']++;
            $domain->forceFill(['dns_zone_id' => $zone->id, 'dns_provider' => 'wedos_zone', 'meta' => $meta])->save();
        }
        $stale = DnsZone::query()->where('provider_instance_id', $connection->dns_instance_id)->where('state', 'active')
            ->where(fn ($q) => $q->whereNull('last_verified_at')->orWhere('last_verified_at', '<', now()->subHours(6)))->orderBy('last_verified_at')->take(self::ZONE_REFRESH_PER_RUN)->get();
        foreach ($stale as $zone) {
            $summary['rows'] += $this->importRows($zone, $adapter);
        }

        return $summary;
    }

    /** Rows the account already has become customer-managed rows of the mirrored zone; the platform never removes what it did not add. */
    private function importRows(DnsZone $zone, DnsProvider $adapter): int
    {
        $imported = 0;
        foreach ($adapter->listRecords($zone->name) as $r) {
            if (in_array($r['type'], ['SOA', 'NS'], true) && $r['name'] === '@') {
                continue;
            }
            $known = $zone->records()->where('name', $r['name'])->where('type', $r['type'])->get()->first(fn ($x) => rtrim(strtolower((string) $x->content), '.') === rtrim(strtolower((string) $r['content']), '.'));
            if ($known !== null) {
                continue;
            }
            DnsRecord::query()->create(['zone_id' => $zone->id, 'name' => $r['name'], 'type' => $r['type'], 'content' => $r['content'], 'ttl' => (int) ($r['ttl'] ?? 3600), 'prio' => $r['prio'] ?? null, 'managed_by' => 'customer', 'protected' => in_array($r['type'], ['MX', 'NS'], true), 'comment' => 'imported from the connected registrar account']);
            $imported++;
        }
        $zone->forceFill(['last_verified_at' => now()])->save();

        return $imported;
    }

    private function notices(RegistrarConnection $connection): int
    {
        $sent = 0;
        $domains = $this->mirrored($connection)->whereNotNull('expires_at')->where('expires_at', '>', now())->where('expires_at', '<=', now()->addDays(max(self::NOTICE_DAYS)))->get();
        foreach ($domains as $domain) {
            $days = $domain->daysToExpiry();
            if ($days === null) {
                continue;
            }
            $applicable = array_values(array_filter(self::NOTICE_DAYS, fn (int $t) => $days <= $t));
            if ($applicable === []) {
                continue;
            }
            $current = min($applicable);
            $done = (array) data_get($domain->meta, 'notices_sent', []);
            if (isset($done[(string) $current])) {
                continue;
            }
            foreach ($applicable as $t) {
                $done[(string) $t] = now()->toDateString(); // the most urgent notice implies the coarser ones
            }
            $domain->forceFill(['meta' => array_merge((array) $domain->meta, ['notices_sent' => $done])])->save();
            $this->outbox->publish(GenericEvent::of('domain.external_expiry_notice', 'domain', $domain->id, [
                'fqdn' => $domain->fqdn_ascii, 'days' => $current, 'days_left' => $days, 'expires_at' => $domain->expires_at?->toIso8601String(), 'registrar' => 'WEDOS', 'account' => $this->mask($connection->login), 'connection_id' => $connection->id,
            ], $connection->organization_id));
            $sent++;
        }

        return $sent;
    }

    /** @param array{balance:string,currency:string} $credit */
    private function creditWatch(RegistrarConnection $connection, array $credit): void
    {
        $threshold = (int) $connection->setting('credit_threshold_minor', 20000);
        $minor = (int) round(((float) str_replace(',', '.', (string) ($credit['balance'] ?? '0'))) * 100);
        $stats = array_merge((array) $connection->stats, ['credit' => ['balance' => (string) ($credit['balance'] ?? '0'), 'currency' => (string) ($credit['currency'] ?? 'CZK'), 'minor' => $minor, 'at' => now()->toIso8601String()]]);
        if ($threshold > 0 && $minor < $threshold) {
            if (($stats['credit_low_notified_on'] ?? null) !== now()->toDateString()) {
                $stats['credit_low_notified_on'] = now()->toDateString();
                $this->outbox->publish(GenericEvent::of('registrar.connection.credit_low', 'registrar_connection', $connection->id, ['label' => $connection->label, 'login' => $this->mask($connection->login), 'balance' => (string) $credit['balance'], 'currency' => (string) $credit['currency'], 'threshold_minor' => $threshold], $connection->organization_id));
            }
        } else {
            unset($stats['credit_low_notified_on']);
        }
        $connection->forceFill(['stats' => $stats])->save();
    }

    private function run(RegistrarConnection $connection, string $kind, bool $ok, array $summary = [], ?string $error = null): void
    {
        $runs = array_slice(array_merge([['at' => now()->toIso8601String(), 'kind' => $kind, 'ok' => $ok, 'summary' => $summary, 'error' => $error]], (array) $connection->runs), 0, self::RUNS_KEPT);
        $connection->forceFill(['runs' => $runs])->save();
    }

    private function instance(RegistrarConnection $connection, ?string $instanceId): ProviderInstance
    {
        $instance = $instanceId === null ? null : ProviderInstance::query()->find($instanceId);
        if ($instance === null || $instance->state !== 'active' || $connection->state === 'disabled') {
            throw new DomainError('registrar_connection_disabled', 'The connection is disabled.', 409);
        }

        return $instance;
    }

    private function discard(RegistrarConnection $connection): void
    {
        $this->dropInstances($connection);
        if ($this->secrets instanceof DbSecretStore) {
            try {
                $this->secrets->delete($connection->secretRef());
            } catch (\Throwable) {
                // the reference may not have been written yet
            }
        }
        $connection->delete();
    }

    private function dropInstances(RegistrarConnection $connection): void
    {
        foreach (array_filter([$connection->registrar_instance_id, $connection->dns_instance_id]) as $id) {
            $instance = ProviderInstance::query()->find($id);
            if ($instance !== null) {
                $this->providers->forget($instance);
                $instance->delete();
            }
        }
    }

    private function dropZone(string $zoneId): void
    {
        $zone = DnsZone::query()->find($zoneId);
        if ($zone === null) {
            return;
        }
        DnsRecord::query()->where('zone_id', $zone->id)->delete();
        DnsChange::query()->where('zone_id', $zone->id)->delete();
        DnsZoneVersion::query()->where('zone_id', $zone->id)->delete();
        $zone->delete(); // the platform's mirror only: the zone at the registrar stays exactly as it is
    }

    private function canonical(string $name): ?string
    {
        try {
            return Hostname::canonical($name);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function reason(\Throwable $e): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/', ' ', $e->getMessage())), 0, 250);
    }

    private function mask(string $login): string
    {
        return (string) preg_replace('/^(.).*?(@.*)?$/', '$1***$2', $login);
    }
}
