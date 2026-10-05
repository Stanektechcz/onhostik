<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\CustomIso;

use Illuminate\Container\Attributes\Bind;

/**
 * The virus scan a customer's own image passes before the platform keeps it (TASK-0110). Fail closed: anything but CLEAN is a
 * refusal — an image is never kept unscanned, never "allowed because the scanner is off" (the general upload scanner may be off
 * outside production; this one never is).
 */
#[Bind(ClamdIsoScanner::class)]
interface IsoScanner
{
    public const CLEAN = 'clean';

    public const INFECTED = 'infected';

    /** The scanner stopped at one of its limits (size, recursion): part of the image was not looked at. */
    public const INCOMPLETE = 'incomplete';

    /** The image is larger than the scanner takes at all (clamd's StreamMaxLength): a property of the file, not an outage. */
    public const TOO_LARGE = 'too_large';

    /** No scanner answered (not configured, unreachable, timed out). */
    public const UNAVAILABLE = 'unavailable';

    /**
     * @param  resource  $stream  read from its current position to the end
     * @return array{result:string, signature:?string}
     */
    public function scan($stream): array;

    /**
     * Whether the scanner can be trusted with an image right now (TASK-0110 review M1): it finds the EICAR test file AND it reports
     * a file it could not read in full (clamd `AlertExceedsMax yes`) instead of passing it. Without the second, an image beyond
     * clamd's MaxFileSize/MaxScanSize would come back "OK" unscanned.
     *
     * @return array{ok:bool, detail:string}
     */
    public function selfTest(): array;
}
