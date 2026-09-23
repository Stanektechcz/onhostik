<?php

declare(strict_types=1);

namespace Onhost\Platform\Dns;

/**
 * What the internet answers for a name — not what the platform's own zone says it should. The two are different
 * things and only the first one decides whether a site is reachable and whether mail arrives.
 *
 * It is deliberately separate from `HostResolver`, which answers one question for `EgressGuard` (where does this
 * customer-named host point, so that a request cannot be aimed at our own network). A security decision and a
 * customer-facing check should not share an implementation that one of them may widen.
 */
interface RecordResolver
{
    /**
     * The records public DNS holds for a name, in the shape `dns_get_record()` returns: `ip` for A, `ipv6` for AAAA,
     * `target` and `pri` for MX, `txt` for TXT, `target` for CNAME. An empty list when the name does not answer.
     *
     * @param  string  $type  A | AAAA | MX | TXT | CNAME | NS | SRV
     * @return list<array<string,mixed>>
     */
    public function records(string $name, string $type): array;
}
