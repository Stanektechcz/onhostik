<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains;

use Onhost\Domain\Dns\DnsService;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\Web\NodeAddresses;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\WebHostingProvider;

/**
 * Pairs a domain with a web hosting plan: the site learns the domain and its www variant (WebHostingProvider), the
 * DNS rows point at the node — written straight into the zone when the platform manages it (a connected registrar
 * account or an ONhost zone), otherwise handed to the customer as instructions — and the certificate follows on its
 * own once the name resolves (CertificateAutoIssuer reads `extra_domains` and the `pending_dns` tag).
 */
final class DomainPairingService
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly DnsService $dns,
        private readonly ServiceFeatures $features,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    /** @return array{domain_id:string, service_id:string, hostname:string, dns:string, records:list<array<string,mixed>>, aliases:list<string>} */
    public function pair(Domain $domain, Service $service, CommandContext $context): array
    {
        if ($domain->organization_id !== $service->organization_id) {
            throw DomainError::notFound('service');
        }
        if (! in_array($service->family, ['web', 'managed'], true) || ! in_array($service->state, [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED], true)) {
            throw new DomainError('pairing_service_invalid', 'Pair domains with an active web hosting service.', 409);
        }
        $paired = data_get($domain->meta, 'paired_service_id');
        if ($paired !== null && $paired !== $service->id) {
            throw new DomainError('domain_already_paired', 'The domain is paired with another service; unpair it first.', 409, ['service_id' => $paired]);
        }
        $ips = NodeAddresses::ipv4($service);
        if ($ips === []) {
            throw new DomainError('node_address_unknown', 'The node has no public address recorded yet; ask support.', 409);
        }
        [$adapter, $ref] = $this->site($service);
        $fqdn = $domain->fqdn_ascii;
        $aliases = [];
        foreach ([$fqdn, 'www.'.$fqdn] as $name) {
            try {
                $adapter->addSubdomain($ref, ['domain' => $name]);
                $aliases[] = $name;
            } catch (ProviderException $e) {
                throw new DomainError('pairing_alias_failed', "The web server refused {$name}: ".mb_substr($e->getMessage(), 0, 200), 502, ['domain' => $name]);
            }
        }

        $records = [['name' => '@', 'type' => 'A', 'content' => $ips[0], 'ttl' => 300], ['name' => 'www', 'type' => 'A', 'content' => $ips[0], 'ttl' => 300]];
        foreach (NodeAddresses::ipv6($service) as $ipv6) {
            $records[] = ['name' => '@', 'type' => 'AAAA', 'content' => $ipv6, 'ttl' => 300];
            $records[] = ['name' => 'www', 'type' => 'AAAA', 'content' => $ipv6, 'ttl' => 300];
            break;
        }
        $mode = 'manual';
        $zone = $domain->dns_zone_id ? DnsZone::query()->find($domain->dns_zone_id) : null;
        if ($zone !== null && $zone->organization_id === $domain->organization_id && $zone->state === 'active') {
            $reason = "pairing with {$service->hostname}";
            $old = $zone->records()->whereIn('name', ['@', 'www'])->whereIn('type', ['A', 'AAAA', 'CNAME'])->where('managed_by', 'customer')->get();
            foreach ($old as $record) {
                $this->dns->stageDelete($zone, $record, $context, $reason, true); // the customer asked for the site to answer here: the old addresses go
            }
            if ($old->isNotEmpty()) {
                $this->dns->commit($zone, $context, $reason);
            }
            $this->dns->syncSystemRecords($zone, $records, $context, $reason, 'service:'.$service->id);
            $mode = 'synced';
        }

        $domain->forceFill(['meta' => array_merge((array) $domain->meta, ['paired_service_id' => $service->id, 'paired_at' => now()->toIso8601String(), 'pairing' => ['dns' => $mode, 'records' => $records, 'aliases' => $aliases]])])->save();
        $extra = array_values(array_unique(array_merge((array) data_get($service->desired_spec, 'extra_domains', []), [$fqdn])));
        $service->forceFill([
            'desired_spec' => array_merge((array) $service->desired_spec, ['extra_domains' => $extra]),
            'tags' => array_replace_recursive((array) $service->tags, ['access' => ['certificate' => 'pending_dns']]), // the auto-issuer requests the certificate once the name resolves
        ])->save();
        $this->features->forget($service);
        $this->audit->record($context->withScope($service->organization_id, $service->project_id), 'domain.pair', 'succeeded', ['fqdn' => $fqdn, 'service_id' => $service->id, 'dns' => $mode], 'domain', $domain->id);
        $this->outbox->publish(GenericEvent::of('domain.paired', 'domain', $domain->id, ['fqdn' => $fqdn, 'service_id' => $service->id, 'hostname' => $service->hostname, 'dns' => $mode, 'records' => $records], $domain->organization_id));

        return ['domain_id' => $domain->id, 'service_id' => $service->id, 'hostname' => (string) $service->hostname, 'dns' => $mode, 'records' => $records, 'aliases' => $aliases];
    }

    /** @return array{domain_id:string, service_id:string, aliases_removed:int, dns:string} */
    public function unpair(Domain $domain, CommandContext $context): array
    {
        $serviceId = data_get($domain->meta, 'paired_service_id');
        if (! is_string($serviceId) || $serviceId === '') {
            throw new DomainError('domain_not_paired', 'The domain is not paired with a service.', 409);
        }
        $service = Service::query()->where('organization_id', $domain->organization_id)->find($serviceId);
        $fqdn = $domain->fqdn_ascii;
        $removed = 0;
        if ($service !== null && $service->primaryBinding() !== null && $service->provider_instance_id !== null) {
            try {
                [$adapter, $ref] = $this->site($service);
                foreach ($adapter->listSubdomains($ref) as $row) {
                    if (in_array(strtolower((string) ($row['domain'] ?? '')), [$fqdn, 'www.'.$fqdn], true)) {
                        $adapter->removeSubdomain($ref, (string) $row['remote_id']);
                        $removed++;
                    }
                }
            } catch (ProviderException $e) {
                if ($e->errorCode !== ProviderErrorCode::NOT_FOUND) {
                    throw new DomainError('pairing_alias_failed', 'The web server refused to drop the domain: '.mb_substr($e->getMessage(), 0, 200), 502);
                }
            }
        }
        $mode = 'kept';
        $zone = $domain->dns_zone_id ? DnsZone::query()->find($domain->dns_zone_id) : null;
        if ($zone !== null && $zone->organization_id === $domain->organization_id && $zone->state === 'active') {
            $this->dns->syncSystemRecords($zone, [], $context, 'unpairing from '.($service?->hostname ?? $serviceId), 'service:'.$serviceId); // drops the rows the pairing added, nothing else
            $mode = 'removed';
        }
        $meta = (array) $domain->meta;
        unset($meta['paired_service_id'], $meta['paired_at'], $meta['pairing']);
        $domain->forceFill(['meta' => $meta])->save();
        if ($service !== null) {
            $extra = array_values(array_diff((array) data_get($service->desired_spec, 'extra_domains', []), [$fqdn]));
            $service->forceFill(['desired_spec' => array_merge((array) $service->desired_spec, ['extra_domains' => $extra])])->save();
            $this->features->forget($service);
        }
        $this->audit->record($context->withScope($domain->organization_id), 'domain.unpair', 'succeeded', ['fqdn' => $fqdn, 'service_id' => $serviceId, 'aliases_removed' => $removed], 'domain', $domain->id);
        $this->outbox->publish(GenericEvent::of('domain.unpaired', 'domain', $domain->id, ['fqdn' => $fqdn, 'service_id' => $serviceId, 'hostname' => $service?->hostname], $domain->organization_id));

        return ['domain_id' => $domain->id, 'service_id' => $serviceId, 'aliases_removed' => $removed, 'dns' => $mode];
    }

    /** @return array{0: WebHostingProvider, 1: ResourceRef} */
    private function site(Service $service): array
    {
        $binding = $service->primaryBinding();
        $instance = $service->provider_instance_id ? ProviderInstance::query()->find($service->provider_instance_id) : null;
        if ($binding === null || $instance === null) {
            throw new DomainError('service_not_provisioned', 'The service has no web site on a node yet.', 409);
        }
        $adapter = $this->providers->forInstance($instance);
        if (! $adapter instanceof WebHostingProvider) {
            throw new DomainError('pairing_unsupported', 'This service cannot take extra domains.', 422);
        }

        return [$adapter, $binding->ref()];
    }
}
