<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflows;

use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\StagingLink;
use Onhost\Domain\Services\ServiceBackups;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\Web\StagingService;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\AsyncHandle;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\BackupCapable;
use Throwable;

/**
 * Staging lifecycle: create (new site on the same server, wait for it, copy), refresh (production → staging),
 * push (safety backup, staging → production), delete (terminate the staging site).
 */
final class StagingWorkflow implements Workflow
{
    public static function kind(): string
    {
        return 'service.staging';
    }

    public function queue(Operation $operation): string
    {
        $instance = $operation->provider_instance_id ? ProviderInstance::query()->find($operation->provider_instance_id) : null;

        return 'provider-'.($instance?->provider ?? 'default');
    }

    public function steps(Operation $operation): array
    {
        return match ((string) data_get($operation->desired, 'action')) {
            'staging.refresh' => [$this->syncStep('refresh')],
            'staging.push' => [$this->backupStep(), $this->syncStep('push')],
            'staging.delete' => [$this->deleteStep()],
            default => [$this->createStep(), $this->syncStep('create')],
        };
    }

    public function compensate(StepContext $context): void
    {
        $link = StagingLink::query()->where('service_id', $context->service->id)->first();
        if ($link !== null && (string) data_get($context->operation->desired, 'action') === 'staging.create' && $link->state === 'provisioning') {
            $link->forceFill(['state' => 'failed', 'meta' => array_merge((array) $link->meta, ['error' => (string) ($context->operation->error['message'] ?? 'failed')])])->save();
        }
        $context->container->make(OutboxPublisher::class)->publish(GenericEvent::of('staging.failed', 'service', $context->service->id, ['action' => data_get($context->operation->desired, 'action'), 'error' => (string) ($context->operation->error['message'] ?? 'failed')], $context->service->organization_id));
    }

    private function createStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Založení staging webu';
            }

            public function run(StepContext $context): StepResult
            {
                $link = $context->container->make(StagingService::class)->createStaging($this->service($context), $context->actor);
                $staging = Service::query()->findOrFail($link->staging_service_id);
                if ($staging->state === ServiceStateMachine::ACTIVE) {
                    return StepResult::done(['staging_service_id' => $staging->id, 'staging_domain' => $link->staging_domain]);
                }

                return StepResult::wait(new AsyncHandle('onhost_service', $staging->id, null, ['link_id' => $link->id], 15, 2 * 3600), ['staging_service_id' => $staging->id, 'staging_domain' => $link->staging_domain]);
            }

            public function poll(StepContext $context, AsyncHandle $handle): StepResult
            {
                $staging = Service::query()->find($handle->handle);
                if ($staging === null) {
                    return StepResult::fail('the staging service disappeared', false);
                }
                if ($staging->state === ServiceStateMachine::ACTIVE) {
                    return StepResult::done(['staging_service_id' => $staging->id]);
                }
                if (in_array($staging->state, [ServiceStateMachine::FAILED, ServiceStateMachine::TERMINATED], true)) {
                    return StepResult::fail('provisioning of the staging site failed', false, ['staging_service_id' => $staging->id]);
                }

                return StepResult::wait($handle);
            }
        };
    }

    private function syncStep(string $mode): ServiceStep
    {
        return new class($mode) extends ServiceStep
        {
            public function __construct(private readonly string $mode) {}

            public function label(): string
            {
                return $this->mode === 'push' ? 'Přenos staging → produkce' : 'Kopie produkce → staging';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $staging = $context->container->make(StagingService::class);
                $link = $staging->link($service);
                if ($link === null) {
                    return StepResult::fail('no staging site', false);
                }
                $production = Service::query()->findOrFail($link->service_id);
                $copy = Service::query()->findOrFail($link->staging_service_id);
                if ($copy->state !== ServiceStateMachine::ACTIVE) {
                    return StepResult::fail('the staging site is not active', false, ['state' => $copy->state]);
                }
                if ($this->mode === 'push' && $link->last_synced_at === null) {
                    return StepResult::fail('the staging site has never been refreshed from production; refresh it first so an empty copy cannot overwrite the live site', false);
                }
                [$from, $to] = $this->mode === 'push' ? [$copy, $production] : [$production, $copy];
                $result = $staging->sync($from, $to, $link, (bool) $context->desired('databases', true), $context->actor);
                $link->forceFill(array_merge(['state' => 'ready', 'databases' => $result['databases']], $this->mode === 'push' ? ['last_pushed_at' => now()] : ['last_synced_at' => now()]))->save();
                $features = $context->container->make(ServiceFeatures::class);
                $features->forget($production);
                $features->forget($copy);
                $context->container->make(OutboxPublisher::class)->publish(GenericEvent::of($this->mode === 'push' ? 'staging.pushed' : 'staging.synced', 'service', $production->id, ['staging_service_id' => $copy->id, 'domain' => $link->staging_domain, 'mode' => $this->mode], $production->organization_id));

                return StepResult::done(['log' => $result['log'], 'staging_domain' => $link->staging_domain]);
            }
        };
    }

    /** A backup of production before staging overwrites it. */
    private function backupStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Záloha produkce před přenosem';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $adapter = $context->adapter();
                if (ServiceBackups::platformMade($service, $adapter)) {
                    // the push overwrites the files AND the databases of production: the way back is a whole, fresh backup — not the
                    // panel's files-only archive, and never last night's archive under a new date (ServiceBackups)
                    try {
                        $backup = $context->container->make(ServiceBackups::class)->take($service, $adapter, $this->ref($context), $context->actor, $context->operation->id, 'pre-push', 7);
                    } catch (Throwable $e) {
                        return StepResult::fail('zálohu produkce před přenosem se nepodařilo vytvořit, nic se nepřepsalo: '.$e->getMessage(), true, [], 120);
                    }

                    return StepResult::done(['backup_id' => $backup->id]);
                }
                if (! $adapter instanceof BackupCapable) {
                    return StepResult::skip();
                }
                $backup = Backup::query()->firstOrCreate(['operation_id' => $context->operation->id], ['service_id' => $service->id, 'organization_id' => $service->organization_id, 'provider_instance_id' => $service->provider_instance_id, 'kind' => 'pre-push', 'state' => 'running', 'started_at' => now(), 'retention_until' => now()->addDays(7)]);
                $result = $adapter->backup($this->ref($context), ['notes' => 'before staging push']);
                if (! $result->isAsync()) {
                    $this->complete($context, $backup);
                }

                return $this->settle($result, ['backup_id' => $backup->id]);
            }

            protected function afterAsyncSuccess(StepContext $context, AsyncStatus $status): StepResult
            {
                $backup = Backup::query()->find((string) $context->get('backup_id'));
                if ($backup !== null) {
                    $this->complete($context, $backup);
                }

                return StepResult::done();
            }

            private function complete(StepContext $context, Backup $backup): void
            {
                $adapter = $context->adapter();
                $latest = $adapter instanceof BackupCapable ? collect($adapter->listBackups($this->ref($context)))->sortByDesc('created_at')->first() : null;
                $backup->forceFill(['state' => 'completed', 'finished_at' => now(), 'remote_id' => $latest['remote_id'] ?? null, 'size_bytes' => $latest['size_bytes'] ?? null])->save();
            }
        };
    }

    private function deleteStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Zrušení staging webu';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $context->container->make(StagingService::class)->deleteStaging($service, $context->actor);
                $context->container->make(ServiceFeatures::class)->forget($service);

                return StepResult::done(['deleted' => true]);
            }
        };
    }
}
