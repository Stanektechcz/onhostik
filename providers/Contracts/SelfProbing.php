<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * A panel that can say — without changing anything — which of the calls the platform relies on it really answers.
 *
 * Adapters are written from the vendors' documentation and proven against doubles; whether the panel in the rack
 * answers the same way is a fact about that panel (its version, the rights of the API user, a plugin that is missing).
 * The nightly prerequisites pass (`onhost:nodes:check`) asks, records the answers on the instance and tells operations
 * what is off — instead of a customer's operation finding out.
 */
interface SelfProbing
{
    /**
     * Read-only probes. Keys are stable names; values are `ok`, or `refused: <reason>` / `missing: <reason>` /
     * `skipped: <reason>` (nothing to ask about yet). A key ending in `_fields` lists the field NAMES a call returned —
     * never values — so that the next change to the adapter is made on what the panel sends, not on memory.
     *
     * @param  ResourceRef|null  $anyResource  one bound resource of this instance to ask about, when there is one
     * @return array<string, string|list<string>>
     */
    public function probes(?ResourceRef $anyResource = null): array;
}
