<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflows;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Jobs\TransferGameArchive;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Scheduling\NodeScheduler;
use Onhost\Domain\Provisioning\ServiceMigrationService;
use Onhost\Domain\Provisioning\Workflow\AbstractStep;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Domain\Services\Models\GameServer;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\AsyncHandle;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\BackupCapable;
use Onhost\Providers\Contracts\GameProvider;
use Onhost\Providers\Contracts\GameToolsProvider;
use Onhost\Providers\Contracts\InfrastructureProvider;
use Onhost\Providers\Contracts\PowerCapable;
use Onhost\Providers\Contracts\ProviderAdapter;
use Onhost\Providers\Contracts\ResourceRef;
use Throwable;

/**
 * Moves a game server to another node — of the same panel or of another one (audit §5g-2, §5h-2) — as one saga:
 * pick the target → stop the source → back it up → allocation on the target → the same server created there
 * (template, image, variables, limits, owner; on another panel the catalogue template mapped there and the
 * customer's panel account ensured) → the archive streamed daemon to daemon by a queued job → the platform switched
 * to the new server (binding, game server row, panel, node, access address) and the new server started → the old
 * one deleted. Nothing is deleted before the switch; a failure before it deletes the half-built target and starts
 * the source again, so the customer is back where they were. Staff start it per server or per node (evacuation),
 * optionally inside a window the customer may move (§5h-3); the customer gets the new address as a notification.
 */
final class GameMigrationWorkflow implements Workflow
{
    public const TARGET_BINDING = 'server_migration';

    public static function kind(): string
    {
        return 'game.migrate';
    }

    public function queue(Operation $operation): string
    {
        return 'provider-pterodactyl';
    }

    public function steps(Operation $operation): array
    {
        // collaborators are read before anything is touched and carried before anything is switched (H341)
        return [$this->targetStep(), $this->collaboratorsReadStep(), $this->stopSourceStep(), $this->backupStep(), $this->allocationStep(), $this->createStep(), $this->transferStep(), $this->collaboratorsCarryStep(), $this->switchStep(), $this->cleanupStep()];
    }

    /** The server the customer runs on now, from the facts recorded at the start (the binding is rewritten at the switch). */
    public static function sourceRef(StepContext $context): ResourceRef
    {
        return new ResourceRef('server', (string) $context->get('source_server_id'), (string) $context->get('source_node_remote_id'), (array) $context->get('source_meta', []), (string) $context->operation->service_id);
    }

    /** The panel the new server lives on: the source panel unless the target node belongs to another one. */
    public static function targetAdapter(StepContext $context): ProviderAdapter
    {
        $id = (string) $context->get('target_instance_id', '');

        return $id !== '' ? $context->adapter($id) : $context->adapter();
    }

    public static function targetBinding(string $serviceId): ?ProviderBinding
    {
        return ProviderBinding::query()->where('service_id', $serviceId)->where('remote_type', self::TARGET_BINDING)->first();
    }

    /** Polls an asynchronous handle on a given adapter (the target panel's, where the default step polling would ask the source). */
    public static function pollOn(ProviderAdapter $adapter, AsyncHandle $handle, callable $onSuccess): StepResult
    {
        if (! method_exists($adapter, 'awaitStatus')) {
            return StepResult::done();
        }
        try {
            $status = $adapter->awaitStatus($handle);
        } catch (ProviderException $e) {
            return AbstractStep::fromProviderException($e);
        }

        return match ($status->state) {
            AsyncStatus::SUCCEEDED => $onSuccess($status),
            AsyncStatus::FAILED => StepResult::fail($status->message ?? 'provider task failed', false, $status->detail),
            AsyncStatus::UNKNOWN => StepResult::fail($status->message ?? 'provider task state unknown', true, $status->detail),
            default => StepResult::wait($handle),
        };
    }

    public function compensate(StepContext $context): void
    {
        $service = $context->service ?? Service::query()->find($context->operation->service_id);
        if ($service === null || $context->get('swapped') === true) {
            return; // the customer already runs on the new node; only the old copy is left, and the failure names it
        }
        $target = self::targetBinding($service->id);
        if ($target !== null) {
            // the copy this migration built is taken back only once the panel confirms it is that copy (CompensationGuard); kept otherwise, on record
            $context->container->make(CompensationGuard::class)->takeBack($context, $service, self::TARGET_BINDING, self::targetAdapter($context), $target);
            $target->delete();
        }
        if ($context->get('source_stopped') === true) {
            try {
                $adapter = $context->adapter();
                if ($adapter instanceof PowerCapable) {
                    $adapter->power(self::sourceRef($context), 'start');
                }
            } catch (Throwable) {
            }
        }
        ServiceMigrationService::markSchedule($service->fresh() ?? $service, 'failed', ['error' => mb_substr((string) ($context->operation->error['message'] ?? 'migration failed'), 0, 200)]);
        $context->container->make(OutboxPublisher::class)->publish(GenericEvent::of('service.migration.failed', 'service', $service->id, ['label' => $service->label ?: $service->name, 'error' => (string) ($context->operation->error['message'] ?? 'migration failed'), 'source_node' => $context->get('source_node_name'), 'target_node' => $context->get('target_node_name')], $service->organization_id));
    }

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
                $source = $this->binding($context, 'server');
                $sourceNode = $service->node_id ? Node::query()->find($service->node_id) : null;
                $wanted = trim((string) $context->desired('target_node_id', ''));
                if ($wanted !== '') {
                    $candidates = Node::query()->with('providerInstance')->where(fn ($q) => $q->whereKey($wanted)->orWhere('name', $wanted))->get()->filter(fn (Node $n) => $n->providerInstance?->provider === 'pterodactyl');
                    $target = $candidates->firstWhere('provider_instance_id', $service->provider_instance_id) ?? $candidates->first();
                    if ($target === null) {
                        return StepResult::fail("Target node {$wanted} does not exist on any game panel", false);
                    }
                } else {
                    $ent = (array) $service->entitlements;
                    try {
                        $pick = $context->container->make(NodeScheduler::class)->pick(array_filter([
                            'role' => 'game', 'provider' => 'pterodactyl', 'region' => $service->region_code, 'ram_mb' => (int) ($ent['ram_mb'] ?? 0), 'disk_gb' => (int) ($ent['nvme_gb'] ?? 0),
                            'exclude_nodes' => array_values(array_filter([$service->node_id])), 'sandbox' => NodeScheduler::sandboxFor($service->organization_id),
                        ], fn ($v) => $v !== null && $v !== [] && $v !== ''));
                    } catch (DomainError $e) {
                        return StepResult::fail($e->getMessage(), true, $e->extra, 900);
                    }
                    $target = $pick['node']->loadMissing('providerInstance');
                }
                if ($target->id === $service->node_id) {
                    return StepResult::fail('The server already runs on the target node', false);
                }
                if ($target->role !== 'game' || $target->state !== 'active') {
                    return StepResult::fail("Target node {$target->name} is not an active game node", false);
                }
                if ((string) $target->remote_id === '') {
                    return StepResult::fail("Target node {$target->name} has no panel node id (nodes.remote_id)", false);
                }
                $targetInstance = $target->providerInstance;
                if ($targetInstance === null || $targetInstance->state !== 'active') {
                    return StepResult::fail("The game panel of node {$target->name} is not active", false);
                }
                $cross = $targetInstance->id !== $service->provider_instance_id;
                $game = GameServer::query()->where('service_id', $service->id)->first();
                $user = (int) (($source->meta['user_id'] ?? 0) ?: ($game?->ptero_user_id ?? 0));
                $template = null;
                if ($cross) { // another panel: the customer's account there and the catalogue template mapped there
                    $organization = Organization::query()->find($service->organization_id);
                    $email = (string) ($organization?->billing_email ?: $organization?->owner?->email ?: '');
                    if ($email === '') {
                        return StepResult::fail('Organization has no e-mail for the game panel account', false);
                    }
                    $adapter = $context->adapter($targetInstance->id);
                    if (! $adapter instanceof GameProvider) {
                        return StepResult::fail("Panel {$targetInstance->key} is not a game panel", false);
                    }
                    $user = (int) $adapter->ensureUser($email, (string) ($organization?->name ?: 'ONhost customer'), (string) $service->organization_id)['remote_id'];
                    $eggKey = (string) data_get($service->desired_spec, 'egg', '');
                    $mapped = (array) ($targetInstance->option('eggs', [])[$eggKey] ?? []);
                    if ($eggKey === '' || empty($mapped['nest']) || empty($mapped['egg'])) {
                        return StepResult::fail("Template '{$eggKey}' is not mapped on the target panel {$targetInstance->key} (Šablony her → Namapovat)", false);
                    }
                    $template = ['nest' => (int) $mapped['nest'], 'egg' => (int) $mapped['egg']];
                }

                return StepResult::done([
                    'source_node_id' => $service->node_id, 'source_node_name' => $sourceNode?->name, 'source_server_id' => $source->remote_id, 'source_node_remote_id' => $source->remote_node, 'source_meta' => (array) $source->meta, 'source_binding_id' => $source->id, 'source_instance_id' => $service->provider_instance_id,
                    'target_node_id' => $target->id, 'target_node_name' => $target->name, 'target_node_remote_id' => (string) $target->remote_id, 'target_instance_id' => $targetInstance->id, 'target_instance_key' => $targetInstance->key, 'cross_panel' => $cross,
                    'ptero_user_id' => $user, 'target_template' => $template,
                ]);
            }
        };
    }

    /** Stops the source so nothing changes between the backup and the switch; waits (up to four minutes) for it to be offline, then kills it. */
    private function stopSourceStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Zastavení zdroje';
            }

            public function run(StepContext $context): StepResult
            {
                $ref = GameMigrationWorkflow::sourceRef($context);
                $this->capability($context, PowerCapable::class)->power($ref, 'stop');

                return StepResult::wait(new AsyncHandle('onhost_game_offline', $ref->remoteId, $ref->node, ['identifier' => $ref->meta['identifier'] ?? null], 5, 600), ['source_stopped' => true, 'stop_requested_at' => now()->toIso8601String()]);
            }

            public function poll(StepContext $context, AsyncHandle $handle): StepResult
            {
                $ref = GameMigrationWorkflow::sourceRef($context);
                $state = (string) ($this->capability($context, GameToolsProvider::class)->status($ref)['state'] ?? 'unknown');
                if ($state === 'offline') {
                    return StepResult::done(['source_state' => 'offline']);
                }
                $since = $context->get('stop_requested_at') ? now()->diffInSeconds(CarbonImmutable::parse((string) $context->get('stop_requested_at')), true) : 0;
                if ($since >= 240) {
                    $this->capability($context, PowerCapable::class)->power($ref, 'kill');

                    return StepResult::done(['source_state' => 'killed']);
                }

                return StepResult::wait($handle);
            }
        };
    }

    private function backupStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Záloha zdroje';
            }

            public function run(StepContext $context): StepResult
            {
                $result = $this->capability($context, BackupCapable::class)->backup(GameMigrationWorkflow::sourceRef($context), ['name' => 'onhost-migration-'.substr($context->operation->id, -8)]);

                return $this->settle($result, ['backup_uuid' => $result->data['backup_uuid'] ?? null]);
            }

            protected function afterAsyncSuccess(StepContext $context, AsyncStatus $status): StepResult
            {
                return StepResult::done(['backup_uuid' => (string) ($status->detail['uuid'] ?? $context->get('backup_uuid')), 'backup_bytes' => $status->detail['bytes'] ?? null]);
            }
        };
    }

    private function allocationStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Síťová alokace na cíli';
            }

            public function run(StepContext $context): StepResult
            {
                if ($context->get('target_allocation_id') !== null) {
                    return StepResult::done();
                }
                $adapter = GameMigrationWorkflow::targetAdapter($context);
                if (! $adapter instanceof GameProvider) {
                    return StepResult::fail('The target panel is not a game panel', false);
                }
                $free = $adapter->freeAllocations((int) $context->get('target_node_remote_id'));
                if ($free === []) {
                    $service = $this->service($context);
                    $context->container->make(OutboxPublisher::class)->publish(GenericEvent::of('capacity.unavailable', 'service', $service->id, ['role' => 'game', 'reason' => 'no free allocation', 'node_remote_id' => (int) $context->get('target_node_remote_id')], $service->organization_id));

                    return StepResult::fail('No free port allocation on the target game node', true, [], 900);
                }

                return StepResult::done(['target_allocation_id' => $free[0]['id'], 'target_allocation' => $free[0]]);
            }
        };
    }

    private function createStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Nový server na cíli';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $definition = $this->capability($context, GameToolsProvider::class)->serverDefinition(GameMigrationWorkflow::sourceRef($context));
                $template = (array) ($context->get('target_template') ?? []);
                $nest = (int) ($template['nest'] ?? $definition['nest']);
                $egg = (int) ($template['egg'] ?? $definition['egg']);
                if ($nest <= 0 || $egg <= 0) {
                    return StepResult::fail('The source server reports no template (nest/egg); it cannot be recreated', false);
                }
                $adapter = GameMigrationWorkflow::targetAdapter($context);
                if (! $adapter instanceof InfrastructureProvider) {
                    return StepResult::fail('The target panel cannot create servers', false);
                }
                $result = $adapter->provision($context->spec('game_server', [
                    'nest_id' => $nest, 'egg_id' => $egg, 'docker_image' => $definition['docker_image'] ?: null, 'startup' => $definition['startup'] ?: null,
                    'environment' => $definition['environment'], 'name' => $definition['name'] ?: ($service->label ?: $service->name),
                    'ptero_user_id' => (int) ($context->get('ptero_user_id') ?: $definition['user']), 'allocation_id' => (int) $context->get('target_allocation_id'),
                    'entitlements' => (array) $service->entitlements, 'limits' => ['cpu_pct' => (int) ($definition['limits']['cpu'] ?? 0)],
                ]));
                if ($result->ref !== null) {
                    $context->bind(ProviderInstance::query()->findOrFail((string) $context->get('target_instance_id')), GameMigrationWorkflow::TARGET_BINDING, $result->ref->remoteId, $result->ref->node, $result->ref->meta, ['managed_by' => 'onhost']);
                }

                return $this->settle($result, ['target_server_id' => $result->ref?->remoteId, 'target_meta' => $result->ref?->meta ?? [], 'nest_id' => $nest, 'egg_id' => $egg, 'definition' => ['docker_image' => $definition['docker_image'], 'startup' => $definition['startup'], 'cpu_pct' => (int) ($definition['limits']['cpu'] ?? 0)]]);
            }

            public function poll(StepContext $context, AsyncHandle $handle): StepResult
            {
                return GameMigrationWorkflow::pollOn(GameMigrationWorkflow::targetAdapter($context), $handle, fn (AsyncStatus $status) => StepResult::done(['last_task' => $status->detail]));
            }
        };
    }

    /** The data half runs in a queued job (hours are fine there); the step only starts it and polls its record. */
    private function transferStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Přenos dat';
            }

            public function run(StepContext $context): StepResult
            {
                $target = GameMigrationWorkflow::targetBinding($this->service($context)->id);
                if ($target === null) {
                    return StepResult::fail('The target server binding is missing', false);
                }
                try {
                    $adapter = GameMigrationWorkflow::targetAdapter($context);
                    if ($adapter instanceof PowerCapable) {
                        $adapter->power($target->ref(), 'stop'); // the fresh install may have started; the import lands on a quiet container
                    }
                } catch (Throwable) {
                }
                $cache = $context->container->make(CacheRepository::class);
                $cache->forget(TransferGameArchive::cacheKey($context->operation->id));
                TransferGameArchive::dispatch($context->operation->id)->onQueue('provider-pterodactyl');

                return StepResult::wait(new AsyncHandle('onhost_transfer', $context->operation->id, null, [], 15, 6 * 3600), ['transfer_started_at' => now()->toIso8601String()]);
            }

            public function poll(StepContext $context, AsyncHandle $handle): StepResult
            {
                $row = $context->container->make(CacheRepository::class)->get(TransferGameArchive::cacheKey($context->operation->id));
                if (! is_array($row)) {
                    return StepResult::wait($handle);
                }
                if (($row['state'] ?? '') === 'done') {
                    return StepResult::done(['transfer' => ['bytes' => $row['bytes'] ?? null, 'at' => $row['at'] ?? null]]);
                }

                return StepResult::fail('Data transfer failed: '.(string) ($row['error'] ?? 'unknown'), false);
            }
        };
    }

    /** The platform now points at the new server; the customer keeps the service, gets the new address, the game starts. */
    /**
     * Who may do what on the server, as the customer approved it (Brain card H341). The new server is a new resource
     * at the panel and starts with no collaborators; what the old one has is read here — before the source is stopped,
     * so a panel that cannot be read costs the customer nothing.
     */
    private function collaboratorsReadStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Spolupracovníci serveru';
            }

            public function run(StepContext $context): StepResult
            {
                $adapter = $context->adapter();
                if (! $adapter instanceof GameToolsProvider) {
                    return StepResult::done(['collaborators' => [], 'collaborators_state' => 'unavailable']);
                }
                try {
                    $rows = $adapter->listSubusers(GameMigrationWorkflow::sourceRef($context));
                } catch (ProviderException $e) {
                    return StepResult::fail('The collaborators of the server cannot be read, so they could not be carried over: '.mb_substr($e->getMessage(), 0, 200), $e->errorCode->isRetryable());
                }
                $collaborators = [];
                foreach ($rows as $row) {
                    $email = mb_strtolower(trim((string) ($row['email'] ?? '')));
                    if ($email !== '') {
                        $collaborators[] = ['email' => $email, 'permissions' => GameMigrationWorkflow::permissionSet((array) ($row['permissions'] ?? []))];
                    }
                }

                return StepResult::done(['collaborators' => $collaborators, 'collaborators_state' => 'read']);
            }
        };
    }

    /**
     * The same people with the same rights on the new server, or no switch (H341). Each collaborator is created with the
     * exact permission list and read back: a panel that gives more, or less, than was approved is a blocker — the
     * account is removed from the target and the migration stops before the customer is moved. `collaborator_policy:
     * drop` is the explicit exception: the server moves without the ones that cannot be carried, and they are named.
     */
    private function collaboratorsCarryStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Přenos spolupracovníků';
            }

            public function run(StepContext $context): StepResult
            {
                $wanted = (array) $context->get('collaborators', []);
                if ($wanted === []) {
                    return StepResult::done(['collaborators_carried' => 0, 'collaborators_dropped' => []]);
                }
                $binding = GameMigrationWorkflow::targetBinding($this->service($context)->id);
                $adapter = GameMigrationWorkflow::targetAdapter($context);
                $drop = (string) $context->desired('collaborator_policy', 'strict') === 'drop';
                $carried = 0;
                $dropped = [];
                foreach ($wanted as $collaborator) {
                    $email = (string) $collaborator['email'];
                    $permissions = (array) $collaborator['permissions'];
                    try {
                        $problem = $binding === null || ! $adapter instanceof GameToolsProvider ? 'the target panel offers no collaborator accounts' : GameMigrationWorkflow::carry($adapter, $binding->ref(), $email, $permissions);
                    } catch (ProviderException $e) { // a panel that is briefly away is no verdict on the permissions: try the step again
                        return StepResult::fail('The target panel did not answer while collaborators were carried over: '.mb_substr($e->getMessage(), 0, 200), true);
                    }
                    if ($problem === null) {
                        $carried++;

                        continue;
                    }
                    if (! $drop) {
                        return StepResult::fail("A collaborator cannot be carried to the new server with the same permissions ({$problem}); nothing was switched. Start the migration with collaborator_policy=drop to move the server without them.", false, ['collaborator' => $email, 'collaborators_carried' => $carried]);
                    }
                    $dropped[] = ['email' => $email, 'reason' => $problem];
                }

                return StepResult::done(['collaborators_carried' => $carried, 'collaborators_dropped' => $dropped]);
            }
        };
    }

    /**
     * One collaborator on the target with exactly these permissions; null = done, otherwise why not. Whatever was
     * created and turned out different is removed again: a wrong grant is never left behind.
     *
     * @param  list<string>  $permissions
     *
     * @throws ProviderException when the panel is only briefly away (the step is repeated)
     */
    public static function carry(GameToolsProvider $adapter, ResourceRef $target, string $email, array $permissions): ?string
    {
        $find = function () use ($adapter, $target, $email): ?array {
            foreach ($adapter->listSubusers($target) as $row) {
                if (mb_strtolower((string) ($row['email'] ?? '')) === $email) {
                    return $row;
                }
            }

            return null;
        };
        try {
            $existing = $find(); // a repeated step: the account may be there already
            if ($existing !== null && self::permissionSet($existing['permissions']) === $permissions) {
                return null;
            }
            if ($existing !== null) {
                $adapter->deleteSubuser($target, (string) $existing['remote_id']);
            }
            $adapter->createSubuser($target, $email, $permissions);
            $created = $find();
            $got = $created === null ? null : self::permissionSet($created['permissions']);
            if ($got === $permissions) {
                return null;
            }
            if ($created !== null) {
                $adapter->deleteSubuser($target, (string) $created['remote_id']);
            }
            $extra = $got === null ? [] : array_values(array_diff($got, $permissions));
            $missing = $got === null ? $permissions : array_values(array_diff($permissions, $got));

            return $got === null ? 'the target panel did not create the account' : 'the target panel gave different permissions'.($extra !== [] ? ', more: '.implode(' ', $extra) : '').($missing !== [] ? ', fewer: '.implode(' ', $missing) : '');
        } catch (ProviderException $e) {
            if ($e->errorCode->isRetryable()) {
                throw $e;
            }

            return mb_substr($e->getMessage(), 0, 160);
        }
    }

    /**
     * @param  array<int|string, mixed>  $permissions
     * @return list<string>
     */
    public static function permissionSet(array $permissions): array
    {
        $set = array_values(array_unique(array_map('strval', $permissions)));
        sort($set);

        return $set;
    }

    private function switchStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Přepnutí na nový server';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $source = ProviderBinding::query()->find((string) $context->get('source_binding_id'));
                $target = GameMigrationWorkflow::targetBinding($service->id);
                if ($source === null || $target === null) {
                    return StepResult::fail('The source or target binding is missing; nothing was switched', false);
                }
                $allocation = (array) $context->get('target_allocation', []);
                $address = isset($allocation['ip']) ? ($allocation['alias'] ?? $allocation['ip']).':'.$allocation['port'] : null;
                $meta = (array) $target->meta;
                $ent = (array) $service->entitlements;
                $targetInstanceId = (string) ($context->get('target_instance_id') ?: $service->provider_instance_id);
                DB::transaction(function () use ($service, $source, $target, $context, $allocation, $address, $meta, $ent, $targetInstanceId) {
                    $source->forceFill(['provider_instance_id' => $targetInstanceId, 'remote_id' => $target->remote_id, 'remote_node' => $target->remote_node, 'meta' => $meta])->save();
                    $target->delete();
                    GameServer::query()->updateOrCreate(['service_id' => $service->id], [
                        'egg_key' => (string) data_get($service->desired_spec, 'egg', ''), 'nest_id' => (int) $context->get('nest_id'), 'egg_id' => (int) $context->get('egg_id'),
                        'ptero_id' => (int) $source->remote_id, 'ptero_uuid' => $meta['uuid'] ?? null, 'ptero_identifier' => $meta['identifier'] ?? null, 'ptero_user_id' => (int) ($meta['user_id'] ?? $context->get('ptero_user_id')),
                        'ptero_node_id' => (int) $context->get('target_node_remote_id'), 'allocation' => $allocation,
                        'memory_mb' => (int) ($ent['ram_mb'] ?? 0), 'cpu_pct' => (int) data_get($context->get('definition', []), 'cpu_pct', 0), 'disk_mb' => (int) (($ent['nvme_gb'] ?? 0) * 1024),
                    ]);
                    $tags = (array) $service->tags;
                    $tags['migration'] = array_replace((array) ($tags['migration'] ?? []), ['state' => 'finished', 'finished_at' => now()->toIso8601String(), 'address' => $address, 'to_node' => $context->get('target_node_name')]); // the panel shows the outcome and the new address
                    $tags['access'] = array_replace((array) ($tags['access'] ?? []), array_filter(['address' => $address, 'identifier' => $meta['identifier'] ?? null]));
                    $service->forceFill(['node_id' => (string) $context->get('target_node_id'), 'provider_instance_id' => $targetInstanceId, 'tags' => $tags])->save();
                });
                try {
                    $adapter = GameMigrationWorkflow::targetAdapter($context);
                    if ($adapter instanceof PowerCapable) {
                        $adapter->power($source->fresh()->ref(), 'start');
                    }
                } catch (Throwable $e) {
                    $context->operation->withContext(['start_error' => mb_substr($e->getMessage(), 0, 200)])->save();
                }
                $context->container->make(AuditRecorder::class)->record($context->actor->withScope($service->organization_id), 'service.migrated', 'succeeded', ['from_node' => $context->get('source_node_name'), 'to_node' => $context->get('target_node_name'), 'to_panel' => $context->get('target_instance_key'), 'address' => $address, 'operation_id' => $context->operation->id], 'service', $service->id);
                $context->container->make(OutboxPublisher::class)->publish(GenericEvent::of('service.migrated', 'service', $service->id, ['label' => $service->label ?: $service->name, 'address' => $address, 'from_node' => $context->get('source_node_name'), 'to_node' => $context->get('target_node_name'), 'reason' => $context->desired('reason')], $service->organization_id));

                $left = (array) $context->get('collaborators_dropped', []);
                if ($left !== []) { // the explicit exception (H341): the server moved, these people did not — the customer adds them again or not
                    $context->container->make(OutboxPublisher::class)->publish(GenericEvent::of('service.migration.collaborators_dropped', 'service', $service->id, ['label' => $service->label ?: $service->name, 'count' => count($left), 'collaborators' => array_slice($left, 0, 20)], $service->organization_id));
                }

                return StepResult::done(['swapped' => true, 'address' => $address]);
            }
        };
    }

    private function cleanupStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Úklid zdroje';
            }

            public function run(StepContext $context): StepResult
            {
                $result = $this->capability($context, InfrastructureProvider::class)->terminate(GameMigrationWorkflow::sourceRef($context));

                return StepResult::done(['source_deleted' => (bool) ($result->data['deleted'] ?? $result->alreadyExisted)]);
            }
        };
    }
}
