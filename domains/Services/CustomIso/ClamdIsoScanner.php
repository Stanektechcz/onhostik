<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\CustomIso;

use Closure;
use Illuminate\Support\Facades\Cache;
use Onhost\Platform\Files\VirusScanner;
use RuntimeException;
use Throwable;

/**
 * clamd's INSTREAM for customers' images (TASK-0110), on the platform's clamd (`ONHOST_CLAMAV_HOST`, `ONHOST_CLAMAV_PORT`), with
 * rules of its own:
 *
 *  • no scanner is a refusal, never a pass — `VirusScanner` answers OFF outside production when no host is set, and a file that
 *    is OFF may still be handed out there; an image someone will boot is not;
 *  • the reply is read word for word: `stream: OK` is clean, `Heuristics.Limits.Exceeded… FOUND` (clamd with `AlertExceedsMax
 *    yes`) is INCOMPLETE, any other `FOUND` is infected, `INSTREAM size limit exceeded` is TOO_LARGE (the file, not an outage:
 *    422, review L1); anything else — an error, a timeout, a closed socket — is UNAVAILABLE;
 *  • its own timeout (`onhost.custom_iso.scan_timeout_seconds`), passed to the socket — the shared configuration is never
 *    changed for it (review L2);
 *  • a self-test before uploads are taken (review M1): EICAR must be found and a file nested deeper than clamd reads must be
 *    reported as a limit, not passed. Cached for a few minutes; a failed test is cached for one.
 *
 * `$transport` is the seam of the tests: given the command and the stream, it returns clamd's reply line.
 */
final class ClamdIsoScanner implements IsoScanner
{
    private const CHUNK = 65536;

    /** The self-test is two tiny files: it must not wait as long as an image of several gigabytes may (`scan_timeout_seconds`). */
    private const SELF_TEST_TIMEOUT = 15;

    public const SELF_TEST_KEY = 'onhost:custom-iso:scanner-self-test';

    /** EICAR, the antivirus test file, kept encoded so no scanner on a developer's machine quarantines this source file. */
    private const EICAR_B64 = 'WDVPIVAlQEFQWzRcUFpYNTQoUF4pN0NDKTd9JEVJQ0FSLVNUQU5EQVJELUFOVElWSVJVUy1URVNULUZJTEUhJEgrSCo=';

    /** Deeper than any MaxRecursion clamd is configured with in practice (default 17, maximum sensible well below this). */
    private const NESTING = 64;

    /** @param (Closure(string, resource|null, int=): string)|null $transport the seam of the tests; its third argument is the timeout in seconds */
    public function __construct(private readonly VirusScanner $scanner, private readonly ?Closure $transport = null) {}

    public function scan($stream): array
    {
        return $this->scanWithin($stream, null);
    }

    /**
     * @param  resource  $stream
     * @param  ?int  $timeout  seconds clamd may take; null = `scan_timeout_seconds`
     */
    private function scanWithin($stream, ?int $timeout): array
    {
        if (! $this->scanner->enabled() && $this->transport === null) {
            return ['result' => self::UNAVAILABLE, 'signature' => null];
        }
        try {
            $reply = $this->converse('zINSTREAM', $stream, $timeout);
        } catch (Throwable) {
            return ['result' => self::UNAVAILABLE, 'signature' => null];
        }

        return self::verdict($reply);
    }

    /**
     * What one clamd reply line means.
     *
     * @return array{result:string, signature:?string}
     */
    public static function verdict(string $reply): array
    {
        $reply = trim($reply, "\0\r\n ");
        if (preg_match('/:\s*(.+?)\s+FOUND$/', $reply, $m) === 1) {
            $signature = mb_substr(trim($m[1]), 0, 120);

            return ['result' => str_starts_with($signature, 'Heuristics.Limits') ? self::INCOMPLETE : self::INFECTED, 'signature' => $signature];
        }
        if (stripos($reply, 'size limit exceeded') !== false) {
            return ['result' => self::TOO_LARGE, 'signature' => null];
        }
        if (preg_match('/:\s*OK$/', $reply) === 1) {
            return ['result' => self::CLEAN, 'signature' => null];
        }

        return ['result' => self::UNAVAILABLE, 'signature' => null];
    }

    public function selfTest(): array
    {
        $cached = Cache::get(self::SELF_TEST_KEY);
        if (is_array($cached) && isset($cached['ok'], $cached['detail'])) {
            return ['ok' => (bool) $cached['ok'], 'detail' => (string) $cached['detail']];
        }
        $result = $this->runSelfTest();
        Cache::put(self::SELF_TEST_KEY, $result, $result['ok'] ? 600 : 60);

        return $result;
    }

    /** @return array{ok:bool, detail:string} */
    private function runSelfTest(): array
    {
        if (! $this->scanner->enabled() && $this->transport === null) {
            return ['ok' => false, 'detail' => 'no clamd is configured (ONHOST_CLAMAV_HOST)'];
        }
        $eicar = $this->scanWithin(self::memory((string) base64_decode(self::EICAR_B64)), self::SELF_TEST_TIMEOUT);
        if ($eicar['result'] !== self::INFECTED) {
            return ['ok' => false, 'detail' => 'clamd did not find the EICAR test file ('.$eicar['result'].')'];
        }
        $nested = 'onhost custom ISO self-test';
        for ($i = 0; $i < self::NESTING; $i++) {
            $nested = (string) gzencode($nested);
        }
        $limits = $this->scanWithin(self::memory($nested), self::SELF_TEST_TIMEOUT);
        if ($limits['result'] !== self::INCOMPLETE) {
            return ['ok' => false, 'detail' => 'clamd passes a file it could not read in full ('.$limits['result'].'): set AlertExceedsMax yes'];
        }

        return ['ok' => true, 'detail' => 'EICAR found; a file beyond the limits is reported ('.$limits['signature'].')'];
    }

    /** @param resource $stream */
    private function converse(string $command, $stream, ?int $timeout = null): string
    {
        if ($this->transport !== null) {
            return ($this->transport)($command, $stream, $timeout ?? max(1, (int) config('onhost.custom_iso.scan_timeout_seconds', 900)));
        }
        $timeout ??= max(1, (int) config('onhost.custom_iso.scan_timeout_seconds', 900));
        $socket = @stream_socket_client('tcp://'.config('onhost.storage.clamav.host').':'.(int) config('onhost.storage.clamav.port', 3310), $errno, $error, 5);
        if ($socket === false) {
            throw new RuntimeException('clamd unreachable: '.$error);
        }
        stream_set_timeout($socket, $timeout);
        try {
            fwrite($socket, $command."\0");
            while (! feof($stream)) {
                $chunk = (string) fread($stream, self::CHUNK);
                if ($chunk === '') {
                    break;
                }
                if (fwrite($socket, pack('N', strlen($chunk)).$chunk) === false) {
                    break; // clamd closed the stream (size limit): its reply says why
                }
            }
            @fwrite($socket, pack('N', 0));
            $reply = (string) stream_get_contents($socket);
            if (stream_get_meta_data($socket)['timed_out']) {
                throw new RuntimeException('clamd did not answer in time');
            }
        } finally {
            fclose($socket);
        }

        return $reply;
    }

    /** @return resource */
    private static function memory(string $bytes)
    {
        $stream = fopen('php://memory', 'r+');
        if ($stream === false) {
            throw new RuntimeException('no memory stream');
        }
        fwrite($stream, $bytes);
        rewind($stream);

        return $stream;
    }
}
