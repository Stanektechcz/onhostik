<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\CustomIso;

use Onhost\Platform\Files\VirusScanner;

/**
 * clamd's INSTREAM through the platform's scanner (`VirusScanner`, `ONHOST_CLAMAV_HOST`), with two rules of its own (TASK-0110):
 *
 *  • no scanner is a refusal, never a pass — `VirusScanner` answers OFF outside production when no host is set, and a file that
 *    is OFF may still be handed out there; an image someone will boot is not;
 *  • clamd that stopped at one of its limits answers `Heuristics.Limits.Exceeded… FOUND` (with `AlertExceedsMax yes`): part of
 *    the image was never looked at, which is INCOMPLETE — refused, but no malware alarm (docs/runbooks/custom-iso.md).
 *
 * An image is large: the scan gets `onhost.custom_iso.scan_timeout_seconds` instead of the few seconds a document gets.
 */
final class ClamdIsoScanner implements IsoScanner
{
    public function __construct(private readonly VirusScanner $scanner) {}

    public function scan($stream): array
    {
        if (! $this->scanner->enabled()) {
            return ['result' => self::UNAVAILABLE, 'signature' => null];
        }
        $key = 'onhost.storage.clamav.timeout_seconds';
        $before = config($key);
        config([$key => max((int) $before, (int) config('onhost.custom_iso.scan_timeout_seconds', 900))]);
        try {
            $scan = $this->scanner->scanStream($stream);
        } finally {
            config([$key => $before]);
        }
        $signature = $scan['signature'] ?? null;

        return match ($scan['result']) {
            VirusScanner::CLEAN => ['result' => self::CLEAN, 'signature' => null],
            VirusScanner::INFECTED => str_starts_with((string) $signature, 'Heuristics.Limits')
                ? ['result' => self::INCOMPLETE, 'signature' => $signature]
                : ['result' => self::INFECTED, 'signature' => $signature],
            default => ['result' => self::UNAVAILABLE, 'signature' => null], // OFF and UNAVAILABLE alike: never kept unscanned
        };
    }
}
