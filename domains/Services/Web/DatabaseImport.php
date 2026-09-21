<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Onhost\Platform\Errors\DomainError;

/**
 * What an import of a SQL dump costs before it is allowed to start (H456).
 *
 * A dump is not the size of what it becomes: the rows are written again as data, the indexes are built beside them,
 * and while the engine loads it needs room for its own log and sort files. Two times the dump is the usual guidance
 * and the one used here — under it, an import that runs out of disk half way takes the site's database with it, and
 * on a shared node it takes the neighbours' too.
 *
 * The size of a gzipped dump is read from the four bytes gzip writes at the end of the file (the uncompressed length
 * modulo 4 GiB), which costs one seek. When that cannot be trusted — a file at or over 4 GiB, a dump that is not
 * gzip — the compressed size is used with the ratio a SQL dump usually reaches, and the check stays on the safe side.
 */
final class DatabaseImport
{
    /** Data, indexes and what the engine needs while it loads. */
    public const HEADROOM = 2.0;

    /** What a SQL dump usually shrinks to; used only when the real length cannot be read. */
    public const GZIP_RATIO = 5;

    /** How much of the plan's disk may be taken by everything together before an import is refused. */
    public const CEILING = 0.95;

    /** The number of bytes this dump will be when it is read, as well as it can be known. */
    public static function dumpBytes(string $file): int
    {
        $size = @filesize($file);
        if ($size === false || $size <= 0) {
            return 0;
        }
        if (! self::isGzip($file)) {
            return $size;
        }
        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            return $size * self::GZIP_RATIO;
        }
        fseek($handle, -4, SEEK_END);
        $tail = (string) fread($handle, 4);
        fclose($handle);
        $isize = strlen($tail) === 4 ? (int) (unpack('V', $tail)[1] ?? 0) : 0;

        // gzip writes the length modulo 4 GiB: a value smaller than the compressed file, or a file big enough to have
        // wrapped round, means the number says nothing — the ratio is used instead and it errs upwards
        return $isize > $size && $size < 512 * 1024 ** 2 ? $isize : $size * self::GZIP_RATIO;
    }

    private static function isGzip(string $file): bool
    {
        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            return false;
        }
        $magic = (string) fread($handle, 2);
        fclose($handle);

        return $magic === "\x1f\x8b";
    }

    /**
     * Whether what this dump needs still fits in what the plan sells.
     *
     * A service whose panel does not measure disk (no limit, no reading) is let through: refusing on a number nobody
     * has would stop imports that are perfectly fine, and the node's own disk guard is still behind it.
     *
     * @param  array<string,mixed>  $quotas  as `ServiceFeatures::resources($service, 'quotas')` returns them
     * @return array{needed:int, free:int, checked:bool}
     */
    public static function room(int $dumpBytes, array $quotas): array
    {
        $limit = (int) ($quotas['disk_limit_bytes'] ?? 0);
        $used = (int) ($quotas['disk_used_bytes'] ?? 0);
        $needed = (int) ceil($dumpBytes * self::HEADROOM);
        if ($limit <= 0) {
            return ['needed' => $needed, 'free' => 0, 'checked' => false];
        }

        return ['needed' => $needed, 'free' => max(0, (int) ($limit * self::CEILING) - $used), 'checked' => true];
    }

    /**
     * @param  array<string,mixed>  $quotas
     *
     * @throws DomainError when the import would not fit
     */
    public static function assertRoom(int $dumpBytes, array $quotas): array
    {
        $room = self::room($dumpBytes, $quotas);
        if ($room['checked'] && $room['needed'] > $room['free']) {
            throw new DomainError('database_import_too_large', sprintf(
                'Import potřebuje asi %s (data i indexy), ve vašem tarifu zbývá %s. Uvolněte místo nebo si navyšte tarif — nic nebylo změněno.',
                self::human($room['needed']), self::human($room['free'])
            ), 422, ['needed_bytes' => $room['needed'], 'free_bytes' => $room['free']]);
        }

        return $room;
    }

    public static function human(int $bytes): string
    {
        foreach (['B', 'kB', 'MB', 'GB'] as $i => $unit) {
            if ($bytes < 1024 ** ($i + 1) || $unit === 'GB') {
                return round($bytes / 1024 ** $i, $i > 1 ? 1 : 0).' '.$unit;
            }
        }

        return $bytes.' B';
    }
}
