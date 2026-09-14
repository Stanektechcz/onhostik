<?php

declare(strict_types=1);

namespace Onhost\Platform\Net;

use Closure;

/** CNAME lookups with a test seam: `DnsLookup::$resolver = fn (string $host): array => ['target.example']` replaces the resolver. */
final class DnsLookup
{
    /** @var (Closure(string): list<string>)|null */
    public static ?Closure $resolver = null;

    /** @return list<string> CNAME targets of the host (lowercase, no trailing dot); empty when none or on failure */
    public static function cname(string $host): array
    {
        $host = strtolower(trim($host, '. '));
        if ($host === '') {
            return [];
        }
        if (self::$resolver !== null) {
            return array_values(array_map(fn ($t) => strtolower(rtrim((string) $t, '.')), (self::$resolver)($host)));
        }
        $records = function_exists('dns_get_record') ? @dns_get_record($host, DNS_CNAME) : [];
        $out = [];
        foreach ((array) $records as $record) {
            if (! empty($record['target'])) {
                $out[] = strtolower(rtrim((string) $record['target'], '.'));
            }
        }

        return $out;
    }
}
