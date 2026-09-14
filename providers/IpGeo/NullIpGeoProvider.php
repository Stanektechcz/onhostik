<?php

declare(strict_types=1);

namespace Onhost\Providers\IpGeo;

use Onhost\Providers\Contracts\IpGeoProvider;

/** No lookup configured: the country signal is simply absent. */
final class NullIpGeoProvider implements IpGeoProvider
{
    public function country(string $ip): ?string
    {
        return null;
    }
}
