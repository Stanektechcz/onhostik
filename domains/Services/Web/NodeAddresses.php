<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Services\Models\Service;

/** Public addresses of the node a web service runs on: the node's tags first, the provider instance option as the fallback. */
final class NodeAddresses
{
    /** @return list<string> */
    public static function ipv4(Service $service): array
    {
        return self::collect($service, 'public_ipv4');
    }

    /** @return list<string> */
    public static function ipv6(Service $service): array
    {
        return self::collect($service, 'public_ipv6');
    }

    /** @return list<string> */
    private static function collect(Service $service, string $key): array
    {
        $node = $service->node_id ? Node::query()->find($service->node_id) : null;
        $instance = $service->provider_instance_id ? ProviderInstance::query()->find($service->provider_instance_id) : null;
        $values = [(string) ($node?->tags[$key] ?? ''), (string) ($instance?->option($key, '') ?? '')];

        return array_values(array_filter(array_unique(array_map('trim', $values))));
    }
}
