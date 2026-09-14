<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflows;

use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Domain\Services\Models\Application;
use Onhost\Domain\Services\Models\Build;
use Onhost\Domain\Services\Models\Deployment;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\KubernetesProvider;

/**
 * git push → rootless BuildKit build (SBOM + provenance) → rolling deploy → rollout verified.
 * A failed rollout rolls back to the previous digest automatically (blueprint §9.3).
 */
final class DeployAppWorkflow implements Workflow
{
    public static function kind(): string
    {
        return 'app.deploy';
    }

    public function queue(Operation $operation): string
    {
        return 'provider-kubernetes';
    }

    public function steps(Operation $operation): array
    {
        return [
            new class extends ServiceStep
            {
                public function label(): string
                {
                    return 'Build';
                }

                public function run(StepContext $context): StepResult
                {
                    $service = $this->service($context);
                    $app = Application::query()->where('service_id', $service->id)->firstOrFail();
                    $build = Build::query()->firstOrCreate(['application_id' => $app->id, 'commit_sha' => (string) $context->desired('commit_sha'), 'state' => 'running'], [
                        'image_ref' => sprintf('%s/%s/%s:%s', rtrim((string) $context->instance()->option('registry', 'registry.onhost.internal'), '/'), $app->namespace, $app->name, substr((string) $context->desired('commit_sha'), 0, 12)),
                        'triggered_by' => $context->actor->actorId, 'started_at' => now(),
                    ]);
                    if ($context->get('build_id') !== null) {
                        return StepResult::done();
                    }
                    $result = $this->capability($context, KubernetesProvider::class)->submitBuild($build->id, [
                        'git_url' => (string) ($context->desired('git_url') ?? $app->git_repo), 'git_ref' => (string) $context->desired('commit_sha'), 'image_ref' => $build->image_ref, 'dockerfile' => $context->desired('dockerfile', 'Dockerfile'),
                        'build_args' => (array) $context->desired('build_args', []), 'timeout_seconds' => (int) $context->desired('build_timeout', 1800),
                    ]);

                    return $this->settle($result, ['build_id' => $build->id, 'image_ref' => $build->image_ref]);
                }

                protected function afterAsyncSuccess(StepContext $context, AsyncStatus $status): StepResult
                {
                    $digest = (string) ($status->detail['image_digest'] ?? $context->desired('image_digest', ''));
                    Build::query()->whereKey($context->get('build_id'))->update(['state' => 'succeeded', 'finished_at' => now(), 'image_digest' => $digest ?: null]);

                    return StepResult::done(['image_digest' => $digest ?: null]);
                }
            },
            new class extends ServiceStep
            {
                public function label(): string
                {
                    return 'Nasazení';
                }

                public function run(StepContext $context): StepResult
                {
                    $service = $this->service($context);
                    $app = Application::query()->where('service_id', $service->id)->firstOrFail();
                    $previous = $app->current_deployment_id ? Deployment::query()->find($app->current_deployment_id) : null;
                    $deployment = Deployment::query()->create(['application_id' => $app->id, 'build_id' => $context->get('build_id'), 'state' => 'rolling', 'strategy' => 'rolling', 'image_digest' => $context->get('image_digest'), 'started_at' => now()]);
                    $k8s = $this->capability($context, KubernetesProvider::class);
                    $result = $k8s->deployRelease($app->namespace, $app->name, [
                        'image_ref' => (string) $context->get('image_ref'), 'image_digest' => $context->get('image_digest'), 'port' => (int) $app->port, 'replicas' => (int) $app->replicas, 'env' => (array) $app->env,
                        'secret_name' => $app->secret_refs['env_secret'] ?? null, 'healthcheck_path' => $app->healthcheck_path, 'domains' => (array) $app->domains,
                        'cpu_request' => $service->entitlement('cpu_request', '100m'), 'cpu_limit' => $service->entitlement('cpu_limit', '1'), 'memory' => (int) $service->entitlement('ram_mb', 512).'Mi',
                    ]);

                    return $this->settle($result, ['deployment_id' => $deployment->id, 'previous_digest' => $previous?->image_digest]);
                }

                protected function afterAsyncSuccess(StepContext $context, AsyncStatus $status): StepResult
                {
                    $service = $this->service($context);
                    $app = Application::query()->where('service_id', $service->id)->firstOrFail();
                    Deployment::query()->whereKey($context->get('deployment_id'))->update(['state' => 'live', 'finished_at' => now()]);
                    $app->forceFill(['current_deployment_id' => $context->get('deployment_id')])->save();
                    $context->container->make(OutboxPublisher::class)->publish(GenericEvent::of('app.deployed', 'service', $service->id, ['deployment_id' => $context->get('deployment_id'), 'digest' => $context->get('image_digest')], $service->organization_id));

                    return StepResult::done(['deployed' => true]);
                }
            },
        ];
    }

    public function compensate(StepContext $context): void
    {
        $deploymentId = $context->get('deployment_id');
        if ($deploymentId !== null) {
            Deployment::query()->whereKey($deploymentId)->update(['state' => 'failed', 'finished_at' => now()]);
        }
        if ($context->get('build_id') !== null && $context->get('image_digest') === null) {
            Build::query()->whereKey($context->get('build_id'))->update(['state' => 'failed', 'finished_at' => now()]);
        }
        $previous = $context->get('previous_digest');
        if ($previous !== null && $deploymentId !== null) {
            try {
                $service = $this->serviceFor($context);
                $app = Application::query()->where('service_id', $service->id)->first();
                if ($app !== null) {
                    $context->adapter()->rollbackRelease($app->namespace, $app->name, (string) $previous);
                }
            } catch (\Throwable) {
                // rollback is best effort; the deployment is marked failed and the on-call sees it in the operation
            }
        }
    }

    private function serviceFor(StepContext $context): Service
    {
        return $context->service ?? Service::query()->findOrFail($context->operation->service_id);
    }
}
