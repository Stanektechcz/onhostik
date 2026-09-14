<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflows;

use Onhost\Domain\Dns\DnsService;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Domain\Provisioning\Workflows\Steps\ScheduleNodeStep;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\Website;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\InfrastructureProvider;
use Onhost\Providers\Contracts\WebHostingProvider;

/**
 * Shared (ISPConfig) and managed (aaPanel) web site saga (blueprint §8): place →
 * create site → PHP version → certificate + HTTPS → DNS records on ONhost zones → ACTIVE.
 * The executor is chosen by the product (`desired.executor`), the steps are the same.
 */
final class ProvisionWebsiteWorkflow implements Workflow
{
    public static function kind(): string
    {
        return 'provision.website';
    }

    public function queue(Operation $operation): string
    {
        return 'provider-'.((string) data_get($operation->desired, 'executor', 'ispconfig'));
    }

    public function steps(Operation $operation): array
    {
        $executor = (string) data_get($operation->desired, 'executor', 'ispconfig');

        return [
            new ScheduleNodeStep($executor === 'aapanel' ? 'managed' : 'web', $executor),
            new class extends ServiceStep
            {
                public function label(): string
                {
                    return 'Založení webu';
                }

                public function run(StepContext $context): StepResult
                {
                    $service = $this->service($context);
                    $infra = $this->capability($context, InfrastructureProvider::class);
                    $result = $infra->provision($context->spec('website', ['domain' => $context->desired('domain'), 'php_version' => $context->desired('php_version', '8.3'), 'aliases' => (array) $context->desired('aliases', []), 'entitlements' => (array) $service->entitlements, 'limits' => (array) $context->desired('limits', [])]));
                    if ($result->ref !== null) {
                        $context->bind($context->instance(), $result->ref->remoteType, $result->ref->remoteId, $result->ref->node, $result->ref->meta, ['managed_by' => 'onhost']);
                    }

                    return $this->settle($result, ['site_remote_id' => $result->ref?->remoteId, 'site_remote_type' => $result->ref?->remoteType, 'site_meta' => $result->ref?->meta ?? []]);
                }
            },
            new class extends ServiceStep
            {
                public function label(): string
                {
                    return 'Verze PHP';
                }

                public function run(StepContext $context): StepResult
                {
                    $web = $this->capability($context, WebHostingProvider::class);
                    $ref = $this->ref($context, (string) $context->get('site_remote_type'));
                    if ($context->get('already_existed') === true) {
                        return StepResult::skip();
                    }

                    return $this->settle($web->setPhpVersion($ref, (string) $context->desired('php_version', '8.3')));
                }
            },
            new class extends ServiceStep
            {
                public function label(): string
                {
                    return 'DNS záznamy';
                }

                public function run(StepContext $context): StepResult
                {
                    $service = $this->service($context);
                    $domain = (string) $context->desired('domain');
                    $dns = $context->container->make(DnsService::class);
                    $zone = DnsZone::query()->where('name', $domain)->where('organization_id', $service->organization_id)->where('state', 'active')->first();
                    $platform = $zone === null ? $dns->platformZoneFor($domain) : null; // <label>.web.onhost.cz lives in the platform's own zone
                    if ($zone === null && $platform === null) {
                        return StepResult::skip(); // external DNS: the customer points A/AAAA to the node themselves (shown in the panel)
                    }
                    $node = Node::query()->find($service->node_id);
                    $ipv4 = (string) ($node?->tags['public_ipv4'] ?? $context->instance()->option('public_ipv4', ''));
                    $ipv6 = (string) ($node?->tags['public_ipv6'] ?? $context->instance()->option('public_ipv6', ''));
                    if ($ipv4 === '' && $ipv6 === '') {
                        return StepResult::skip();
                    }
                    if ($platform !== null) {
                        [$platformZone, $relative] = $platform;
                        $version = $dns->syncHostname($platformZone, $relative, $ipv4 ?: null, $ipv6 ?: null, $context->actor, "service:{$service->id}", "web hosting {$service->id}");

                        return StepResult::done(['dns_version' => $version?->version, 'dns_zone' => $platformZone->name, 'public_ipv4' => $ipv4 ?: null, 'public_ipv6' => $ipv6 ?: null]);
                    }
                    $records = [];
                    foreach (['@', 'www'] as $name) {
                        if ($ipv4 !== '') {
                            $records[] = ['name' => $name, 'type' => 'A', 'content' => $ipv4, 'ttl' => 600];
                        }
                        if ($ipv6 !== '') {
                            $records[] = ['name' => $name, 'type' => 'AAAA', 'content' => $ipv6, 'ttl' => 600];
                        }
                    }
                    $version = $dns->syncSystemRecords($zone, $records, $context->actor, "web hosting {$service->id}");

                    return StepResult::done(['dns_version' => $version?->version, 'public_ipv4' => $ipv4 ?: null, 'public_ipv6' => $ipv6 ?: null]);
                }
            },
            new class extends ServiceStep
            {
                public function label(): string
                {
                    return 'Certifikát a HTTPS';
                }

                public function run(StepContext $context): StepResult
                {
                    $web = $this->capability($context, WebHostingProvider::class);
                    $ref = $this->ref($context, (string) $context->get('site_remote_type'));
                    $domain = (string) $context->desired('domain');
                    try {
                        $result = $web->issueCertificate($ref, array_values(array_unique(array_merge([$domain, "www.{$domain}"], (array) $context->desired('aliases', [])))));
                    } catch (ProviderException $e) {
                        if ($e->errorCode === ProviderErrorCode::VALIDATION) {
                            return StepResult::done(['certificate' => 'pending_dns', 'certificate_error' => $e->getMessage()]); // DNS not pointing yet: ACME retried by the panel later, site still works over HTTP
                        }
                        throw $e;
                    }

                    return $this->settle($result, ['certificate' => 'requested']);
                }
            },
            new class extends ServiceStep
            {
                public function label(): string
                {
                    return 'Vynucení HTTPS';
                }

                public function run(StepContext $context): StepResult
                {
                    if ($context->get('certificate') !== 'requested') {
                        return StepResult::skip();
                    }

                    return $this->settle($this->capability($context, WebHostingProvider::class)->forceHttps($this->ref($context, (string) $context->get('site_remote_type')), true), ['https_forced' => true]);
                }
            },
            new class extends ServiceStep
            {
                public function label(): string
                {
                    return 'Aktivace';
                }

                public function run(StepContext $context): StepResult
                {
                    $service = $this->service($context);
                    $infra = $this->capability($context, InfrastructureProvider::class);
                    $ref = $this->ref($context, (string) $context->get('site_remote_type'));
                    $state = $infra->getActualState($ref);
                    if (! $state->exists) {
                        return StepResult::fail('Site is missing at the executor after creation', true, [], 30);
                    }
                    $meta = (array) $context->get('site_meta', []);
                    Website::query()->updateOrCreate(['service_id' => $service->id], [
                        'domain' => (string) $context->desired('domain'), 'aliases' => (array) $context->desired('aliases', []), 'executor' => (string) $context->desired('executor', 'ispconfig'), 'php_version' => (string) $context->desired('php_version', '8.3'),
                        'docroot' => $meta['document_root'] ?? $meta['path'] ?? null, 'ssl_state' => $context->get('certificate') === 'requested' ? 'issued' : 'pending', 'https_forced' => (bool) $context->get('https_forced', false),
                        'remote_client_id' => isset($meta['client_id']) ? (int) $meta['client_id'] : null, 'remote_site_id' => (int) $ref->remoteId, 'remote_node' => $ref->node, 'system_user' => $meta['system_user'] ?? null, 'state' => 'active',
                        'quota' => ['nvme_gb' => $service->entitlement('nvme_gb'), 'php_workers' => $service->entitlement('php_workers'), 'databases' => $service->entitlement('databases')],
                    ]);
                    $context->container->make(ServiceService::class)->activate($service, $context->actor, $context->operation, ['domain' => $context->desired('domain'), 'public_ipv4' => $context->get('public_ipv4'), 'public_ipv6' => $context->get('public_ipv6'), 'certificate' => $context->get('certificate')]);

                    return StepResult::done(['activated' => true]);
                }
            },
        ];
    }

    public function compensate(StepContext $context): void
    {
        $service = $context->service ?? Service::query()->find($context->operation->service_id);
        if ($service === null) {
            return;
        }
        $binding = $context->binding();
        if ($binding !== null && $context->get('already_existed') !== true) {
            try {
                $context->adapter()->terminate($binding->ref());
            } catch (\Throwable) {
                // orphan reported by the reconciler
            }
        }
        $context->container->make(ServiceService::class)->fail($service, $context->actor, (string) ($context->operation->error['message'] ?? 'provisioning failed'), $context->operation);
    }
}
