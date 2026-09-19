<?php

declare(strict_types=1);

namespace Onhost\Platform\Http;

/** Every address a host name answers with — all of them, because a name with one public and one private address is a way in. */
interface HostResolver
{
    /** @return list<string> IPv4 and IPv6 addresses; empty when the name does not resolve */
    public function resolve(string $host): array;
}
