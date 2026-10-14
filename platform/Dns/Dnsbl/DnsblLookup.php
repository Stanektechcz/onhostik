<?php

declare(strict_types=1);

namespace Onhost\Platform\Dns\Dnsbl;

/**
 * Asks a blocklist zone one A question. Separate from `RecordResolver` on purpose: blocklists refuse queries that come
 * through public resolvers (Spamhaus answers `127.255.255.254`), so this one goes to the resolver the operator names
 * (`ONHOST_DNSBL_RESOLVER`) and only falls back to the system resolver when none is named.
 */
interface DnsblLookup
{
    public function a(string $name): DnsblAnswer;

    /** Where the questions go, for the doctor and the staff page — never a secret. */
    public function describe(): string;
}
