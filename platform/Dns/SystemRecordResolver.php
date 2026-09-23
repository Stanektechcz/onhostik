<?php

declare(strict_types=1);

namespace Onhost\Platform\Dns;

use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * The host's own resolver. `dns_get_record()` has no timeout of its own, so this is only ever used from the scheduled
 * check — never from a request the customer is waiting on, which reads the answer the check wrote down.
 *
 * The answer is kept for a few minutes: a run looks at a domain's address, its MX and three TXT names, and several
 * services can share a domain.
 */
final class SystemRecordResolver implements RecordResolver
{
    private const TYPES = ['A' => DNS_A, 'AAAA' => DNS_AAAA, 'MX' => DNS_MX, 'TXT' => DNS_TXT, 'CNAME' => DNS_CNAME, 'NS' => DNS_NS, 'SRV' => DNS_SRV];

    public function __construct(private readonly Cache $cache) {}

    public function records(string $name, string $type): array
    {
        $name = rtrim(mb_strtolower(trim($name)), '.');
        $type = mb_strtoupper($type);
        if ($name === '' || ! isset(self::TYPES[$type]) || ! preg_match('/^[a-z0-9_.\-]{1,253}$/', $name)) {
            return [];
        }

        return (array) $this->cache->remember('onhost:dns:public:'.$type.':'.$name, now()->addMinutes(10), function () use ($name, $type) {
            $answer = @dns_get_record($name, self::TYPES[$type]) ?: [];

            return array_values(array_filter($answer, fn (array $row) => ($row['type'] ?? '') === $type));
        });
    }
}
