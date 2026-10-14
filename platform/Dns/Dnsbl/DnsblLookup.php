<?php

declare(strict_types=1);

namespace Onhost\Platform\Dns\Dnsbl;

/**
 * Asks a blocklist zone one A question. Separate from `RecordResolver` on purpose: blocklists refuse queries that come
 * through public resolvers (Spamhaus answers `127.255.255.254`), so this one goes to the resolver the operator names
 * (`ONHOST_DNSBL_RESOLVER`) and only falls back to the system resolver when none is named — and then never to a list that
 * refuses public resolvers.
 */
interface DnsblLookup
{
    public function a(string $name): DnsblAnswer;

    /** Where the questions go, for the doctor and the staff page — never a secret. */
    public function describe(): string;

    /**
     * Do the questions go to a resolver the operator named that is not a well-known public one? Lists that refuse public
     * resolvers are asked only then (`BlocklistCatalog::needsOwnResolver()`).
     */
    public function isOwnResolver(): bool;
}
