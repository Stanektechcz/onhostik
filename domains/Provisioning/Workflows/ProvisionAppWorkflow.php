<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflows;

use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Domain\Services\Models\Application;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceService;
use Onhost\Providers\Contracts\KubernetesProvider;

/** ONhost Apps tenant saga (blueprint §9): cluster → hardened namespace + quotas + network policies → ACTIVE (deploys are separate operations). */
final class ProvisionAppWorkflow implements Workflow
{
    public static function kind(): string
    {
        return 'provision.app';
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
                    return 'Výběr clusteru';
                }

                public function run(StepContext $context): StepResult
                {
                    $service = $this->service($context);
                    if ($service->provider_instance_id !== null) {
                        return StepResult::done(['provider_instance_id' => $service->provider_instance_id]);
                    }
                    $instance = $context->container->make(ProviderRegistry::class)->findInstance('kubernetes', $service->region_code, 'namespace.tenant');
                    if ($instance === null) {
                        return StepResult::fail('No usable Kubernetes cluster for the region', true, ['region' => $service->region_code], 900);
                    }
                    $service->forceFill(['provider_instance_id' => $instance->id])->save();
                    $context->operation->forceFill(['provider_instance_id' => $instance->id])->save();

                    return StepResult::done(['provider_instance_id' => $instance->id]);
                }
            },
            new class extends ServiceStep
            {
                public function label(): string
                {
                    return 'Namespace a kvóty';
                }

                public function run(StepContext $context): StepResult
                {
                    $service = $this->service($context);
                    $k8s = $this->capability($context, KubernetesProvider::class);
                    $namespace = (string) $context->desired('namespace', 'tenant-'.strtolower(str_replace('_', '-', substr($service->id, 4))));
                    $result = $k8s->provision($context->spec('namespace', ['namespace' => $namespace, 'entitlements' => (array) $service->entitlements, 'sla_class' => $service->sla_class]));
                    if ($result->ref !== null) {
                        $context->bind($context->instance(), 'namespace', $result->ref->remoteId, null, $result->ref->meta, ['managed_by' => 'onhost']);
                    }

                    return $this->settle($result, ['namespace' => $namespace]);
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
                    $app = (array) $context->desired('app', []);
                    Application::query()->updateOrCreate(['service_id' => $service->id], [
                        'name' => (string) ($app['name'] ?? $service->label ?? $service->name), 'git_repo' => $app['git_repo'] ?? null, 'git_branch' => (string) ($app['git_branch'] ?? 'main'), 'git_provider' => $app['git_provider'] ?? null,
                        'runtime' => (string) ($app['runtime'] ?? 'node-22'), 'build_command' => $app['build_command'] ?? null, 'start_command' => $app['start_command'] ?? null, 'port' => (int) ($app['port'] ?? 8080), 'healthcheck_path' => (string) ($app['healthcheck_path'] ?? '/'),
                        'env' => (array) ($app['env'] ?? []), 'secret_refs' => [], 'namespace' => (string) $context->get('namespace'), 'domains' => (array) ($app['domains'] ?? []), 'replicas' => (int) $service->entitlement('replicas', 1), 'state' => 'active',
                    ]);
                    $context->container->make(ServiceService::class)->activate($service, $context->actor, $context->operation, ['namespace' => $context->get('namespace')]);

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
        $context->container->make(CompensationGuard::class)->takeBack($context, $service, 'namespace');
        $context->container->make(ServiceService::class)->fail($service, $context->actor, (string) ($context->operation->error['message'] ?? 'provisioning failed'), $context->operation);
    }
}
