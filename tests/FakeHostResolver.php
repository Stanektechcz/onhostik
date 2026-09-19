<?php

declare(strict_types=1);

namespace Tests;

use Onhost\Platform\Http\HostResolver;

/** No test asks the real DNS: every name answers with a public address unless a test says where it points. */
final class FakeHostResolver implements HostResolver
{
    /** @var array<string, list<string>> */
    public static array $hosts = [];

    public function resolve(string $host): array
    {
        return self::$hosts[strtolower($host)] ?? ['93.184.216.34'];
    }
}
