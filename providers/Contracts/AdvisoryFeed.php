<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * A source of vulnerability advisories read incrementally (SN, docs/proposals/security-advisories.md): CISA KEV, NVD, Wordfence.
 * `fetch()` returns what changed since `$cursor` (the feed's own resume point, opaque to the caller) and the cursor to start from
 * next time. Every advisory is normalized (AdvisoryBatch); text from a feed is untrusted and is never logged with the call.
 * A failure throws (ProviderException); the caller keeps the old cursor, so nothing is skipped.
 */
interface AdvisoryFeed
{
    /** The feed's key in `security_advisory_sources` and in `onhost.advisories.feeds` (kev, nvd, wordfence). */
    public function key(): string;

    /** Whether the feed can be asked at all (switched on, its API key present when it needs one). */
    public function enabled(): bool;

    public function fetch(?string $cursor): AdvisoryBatch;
}
