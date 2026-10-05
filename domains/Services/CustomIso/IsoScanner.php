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

    /** No scanner answered (not configured, unreachable, timed out). */
    public const UNAVAILABLE = 'unavailable';

    /**
     * @param  resource  $stream  read from its current position to the end
     * @return array{result:string, signature:?string}
     */
    public function scan($stream): array;
}
