<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;
use Onhost\Providers\Contracts\NodeOrderProvider;
use Onhost\Providers\Hetzner\HetznerCloudNodeOrderProvider;
use Throwable;

/**
 * Which vendor sells nodes for a provider instance (audit §5n-7): `options.node_order = {driver: hetzner, secret_ref?,
 * server_type?, image?, location?, ssh_keys?, user_data?}` on the instance; the token lives in the secret store (the
 * instance's own entry under `node_order_token`, or a separate `secret_ref`). No option, no vendor: the request stays a
 * human purchase.
 */
final class NodeOrders
{
    /** @var array<string,callable(array<string,mixed>):NodeOrderProvider> */
    private array $drivers = [];

    public function __construct(private readonly SecretStore $secrets)
    {
        $this->drivers['hetzner'] = fn (array $credentials) => new HetznerCloudNodeOrderProvider((string) ($credentials['token'] ?? ''));
    }

    /** @param callable(array<string,mixed>):NodeOrderProvider $factory */
    public function register(string $driver, callable $factory): void
    {
        $this->drivers[$driver] = $factory;
    }

    public function canOrder(ProviderInstance $instance): bool
    {
        $driver = (string) $instance->option('node_order.driver', '');

        return $driver !== '' && isset($this->drivers[$driver]);
    }

    public function for(ProviderInstance $instance): ?NodeOrderProvider
    {
        if (! $this->canOrder($instance)) {
            return null;
        }
        $ref = (string) $instance->option('node_order.secret_ref', '');
        try {
            $credentials = $ref !== '' ? $this->secrets->read(SecretRef::parse($ref)) : $this->secrets->read($instance->secretRef());
        } catch (Throwable) {
            return null;
        }
        if ($ref === '' && isset($credentials['node_order_token'])) {
            $credentials = ['token' => $credentials['node_order_token']];
        }

        return ($this->drivers[(string) $instance->option('node_order.driver')])($credentials);
    }
}
