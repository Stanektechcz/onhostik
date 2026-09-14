<?php

declare(strict_types=1);

namespace Onhost\Platform\Files;

use Throwable;

/**
 * Virus scan of uploaded files (audit §5r-4): clamd's INSTREAM protocol over TCP (`ONHOST_CLAMAV_HOST`,
 * `ONHOST_CLAMAV_PORT`) — the file goes in 64 kB chunks, clamd answers `stream: OK` or `stream: <signature> FOUND`.
 * An infected file never reaches the customer or a server; while clamd is unreachable the file is kept as
 * `unavailable` and, with `ONHOST_CLAMAV_ENFORCE`, cannot be downloaded until the retry pass (`onhost:files:scan`)
 * finds it clean. Without a host the scanner is off and every file counts as unscanned-but-allowed.
 */
class VirusScanner
{
    public const CLEAN = 'clean';

    public const INFECTED = 'infected';

    public const UNAVAILABLE = 'unavailable';

    public const OFF = 'off';

    public const CHUNK = 65536;

    public function enabled(): bool
    {
        return (string) config('onhost.storage.clamav.host', '') !== '';
    }

    public function enforced(): bool
    {
        return $this->enabled() && (bool) config('onhost.storage.clamav.enforce', true);
    }

    /** Whether a file with this scan result may be handed out. */
    public function allows(?string $result): bool
    {
        if ($result === self::INFECTED) {
            return false;
        }

        return ! $this->enforced() || $result === self::CLEAN;
    }

    /**
     * Scans a file on the store's disk.
     *
     * @return array{result:string, signature:?string, at:string}
     */
    public function scanPath(string $path): array
    {
        if (! $this->enabled()) {
            return self::outcome(self::OFF);
        }
        try {
            $stream = app(FileStore::class)->disk()->readStream($path);
        } catch (Throwable) {
            $stream = null;
        }
        if (! is_resource($stream)) {
            return self::outcome(self::UNAVAILABLE);
        }
        try {
            return $this->scanStream($stream);
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  resource  $stream
     * @return array{result:string, signature:?string, at:string}
     */
    public function scanStream($stream): array
    {
        if (! $this->enabled()) {
            return self::outcome(self::OFF);
        }
        try {
            $reply = $this->instream($stream);
        } catch (Throwable) {
            return self::outcome(self::UNAVAILABLE);
        }
        if (preg_match('/:\s*(.+)\s+FOUND\s*$/', $reply, $m) === 1) {
            return self::outcome(self::INFECTED, mb_substr(trim($m[1]), 0, 120));
        }

        return str_ends_with(rtrim($reply), 'OK') ? self::outcome(self::CLEAN) : self::outcome(self::UNAVAILABLE);
    }

    /**
     * The clamd conversation: `zINSTREAM\0`, length-prefixed chunks, a zero-length terminator, one reply line.
     *
     * @param  resource  $stream
     */
    protected function instream($stream): string
    {
        $timeout = max(1, (int) config('onhost.storage.clamav.timeout_seconds', 30));
        $socket = @stream_socket_client('tcp://'.config('onhost.storage.clamav.host').':'.(int) config('onhost.storage.clamav.port', 3310), $errno, $error, 5);
        if ($socket === false) {
            throw new \RuntimeException('clamd unreachable: '.$error);
        }
        stream_set_timeout($socket, $timeout);
        try {
            fwrite($socket, "zINSTREAM\0");
            while (! feof($stream)) {
                $chunk = (string) fread($stream, self::CHUNK);
                if ($chunk === '') {
                    break;
                }
                fwrite($socket, pack('N', strlen($chunk)).$chunk);
            }
            fwrite($socket, pack('N', 0));
            $reply = (string) stream_get_contents($socket);
        } finally {
            fclose($socket);
        }

        return trim($reply, "\0\r\n ");
    }

    /** @return array{result:string, signature:?string, at:string} */
    private static function outcome(string $result, ?string $signature = null): array
    {
        return ['result' => $result, 'signature' => $signature, 'at' => now()->toIso8601String()];
    }
}
