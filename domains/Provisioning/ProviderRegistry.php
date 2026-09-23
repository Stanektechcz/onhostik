<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Illuminate\Contracts\Container\Container;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Secrets\SecretStore;
use Onhost\Providers\Contracts\ProviderAdapter;

/**
 * Resolves the adapter for a provider instance and injects credentials read at
 * runtime from the secret store. The scheduler and workflows never reference a
 * vendor class by name — they ask the registry for a capability.
 */
final class ProviderRegistry
{
    /** @var array<string, class-string<ProviderAdapter>> */
    private array $adapters = [];

    /** @var array<string, ProviderAdapter> */
    private array $instances = [];

    public function __construct(private readonly Container $container, private readonly SecretStore $secrets) {}

    /** @param class-string<ProviderAdapter> $class */
    public function register(string $providerKey, string $class): void
    {
        $this->adapters[$providerKey] = $class;
    }

    /** @return list<string> */
    public function providerKeys(): array
    {
        return array_keys($this->adapters);
    }

    /** @return class-string<ProviderAdapter>|null */
    public function adapterClass(string $providerKey): ?string
    {
        return $this->adapters[$providerKey] ?? null;
    }

    /**
     * Test seam: the adapter to hand out for an instance, instead of building one from its credentials. The suite
     * uses it to drive a saga that talks to two panels at once (`WebMigrationTest`); nothing in production calls it.
     */
    public function useAdapter(string $instanceId, ProviderAdapter $adapter): void
    {
        $this->instances[$instanceId] = $adapter;
    }

    public function forInstance(ProviderInstance $instance): ProviderAdapter
    {
        if (isset($this->instances[$instance->id])) {
            return $this->instances[$instance->id];
        }
        $class = $this->adapters[$instance->provider] ?? null;
        if ($class === null) {
            throw new DomainError('provider_unsupported', "No adapter registered for provider {$instance->provider}", 500);
        }
        $credentials = $this->secrets->read($instance->secretRef());
        $adapter = $this->container->make($class, ['instance' => $instance, 'credentials' => $credentials]);
        if (! $adapter instanceof ProviderAdapter) {
            throw new DomainError('provider_adapter_invalid', "{$class} is not a ProviderAdapter", 500);
        }

        return $this->instances[$instance->id] = $adapter;
    }

    public function forKey(string $instanceKey): ProviderAdapter
    {
        $instance = ProviderInstance::query()->where('key', $instanceKey)->first();
        if ($instance === null) {
            throw DomainError::notFound("Provider instance {$instanceKey}");
        }

        return $this->forInstance($instance);
    }

    /** First usable instance of a provider family in a region (or any region), optionally requiring a capability. */
    public function findInstance(string $provider, ?string $regionCode = null, ?string $capability = null): ?ProviderInstance
    {
        $query = ProviderInstance::query()->platform()->where('provider', $provider)->where('state', 'active')->orderBy('key');
        if ($regionCode !== null) {
            $query->where(fn ($q) => $q->where('region_code', $regionCode)->orWhereNull('region_code'));
        }
        foreach ($query->get() as $instance) {
            if ($instance->isUsable() && ($capability === null || $instance->supports($capability))) {
                return $instance;
            }
        }

        return null;
    }

    /**
     * An adapter built on credentials that are not stored yet (Brain card H314): a new access is tried against the
     * panel before it replaces the working one. Never cached, so it can never become the instance's own adapter.
     *
     * @param  array<string,string>  $credentials
     */
    public function trial(ProviderInstance $instance, array $credentials): ProviderAdapter
    {
        $class = $this->adapters[$instance->provider] ?? null;
        if ($class === null) {
            throw new DomainError('provider_unsupported', "No adapter registered for provider {$instance->provider}", 500);
        }
        $adapter = $this->container->make($class, ['instance' => $instance, 'credentials' => $credentials]);
        if (! $adapter instanceof ProviderAdapter) {
            throw new DomainError('provider_adapter_invalid', "{$class} is not a ProviderAdapter", 500);
        }

        return $adapter;
    }

    public function forget(ProviderInstance $instance): void
    {
        unset($this->instances[$instance->id]);
    }
}
