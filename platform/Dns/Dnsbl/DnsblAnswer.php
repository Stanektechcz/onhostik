<?php

declare(strict_types=1);

namespace Onhost\Platform\Dns\Dnsbl;

/**
 * What one blocklist zone answered for one name: the A records (`127.x.y.z` when listed), nothing (NXDOMAIN = not listed), or
 * no answer at all (timeout, SERVFAIL, a resolver that refused) — which is never "not listed".
 */
final class DnsblAnswer
{
    /** @param  list<string>  $addresses */
    private function __construct(public readonly array $addresses, public readonly bool $failed, public readonly string $reason = '') {}

    /** @param  list<string>  $addresses */
    public static function records(array $addresses): self
    {
        return new self(array_values(array_unique($addresses)), false);
    }

    public static function none(): self
    {
        return new self([], false);
    }

    public static function failed(string $reason): self
    {
        return new self([], true, $reason);
    }
}
