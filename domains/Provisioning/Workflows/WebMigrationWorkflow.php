<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflows;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Onhost\Domain\Dns\DnsService;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Scheduling\NodeScheduler;
use Onhost\Domain\Provisioning\ServiceMigrationService;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\Website;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\Web\NodeAddresses;
use Onhost\Domain\Services\Web\WebMigration;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\InfrastructureProvider;
use Onhost\Providers\Contracts\ResourceRef;
use Throwable;

/**
 * Moves a web hosting to another node, so a shared web server can be emptied and retired.
 *
 * Until this saga a web service could not be moved at all: an evacuation walked past it and the only way out was a
 * backup, a new order and a customer copying their own site across. A shared node therefore could not be drained —
 * the one thing an operator must be able to do before they touch the hardware.
 *
 * The order is the order of a safe move: the target node is chosen, the site is asked whether it **can** be carried
 * (a database whose password the platform does not hold would arrive with a new one and the site's own
 * `wp-config.php` would open nothing), a fresh archive is made of the files and every database, the site is created
 * on the target, the data is put down there with each database keeping its name, user and password — and only then
 * is anything switched. The customer stays on the source until the copy is whole; a failure before the switch takes
 * the half-built target back (`CompensationGuard` asks the panel whether that really is the copy this run made) and
 * leaves the customer where they were. The old site is removed last, and its archive is kept.
 */
final class WebMigrationWorkflow implements Workflow
{
    public const TARGET_BINDING = 'web_migration';

    public static function kind(): string
    {
        return 'web.migrate';
    }

    public function queue(Operation $operation): string
    {
        return 'provider-'.((string) data_get($operation->desired, 'executor', 'ispconfig'));
    }

    public function steps(Operation $operation): array
    {
        return [$this->targetStep(), $this->readinessStep(), $this->archiveStep(), $this->createStep(), $this->dataStep(), $this->switchStep(), $this->certificateStep(), $this->cleanupStep()];
    }

    /** The site the customer is served from now, from the facts recorded at the start (the binding is rewritten at the switch). */
    public static function sourceRef(StepContext $context): ResourceRef
    {
        return new ResourceRef(
            (string) $context->get('source_remote_type', 'web_domain'),
            (string) $context->get('source_remote_id'),
            (string) $context->get('source_node_remote_id'),
            (array) $context->get('source_meta', []),
            (string) $context->service?->id,
        );
    }

    /** The panel the new site lives on: the source panel unless the target node belongs to another one. */
    public static function targetAdapter(StepContext $context): object
    {
        $id = (string) $context->get('target_instance_id', '');

        return $id !== '' ? $context->adapter($id) : $context->adapter();
    }

    public static function targetBinding(string $serviceId): ?ProviderBinding
    {
        return ProviderBinding::query()->where('service_id', $serviceId)->where('remote_type', self::TARGET_BINDING)->first();
    }

    public function compensate(StepContext $context): void
    {
        $service = $context->service ?? Service::query()->find($context->operation->service_id);
        if ($service === null || $context->get('swapped') === true) {
            return; // the customer is already served from the new node; only the old site is left, and the failure names it
        }
        $target = self::targetBinding($service->id);
        if ($target !== null) {
            // taken back only once the panel confirms it is the site this run made (CompensationGuard); kept otherwise, on record
            $context->container->make(CompensationGuard::class)->takeBack($context, $service, self::TARGET_BINDING, self::targetAdapter($context), $target);
            $target->delete();
        }
        ServiceMigrationService::markSchedule($service->fresh() ?? $service, 'failed', ['error' => mb_substr((string) ($context->operation->error['message'] ?? 'migration failed'), 0, 300), 'operation_id' => $context->operation->id]);
        $context->container->make(OutboxPublisher::class)->publish(GenericEvent::of('service.migration.failed', 'service', $service->id, [
            'label' => $service->label ?: $service->name, 'hostname' => $service->hostname, 'family' => $service->family,
            'reason' => mb_substr((string) ($context->operation->error['message'] ?? ''), 0, 300),
        ], $service->organization_id));
    }

    /** Which node the site moves to, and which one it is on now. */
    private function targetStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Cílový uzel';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $source = $this->binding($context);
                $sourceProvider = (string) (ProviderInstance::query()->find((string) $service->provider_instance_id)?->provider ?: '');
                $sourceNode = $service->node_id ? Node::query()->find($service->node_id) : null;
                $executor = (string) $service->spec('executor', 'ispconfig');
                $wanted = trim((string) $context->desired('target_node_id', ''));
                if ($wanted !== '') {
                    $target = Node::query()->with('providerInstance')->where(fn ($q) => $q->whereKey($wanted)->orWhere('name', $wanted))->first();
                    if ($target === null) {
                        return StepResult::fail("Uzel {$wanted} neexistuje", false);
                    }
                } else {
                    try {
                        $pick = $context->container->make(NodeScheduler::class)->pick(array_filter([
                            'role' => $executor === 'aapanel' ? 'managed' : 'web', 'provider' => $executor, 'region' => $service->region_code,
                            'disk_gb' => (int) data_get($service->entitlements, 'nvme_gb', 0),
                            'exclude_nodes' => array_values(array_filter([$service->node_id])),
                        ], fn ($value) => $value !== null && $value !== [] && $value !== ''));
                    } catch (DomainError $e) {
                        return StepResult::fail($e->getMessage(), true, $e->extra, 900);
                    }
                    $target = $pick['node']->loadMissing('providerInstance');
                }
                if ($target->id === $service->node_id) {
                    return StepResult::fail('Web už na cílovém uzlu běží', false);
                }
                if ($target->state !== 'active') {
                    return StepResult::fail("Uzel {$target->name} není aktivní", false);
                }
                $instance = $target->providerInstance;
                if ($instance === null || $instance->state !== 'active' || $instance->provider !== ($sourceProvider ?: $executor)) {
                    return StepResult::fail("Panel uzlu {$target->name} není aktivní panel stejného druhu", false);
                }

                return StepResult::done([
                    'source_binding_id' => $source->id, 'source_remote_id' => $source->remote_id, 'source_remote_type' => $source->remote_type,
                    'source_node_remote_id' => $source->remote_node, 'source_meta' => (array) $source->meta, 'source_node_id' => $service->node_id,
                    'source_node_name' => $sourceNode?->name, 'source_instance_id' => $service->provider_instance_id,
                    'target_node_id' => $target->id, 'target_node_name' => $target->name, 'target_instance_id' => $instance->id, 'node_name' => $target->name,
                ]);
            }
        };
    }

    /** Whether this site can be carried at all — asked before anything is made or copied. */
    private function readinessStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Co se dá přenést';
            }

            public function run(StepContext $context): StepResult
            {
                $readiness = $context->container->make(WebMigration::class)->readiness($this->service($context));
                if (! $readiness['ok']) {
                    return StepResult::fail('Web zatím přestěhovat nejde: '.implode('; ', $readiness['blockers']), false, ['blockers' => $readiness['blockers']]);
                }

                // only what an operator may read: the names and the numbers, never the passwords — those are read
                // again from the secret store at the moment each database is made on the target
                return StepResult::done(['databases' => array_map(fn (array $database) => ['remote_id' => $database['remote_id'], 'name' => $database['name']], $readiness['databases'])]);
            }
        };
    }

    /** Files and every database, fresh or the step fails: the copy the move is made from, and the way back. */
    private function archiveStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Záloha před stěhováním';
            }

            public function run(StepContext $context): StepResult
            {
                $archive = $context->container->make(FinalArchive::class)->create(
                    $this->service($context), $context->adapter(), $this->ref($context), $context->actor, $context->operation->id, null,
                    ['kind' => 'migration', 'fresh_only' => true, 'protected' => true, 'reason' => 'migration', 'retention_days' => 30],
                );

                return StepResult::done(['set' => $archive['set'] ?? null, 'backup_id' => $archive['backup']->id ?? null, 'archive_gaps' => $archive['gaps'] ?? []]);
            }
        };
    }

    /** The same site on the target node, bound beside the running one until the switch. */
    private function createStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Web na cílovém uzlu';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                if (WebMigrationWorkflow::targetBinding($service->id) !== null) {
                    return StepResult::skip(); // the run was picked up again; the site is already there
                }
                $adapter = WebMigrationWorkflow::targetAdapter($context);
                if (! $adapter instanceof InfrastructureProvider) {
                    return StepResult::fail('Cílový panel neumí založit web', false);
                }
                $result = $adapter->provision($context->spec('website', [
                    'domain' => (string) $service->spec('domain', $service->hostname), 'php_version' => (string) $service->spec('php_version', '8.3'),
                    'entitlements' => (array) $service->entitlements, 'aliases' => (array) $service->spec('aliases', []), 'migration_of' => $service->id,
                ]));
                if ($result->ref === null) {
                    return StepResult::fail('Cílový panel nevrátil číslo nového webu', true, [], 120);
                }
                $instance = $context->instance(((string) $context->get('target_instance_id', '')) ?: null);
                $context->bind($instance, WebMigrationWorkflow::TARGET_BINDING, $result->ref->remoteId, $result->ref->node, $result->ref->meta, ['managed_by' => 'onhost']);

                return $this->settle($result, ['target_remote_id' => $result->ref->remoteId, 'target_meta' => $result->ref->meta]);
            }
        };
    }

    /** The archive put down on the target: every database with its own name, user and password, then the files. */
    private function dataStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Přenos dat';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $set = (string) $context->get('set', '');
                $target = WebMigrationWorkflow::targetBinding($service->id);
                if ($set === '' || $target === null) {
                    return StepResult::fail('Chybí záloha nebo web na cílovém uzlu; nic se nepřenášelo', false);
                }
                $migration = $context->container->make(WebMigration::class);
                $readiness = $migration->readiness($service); // the passwords are read here, at the last moment
                if (! $readiness['ok']) {
                    return StepResult::fail('Web zatím přestěhovat nejde: '.implode('; ', $readiness['blockers']), false, ['blockers' => $readiness['blockers']]);
                }
                $work = storage_path('app/onhost-migration-'.Str::random(8));
                if (! is_dir($work) && ! mkdir($work, 0700, true) && ! is_dir($work)) {
                    return StepResult::fail('pracovní adresář pro přenos nelze vytvořit', true, [], 60);
                }
                try {
                    $moved = $migration->restoreInto($service, $context->container->make(FinalArchive::class)->disk(), $set, WebMigrationWorkflow::targetAdapter($context), $target->ref(), $readiness['databases'], $work);
                } catch (DomainError $e) {
                    return StepResult::fail($e->getMessage(), false, $e->extra);
                } finally {
                    foreach (glob($work.'/*') ?: [] as $left) {
                        @unlink($left);
                    }
                    @rmdir($work);
                }

                return StepResult::done(['moved_files' => $moved['files'], 'moved_databases' => array_column($moved['databases'], 'name')]);
            }
        };
    }

    /** The platform starts serving the customer from the new node: the binding, the node, the site row and DNS. */
    private function switchStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Přepnutí na nový uzel';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $source = ProviderBinding::query()->find((string) $context->get('source_binding_id'));
                $target = WebMigrationWorkflow::targetBinding($service->id);
                if ($source === null || $target === null) {
                    return StepResult::fail('Chybí zdrojová nebo cílová vazba; nic se nepřepínalo', false);
                }
                $targetInstanceId = (string) ($context->get('target_instance_id') ?: $service->provider_instance_id);
                $meta = (array) $target->meta;
                DB::transaction(function () use ($service, $source, $target, $context, $meta, $targetInstanceId) {
                    $source->forceFill(['provider_instance_id' => $targetInstanceId, 'remote_id' => $target->remote_id, 'remote_node' => $target->remote_node, 'meta' => $meta])->save();
                    $target->delete();
                    $tags = (array) $service->tags;
                    $tags['migration'] = array_replace((array) ($tags['migration'] ?? []), [
                        'state' => 'finished', 'finished_at' => now()->toIso8601String(),
                        'to_node' => $context->get('target_node_name'), 'from_node' => $context->get('source_node_name'),
                    ]);
                    $service->forceFill(['node_id' => (string) $context->get('target_node_id'), 'provider_instance_id' => $targetInstanceId, 'tags' => $tags])->save();
                    Website::query()->where('service_id', $service->id)->update(array_filter([
                        'remote_site_id' => is_numeric($target->remote_id) ? (int) $target->remote_id : null,
                        'remote_node' => $target->remote_node, 'system_user' => $meta['system_user'] ?? null, 'docroot' => $meta['document_root'] ?? null,
                        'remote_client_id' => isset($meta['client_id']) ? (int) $meta['client_id'] : null,
                    ], fn ($value) => $value !== null));
                });
                $fresh = $service->fresh() ?? $service;
                $dns = WebMigrationWorkflow::publishAddresses($context, $fresh);
                $context->container->make(AuditRecorder::class)->record($context->actor->withScope($service->organization_id), 'service.migrated', 'succeeded', [
                    'from_node' => $context->get('source_node_name'), 'to_node' => $context->get('target_node_name'), 'family' => $service->family,
                ], 'service', $service->id);
                $context->container->make(OutboxPublisher::class)->publish(GenericEvent::of('service.migrated', 'service', $service->id, [
                    'label' => $service->label ?: $service->name, 'hostname' => $service->hostname, 'family' => $service->family,
                    'from' => $context->get('source_node_name'), 'to' => $context->get('target_node_name'), 'dns' => $dns['state'],
                ], $service->organization_id));
                ServiceMigrationService::markSchedule($fresh, 'finished', ['to_node' => $context->get('target_node_name'), 'operation_id' => $context->operation->id]);

                return StepResult::done(['swapped' => true] + $dns);
            }
        };
    }

    /**
     * The address of the new node, published where the platform runs the zone itself. A domain whose DNS is somewhere
     * else is not ours to change: the records are written down, the daily check tells the customer (`PublicDnsCheck`)
     * and the certificate waits for them by itself (`DomainPointing`).
     *
     * @return array{state:string, dns_version:?int, dns_records_required:?list<array<string,mixed>>}
     */
    public static function publishAddresses(StepContext $context, Service $service): array
    {
        $domain = mb_strtolower((string) $service->spec('domain', $service->hostname));
        $addresses = ['A' => NodeAddresses::ipv4($service)[0] ?? '', 'AAAA' => NodeAddresses::ipv6($service)[0] ?? ''];
        $records = [];
        foreach (['@', 'www'] as $name) {
            foreach ($addresses as $type => $address) {
                if ($address !== '') {
                    $records[] = ['name' => $name, 'type' => $type, 'content' => $address, 'ttl' => 600];
                }
            }
        }
        if ($records === []) {
            return ['state' => 'unknown', 'dns_version' => null, 'dns_records_required' => null];
        }
        $zone = $domain === '' ? null : DnsZone::query()->where('name', $domain)->where('organization_id', $service->organization_id)->where('state', 'active')->first();
        if ($zone === null) {
            return ['state' => 'customer', 'dns_version' => null, 'dns_records_required' => $records];
        }
        try {
            $version = $context->container->make(DnsService::class)->syncSystemRecords($zone, $records, $context->actor, 'stěhování webu '.$service->id, 'web:'.$service->id);
        } catch (Throwable $e) {
            return ['state' => 'failed: '.mb_substr($e->getMessage(), 0, 120), 'dns_version' => null, 'dns_records_required' => $records];
        }

        return ['state' => 'published', 'dns_version' => $version?->version, 'dns_records_required' => null];
    }

    /** A new node means a new address, so the certificate is asked for again — it waits for DNS on its own. */
    private function certificateStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Certifikát na novém uzlu';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context)->fresh();
                if ($service === null) {
                    return StepResult::skip();
                }
                try {
                    $context->container->make(ServiceService::class)->requestAction($service, 'ssl.issue', $context->actor, 'migration-cert:'.$context->operation->id, []);
                } catch (Throwable $e) {
                    // the site is served from the new node either way; the daily check tells the customer about DNS
                    return StepResult::done(['certificate' => 'later: '.mb_substr($e->getMessage(), 0, 160)]);
                }

                return StepResult::done(['certificate' => 'requested']);
            }
        };
    }

    /** The site on the old node, once the customer is served from the new one. Its archive stays. */
    private function cleanupStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Úklid na starém uzlu';
            }

            public function run(StepContext $context): StepResult
            {
                if ($context->get('swapped') !== true) {
                    return StepResult::skip(); // nothing was switched, so nothing of the customer's is old
                }
                $sourceInstanceId = (string) $context->get('source_instance_id', '');
                $adapter = $sourceInstanceId !== '' ? $context->adapter($sourceInstanceId) : $context->adapter();
                if (! $adapter instanceof InfrastructureProvider) {
                    return StepResult::done(['source_removed' => false]);
                }
                try {
                    $adapter->terminate(WebMigrationWorkflow::sourceRef($context));
                } catch (Throwable $e) {
                    // the customer already runs on the new node: a site left behind is an operator's job, not a failure
                    return StepResult::done(['source_removed' => false, 'source_error' => mb_substr($e->getMessage(), 0, 200)]);
                }

                return StepResult::done(['source_removed' => true]);
            }
        };
    }
}
