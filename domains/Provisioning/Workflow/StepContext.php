<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflow;

use Illuminate\Contracts\Container\Container;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Providers\Contracts\ProviderAdapter;
use Onhost\Providers\Contracts\ResourceSpec;

/** Everything a step needs: the operation (desired + accumulated context), the service, adapters. */
final class StepContext
{
    public function __construct(
        public readonly Operation $operation,
        public readonly ?Service $service,
        public readonly ProviderRegistry $providers,
        public readonly Container $container,
        public readonly CommandContext $actor,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->operation->context, $key, data_get($this->operation->desired, $key, $default));
    }

    public function desired(string $key, mixed $default = null): mixed
    {
        return data_get($this->operation->desired, $key, $default);
    }

    public function instance(?string $key = null): ProviderInstance
    {
        $id = $key ?? $this->get('provider_instance_id') ?? $this->operation->provider_instance_id ?? $this->service?->provider_instance_id;
        $instance = $id === null ? null : ProviderInstance::query()->find($id);
        if ($instance === null) {
            throw new \RuntimeException('Operation has no provider instance assigned');
        }

        return $instance;
    }

    public function adapter(?string $instanceId = null): ProviderAdapter
    {
        return $this->providers->forInstance($this->instance($instanceId));
    }

    public function spec(string $kind, array $attributes = []): ResourceSpec
    {
        return new ResourceSpec(
            (string) ($this->service?->id ?? $this->operation->service_id ?? $this->operation->id),
            $kind,
            $this->operation->idempotency_key,
            array_replace((array) $this->operation->desired, (array) $this->operation->context, $attributes),
            $this->get('node_name'),
            $this->get('region') ?? $this->service?->region_code,
            $this->operation->organization_id,
        );
    }

    public function binding(?string $remoteType = null): ?ProviderBinding
    {
        if ($this->service === null) {
            return null;
        }
        $query = ProviderBinding::query()->where('service_id', $this->service->id);
        if ($remoteType !== null) {
            $query->where('remote_type', $remoteType);
        }

        return $query->orderBy('created_at')->first();
    }

    /** Upsert a binding for the service (unique per idempotency key + remote id). */
    public function bind(ProviderInstance $instance, string $remoteType, string $remoteId, ?string $node = null, array $meta = [], array $ownership = []): ProviderBinding
    {
        // a panel that answers 200 without an identifier has created nothing we can address (H319): binding a service to
        // '' or '0' would activate it against no resource, and every later call would hit whatever owns that number
        if (trim($remoteId) === '' || trim($remoteId) === '0' || trim($remoteType) === '') {
            throw new \RuntimeException("The provider returned no usable identifier for {$remoteType}; the service is not bound and will not be activated.");
        }
        $serviceId = (string) ($this->service?->id ?? $this->operation->service_id);
        $key = $this->operation->idempotency_key.':'.$remoteType;

        return ProviderBinding::query()->updateOrCreate(
            ['idempotency_key' => $key],
            ['service_id' => $serviceId, 'provider_instance_id' => $instance->id, 'remote_type' => $remoteType, 'remote_id' => $remoteId, 'remote_node' => $node, 'meta' => $meta, 'ownership' => $ownership, 'adapter_version' => $instance->adapter_version],
        );
    }
}
