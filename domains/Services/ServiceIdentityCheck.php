<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Errors\DomainError;
use Onhost\Providers\Contracts\InfrastructureProvider;
use Onhost\Providers\Contracts\ResourceRef;
use Throwable;

/**
 * Proof that the resource about to be deleted really is the service being cancelled (audit §5ab). Deleting is the
 * one operation with no undo at the provider, and panel ids overlap between resource types and between panels —
 * the incident that produced this class deleted a stranger's web site because a mail domain had the same number.
 *
 * At least `DeletionPolicy::identityChecks()` (five) independent identifiers must match and **none** may contradict:
 * the service row, its organisation, the binding type expected for the family, the provider instance, the resource
 * existing at the panel, the name/domain the panel reports, the owner (site user, client, panel user) and the fact
 * that no other service in the platform points at the same remote resource.
 */
final class ServiceIdentityCheck
{
    /** Remote types a family may legitimately bind to; anything else is refused outright. */
    private const TYPES = [
        'web' => ['web_domain', 'site'],
        'managed' => ['web_domain', 'site'],
        'mail' => ['mail_domain'],
        'game' => ['server', 'game_server'],
        'cloud' => ['qemu', 'lxc', 'vm', 'server'],
        'data' => ['database', 'db_instance', 'server'],
    ];

    public function __construct(private readonly DeletionPolicy $policy) {}

    /**
     * @return array{ok:bool, required:int, matched:int, failed:list<string>, checks:list<array{key:string,label:string,ok:bool|null,expected:?string,actual:?string,note:?string}>, verified_at:string}
     */
    public function verify(Service $service, ?object $adapter = null, ?ResourceRef $ref = null): array
    {
        $checks = [];
        $binding = $ref === null
            ? $service->primaryBinding()
            : ProviderBinding::query()->where('service_id', $service->id)->where('remote_type', $ref->remoteType)->where('remote_id', $ref->remoteId)->first() ?? $service->primaryBinding();

        $checks[] = self::check('service_record', 'Servisní záznam', $service->exists && $service->id !== '' && $service->deleted_at === null,
            $service->id, $service->id, $service->deleted_at === null ? null : 'the service row is already soft-deleted');

        $checks[] = self::check('organization', 'Organizace', $service->organization_id !== null && $service->organization_id !== '' && ($binding === null || $binding->service_id === $service->id),
            $service->organization_id, $binding === null || $binding->service_id === $service->id ? $service->organization_id : $binding->service_id);

        if ($binding === null) {
            $checks[] = self::check('binding', 'Vazba na panel', false, 'provider binding', null, 'the service has no provider binding');

            return $this->report($checks);
        }

        $expected = self::TYPES[$service->family] ?? null;
        $checks[] = self::check('binding_type', 'Typ zdroje', $expected === null || in_array($binding->remote_type, $expected, true),
            $expected === null ? $binding->remote_type : implode('|', $expected), $binding->remote_type);

        $checks[] = self::check('remote_id', 'Identifikátor v panelu', $binding->remote_id !== null && (string) $binding->remote_id !== '' && ($ref === null || ((string) $ref->remoteId === (string) $binding->remote_id && $ref->remoteType === $binding->remote_type)),
            (string) $binding->remote_id, $ref === null ? (string) $binding->remote_id : (string) $ref->remoteId);

        $instance = ProviderInstance::query()->find($binding->provider_instance_id);
        $checks[] = self::check('provider_instance', 'Instance providera',
            $instance !== null && ($service->provider_instance_id === null || $service->provider_instance_id === $binding->provider_instance_id),
            (string) ($service->provider_instance_id ?? $binding->provider_instance_id), $instance === null ? null : $instance->key,
            $instance === null ? 'the provider instance of the binding no longer exists' : null);

        // no second service may point at the same remote resource — that is the shape the s4s.electree.cz incident had
        $foreign = ProviderBinding::query()->where('provider_instance_id', $binding->provider_instance_id)->where('remote_type', $binding->remote_type)
            ->where('remote_id', $binding->remote_id)->where('service_id', '!=', $service->id)->value('service_id');
        $checks[] = self::check('sole_owner', 'Výhradní vlastník zdroje', $foreign === null, 'only '.$service->id, $foreign === null ? 'only '.$service->id : 'also '.$foreign,
            $foreign === null ? null : 'another service points at the same remote resource');

        $checks[] = self::check('legal_hold', 'Bez právní blokace', ! $service->legal_hold, 'no hold', $service->legal_hold ? 'legal hold' : 'no hold');

        $checks[] = self::check('state', 'Stav služby', in_array($service->state, [
            ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED, ServiceStateMachine::SUSPENDED, ServiceStateMachine::SUSPENDING,
            ServiceStateMachine::TERMINATING, ServiceStateMachine::FAILED,
        ], true), 'deletable state', $service->state);

        [$state, $error] = $this->actual($adapter, $ref ?? $binding->ref());
        if ($error !== null) {
            $checks[] = self::check('remote_exists', 'Zdroj v panelu', null, 'exists', null, $error);
        } else {
            $checks[] = self::check('remote_exists', 'Zdroj v panelu', $state !== null && $state['exists'] === true, 'exists', $state === null ? null : ($state['exists'] ? 'exists' : 'missing'));
        }
        $attributes = (array) ($state['attributes'] ?? []);

        foreach ($this->identifiers($service, $binding, $attributes) as $identifier) {
            $checks[] = $identifier;
        }

        return $this->report($checks);
    }

    /** The same verification, but it throws instead of reporting — used where a caller must simply not continue. */
    public function assert(Service $service, ?object $adapter = null, ?ResourceRef $ref = null): array
    {
        $report = $this->verify($service, $adapter, $ref);
        if (! $report['ok']) {
            throw new DomainError('service_identity_unverified',
                'Službu se nepodařilo jednoznačně ověřit ('.$report['matched'].'/'.$report['required'].' bodů, neshody: '.(implode(', ', $report['failed']) ?: 'žádné').'); nic nebylo smazáno.',
                409, ['identity' => $report]);
        }

        return $report;
    }

    /** Names the panel itself reports, compared with what our records say (domain, site user, panel user, node). */
    private function identifiers(Service $service, ProviderBinding $binding, array $attributes): array
    {
        $meta = (array) $binding->meta;
        $checks = [];

        // the owner of the resource: the site user (ISPConfig), the site folder (aaPanel), the uuid/identifier (game panel)
        $ourOwner = [$meta['system_user'] ?? null, $meta['identifier'] ?? null, $meta['uuid'] ?? null, $meta['path'] ?? null, $meta['document_root'] ?? null];
        $panelOwner = self::first([$attributes['system_user'] ?? null, $attributes['identifier'] ?? null, $attributes['uuid'] ?? null, $attributes['path'] ?? null, $attributes['document_root'] ?? null]);
        $ownerOk = $panelOwner === null || self::first($ourOwner) === null ? null : self::anySame($ourOwner, $panelOwner);
        $checks[] = self::check('owner', 'Vlastník zdroje', $ownerOk, self::first($ourOwner), $panelOwner,
            $ownerOk === null ? 'the panel reports no owner for this resource type' : null);

        // the name the panel shows: for a web or mail service the domain *is* the identity, elsewhere it is a label
        $ourNames = [$meta['domain'] ?? null, $meta['name'] ?? null, $service->spec('domain'), $service->spec('hostname'), $service->hostname, $service->label, $service->name];
        $panelName = self::first([$attributes['domain'] ?? null, $attributes['name'] ?? null]);
        $nameOk = $panelName === null || self::first($ourNames) === null ? null : self::anySame($ourNames, $panelName);
        $note = $nameOk === null ? 'the panel or our record has no name to compare' : null;
        if ($nameOk === false && $ownerOk === true && ! in_array($service->family, ['web', 'managed', 'mail'], true)) {
            $nameOk = null; // a game server renamed in the panel is still the same server — its uuid says so
            $note = 'the panel shows a different name; the resource is identified by its uuid/identifier';
        }
        $checks[] = self::check('name', 'Doména / název', $nameOk, self::first($ourNames), $panelName, $note);

        $ourNode = $binding->remote_node === null || (string) $binding->remote_node === '' ? null : (string) $binding->remote_node;
        $panelNode = isset($attributes['node']) ? (string) $attributes['node'] : null;
        $checks[] = self::check('node', 'Uzel', $ourNode === null || $panelNode === null ? null : self::same($ourNode, $panelNode), $ourNode, $panelNode,
            $ourNode === null || $panelNode === null ? 'the panel does not report the node for this resource type' : null);

        return $checks;
    }

    /** @return array{0:?array{exists:bool,attributes:array<string,mixed>,status:string}, 1:?string} */
    private function actual(?object $adapter, ?ResourceRef $ref): array
    {
        if (! $adapter instanceof InfrastructureProvider || $ref === null) {
            return [null, 'the provider cannot be asked for the resource state'];
        }
        try {
            $state = $adapter->getActualState($ref);

            return [['exists' => $state->exists, 'attributes' => $state->attributes, 'status' => $state->status], null];
        } catch (Throwable $e) {
            return [null, mb_substr($e->getMessage(), 0, 200)];
        }
    }

    /**
     * The verdict. Counting points is not enough: the panel must confirm the resource is there (`remote_exists`) and
     * at least one identifier the panel itself reports — the domain, the site user, the uuid — must match ours.
     * A resource the panel says is gone is reported as `missing`: there is nothing to delete, and the caller may
     * finish the cancellation with a metadata-only archive instead of refusing forever.
     */
    private function report(array $checks): array
    {
        $byKey = [];
        foreach ($checks as $check) {
            $byKey[$check['key']] = $check['ok'];
        }
        $matched = count(array_filter($checks, fn (array $c) => $c['ok'] === true));
        $failed = array_values(array_map(fn (array $c) => $c['key'], array_filter($checks, fn (array $c) => $c['ok'] === false)));
        $required = $this->policy->identityChecks();
        $remote = $byKey['remote_exists'] ?? null;
        $identifier = ($byKey['name'] ?? null) === true || ($byKey['owner'] ?? null) === true;
        $ok = $failed === [] && $matched >= $required && $remote === true && $identifier;

        return ['ok' => $ok, 'required' => $required, 'matched' => $matched, 'failed' => $failed, 'missing' => $remote === false && $failed === ['remote_exists'],
            'identifier_matched' => $identifier, 'checks' => $checks, 'verified_at' => now()->toIso8601String()];
    }

    private static function check(string $key, string $label, ?bool $ok, ?string $expected, ?string $actual, ?string $note = null): array
    {
        return ['key' => $key, 'label' => $label, 'ok' => $ok, 'expected' => $expected === null ? null : mb_substr($expected, 0, 120), 'actual' => $actual === null ? null : mb_substr($actual, 0, 120), 'note' => $note];
    }

    private static function first(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && (string) $candidate !== '') {
                return (string) $candidate;
            }
        }

        return null;
    }

    /** @param array<int,mixed> $ours */
    private static function anySame(array $ours, string $theirs): bool
    {
        foreach ($ours as $candidate) {
            if (is_scalar($candidate) && (string) $candidate !== '' && self::same((string) $candidate, $theirs)) {
                return true;
            }
        }

        return false;
    }

    private static function same(string $a, string $b): bool
    {
        $normalise = static fn (string $v) => rtrim(mb_strtolower(trim($v)), '/.');

        return $normalise($a) === $normalise($b) || str_ends_with($normalise($a), '/'.$normalise($b)) || str_ends_with($normalise($b), '/'.$normalise($a));
    }
}
