<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflows;

use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Workflow\AbstractStep;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Services\Models\Service;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\ResourceRef;

/** Base for service sagas: resolves the Service aggregate, its primary binding and capability-checked adapters. */
abstract class ServiceStep extends AbstractStep
{
    protected function service(StepContext $context): Service
    {
        $service = $context->service ?? ($context->operation->service_id ? Service::query()->find($context->operation->service_id) : null);
        if ($service === null) {
            throw new \RuntimeException('Operation has no service assigned');
        }

        return $service;
    }

    protected function binding(StepContext $context, ?string $remoteType = null): ProviderBinding
    {
        $binding = $context->binding($remoteType) ?? ProviderBinding::query()->where('service_id', $this->service($context)->id)->when($remoteType, fn ($q) => $q->where('remote_type', $remoteType))->orderBy('created_at')->first();
        if ($binding === null) {
            throw new \RuntimeException('Service has no provider binding'.($remoteType ? " of type {$remoteType}" : ''));
        }

        return $binding;
    }

    protected function ref(StepContext $context, ?string $remoteType = null): ResourceRef
    {
        return $this->binding($context, $remoteType)->ref();
    }

    /** @template T of object @param class-string<T> $interface @return T */
    protected function capability(StepContext $context, string $interface): object
    {
        $adapter = $context->adapter();
        if (! $adapter instanceof $interface) {
            throw new \RuntimeException(get_class($adapter)." does not implement {$interface}");
        }

        return $adapter;
    }

    /** DONE when the provider finished synchronously, WAIT with the handle otherwise. */
    protected function settle(ProviderResult $result, array $context = []): StepResult
    {
        if ($result->isAsync() && $result->async !== null) {
            return StepResult::wait($result->async, $context);
        }

        return StepResult::done(array_merge($context, ['last_result' => $result->data]));
    }
}
