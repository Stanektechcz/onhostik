<?php

declare(strict_types=1);

namespace Onhost\Platform\Http;

final class DnsHostResolver implements HostResolver
{
    public function resolve(string $host): array
    {
        $out = [];
        foreach ([DNS_A => 'ip', DNS_AAAA => 'ipv6'] as $type => $field) {
            foreach (@dns_get_record($host, $type) ?: [] as $record) {
                if (isset($record[$field]) && is_string($record[$field])) {
                    $out[] = $record[$field];
                }
            }
        }
        if ($out === []) { // the system resolver knows /etc/hosts and search domains — exactly the names an attacker would try
            $out = array_values(array_filter((array) @gethostbynamel($host), 'is_string'));
        }

        return array_values(array_unique($out));
    }
}
