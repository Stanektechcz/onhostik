<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * A vulnerability database asked about concrete packages instead of read as a feed (OSV.dev): given what we run, which advisories
 * cover it. The answer is normalized like a feed's (AdvisoryBatch::normalize) and still matched by the platform's own version rules.
 */
interface AdvisoryLookup
{
    public function key(): string;

    public function enabled(): bool;

    /** @return list<string> the inventory ecosystems this database knows (OSV names: Packagist, npm, Debian:12, …) */
    public function ecosystems(): array;

    /**
     * @param  list<array{ecosystem:string, product:string, version:string}>  $packages
     * @return list<array<string,mixed>> normalized advisories (AdvisoryBatch shape) covering at least one of the packages
     */
    public function lookup(array $packages): array;
}
