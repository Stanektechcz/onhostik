<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflows;

use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Domain\Provisioning\Workflows\Steps\ScheduleNodeStep;
use Onhost\Domain\Services\Models\GameServer;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\GameProvider;
use Onhost\Providers\Contracts\InfrastructureProvider;

/** Game server saga on Pterodactyl (blueprint §14): place → panel user → allocation → create (install) → ACTIVE. */
final class ProvisionGameServerWorkflow implements Workflow
{
    public static function kind(): string
    {
        return 'provision.game';
    }

    public function queue(Operation $operation): string
    {
        return 'provider-pterodactyl';
    }

    public function steps(Operation $operation): array
    {
        return [
            new ScheduleNodeStep('game', 'pterodactyl'),
            new class extends ServiceStep
            {
                public function label(): string
                {
                    return 'Uživatel panelu';
                }

                public function run(StepContext $context): StepResult
                {
                    $service = $this->service($context);
                    $organization = Organization::query()->findOrFail($service->organization_id);
                    $email = (string) ($context->desired('contact_email') ?: $organization->billing_email ?: $organization->owner?->email);
                    if ($email === '') {
                        return StepResult::fail('Organization has no e-mail for the game panel account', false);
                    }
                    $user = $this->capability($context, GameProvider::class)->ensureUser($email, (string) ($organization->name ?: 'ONhost customer'), $organization->id);

                    return StepResult::done(['ptero_user_id' => (int) $user['remote_id'], 'ptero_user_created' => (bool) ($user['created'] ?? false)]);
                }
            },
            new class extends ServiceStep
            {
                public function label(): string
                {
                    return 'Síťová alokace';
                }

                public function run(StepContext $context): StepResult
                {
                    if ($context->get('allocation_id') !== null) {
                        return StepResult::done();
                    }
                    $nodeRemoteId = (int) $context->get('node_remote_id');
                    if ($nodeRemoteId <= 0) {
                        return StepResult::fail('Game node has no Pterodactyl node id (nodes.remote_id)', false);
                    }
                    $free = $this->capability($context, GameProvider::class)->freeAllocations($nodeRemoteId, $context->desired('port') !== null ? (int) $context->desired('port') : null);
                    if ($free === []) {
                        $service = $this->service($context);
                        $context->container->make(OutboxPublisher::class)->publish(GenericEvent::of('capacity.unavailable', 'service', $service->id, ['role' => 'game', 'reason' => 'no free allocation', 'node_remote_id' => $nodeRemoteId], $service->organization_id));

                        return StepResult::fail('No free port allocation on the selected game node', true, [], 900);
                    }
                    $pick = $free[0];

                    return StepResult::done(['allocation_id' => $pick['id'], 'allocation' => $pick]);
                }
            },
            new class extends ServiceStep
            {
                public function label(): string
                {
                    return 'Vytvoření serveru';
                }

                public function run(StepContext $context): StepResult
                {
                    $service = $this->service($context);
                    $infra = $this->capability($context, InfrastructureProvider::class);
                    $eggs = (array) $context->instance()->option('eggs', []);
                    $eggKey = (string) $context->desired('egg', '');
                    $egg = (array) ($eggs[$eggKey] ?? []);
                    $nest = (int) ($context->desired('nest_id') ?? $egg['nest'] ?? 0);
                    $eggId = (int) ($context->desired('egg_id') ?? $egg['egg'] ?? 0);
                    if ($nest <= 0 || $eggId <= 0) {
                        return StepResult::fail("Egg '{$eggKey}' is not mapped on this Pterodactyl instance (options.eggs)", false);
                    }
                    $result = $infra->provision($context->spec('game_server', [
                        'nest_id' => $nest, 'egg_id' => $eggId, 'docker_image' => $context->desired('docker_image', $egg['docker_image'] ?? null), 'startup' => $context->desired('startup', $egg['startup'] ?? null),
                        'environment' => self::withVersion(array_merge((array) ($egg['environment'] ?? []), (array) $context->desired('environment', []))), 'name' => $service->label ?: $service->name,
                        'ptero_user_id' => (int) $context->get('ptero_user_id'), 'allocation_id' => (int) $context->get('allocation_id'), 'entitlements' => (array) $service->entitlements, 'limits' => (array) $context->desired('limits', []),
                    ]));
                    if ($result->ref !== null) {
                        $context->bind($context->instance(), 'server', $result->ref->remoteId, $result->ref->node, $result->ref->meta, ['managed_by' => 'onhost']);
                    }

                    return $this->settle($result, ['server_id' => $result->ref?->remoteId, 'server_meta' => $result->ref?->meta ?? [], 'nest_id' => $nest, 'egg_id' => $eggId, 'already_existed' => $result->alreadyExisted]);
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
                    $ref = $this->ref($context, 'server');
                    $state = $this->capability($context, InfrastructureProvider::class)->getActualState($ref);
                    if (! $state->exists) {
                        return StepResult::fail('Game server vanished after install', true, [], 30);
                    }
                    $meta = (array) $context->get('server_meta', []);
                    $ent = (array) $service->entitlements;
                    GameServer::query()->updateOrCreate(['service_id' => $service->id], [
                        'egg_key' => (string) $context->desired('egg', ''), 'nest_id' => (int) $context->get('nest_id'), 'egg_id' => (int) $context->get('egg_id'), 'ptero_id' => (int) $ref->remoteId, 'ptero_uuid' => $meta['uuid'] ?? null, 'ptero_identifier' => $meta['identifier'] ?? null,
                        'ptero_user_id' => (int) $context->get('ptero_user_id'), 'ptero_node_id' => (int) $context->get('node_remote_id'), 'allocation' => (array) $context->get('allocation', []),
                        'memory_mb' => (int) ($ent['ram_mb'] ?? 0), 'cpu_pct' => (int) data_get($context->desired('limits', []), 'cpu_pct', 0), 'disk_mb' => (int) (($ent['nvme_gb'] ?? 0) * 1024), 'startup' => ['environment' => (array) $context->desired('environment', [])], 'state' => (string) $state->status, 'last_status' => $state->attributes,
                    ]);
                    $allocation = (array) $context->get('allocation', []);
                    $context->container->make(ServiceService::class)->activate($service, $context->actor, $context->operation, ['address' => isset($allocation['ip']) ? ($allocation['alias'] ?? $allocation['ip']).':'.$allocation['port'] : null, 'identifier' => $meta['identifier'] ?? null]);

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
        $binding = $context->binding('server');
        if ($binding !== null && $context->get('already_existed') !== true) {
            try {
                $context->adapter()->terminate($binding->ref());
            } catch (\Throwable) {
            }
        }
        $context->container->make(ServiceService::class)->fail($service, $context->actor, (string) ($context->operation->error['message'] ?? 'provisioning failed'), $context->operation);
    }

    /**
     * `{version}` in a template's environment (the Spigot jar name and download path, §5o) is the game version the order
     * chose (`MINECRAFT_VERSION`), so one preset serves every version.
     *
     * @param  array<string,mixed>  $environment
     * @return array<string,mixed>
     */
    public static function withVersion(array $environment): array
    {
        $version = trim((string) ($environment['MINECRAFT_VERSION'] ?? $environment['VERSION'] ?? ''));
        foreach ($environment as $key => $value) {
            if (is_string($value) && str_contains($value, '{version}')) {
                $environment[$key] = str_replace('{version}', $version !== '' && $version !== 'latest' ? $version : 'latest', $value);
            }
        }

        return $environment;
    }
}
