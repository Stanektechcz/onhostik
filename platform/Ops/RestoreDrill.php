<?php

declare(strict_types=1);

namespace Onhost\Platform\Ops;

use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * P13 (phase R): a backup is only as good as a restore that was tried. `verify()` of PlatformBackup proves the set is intact
 * and that pg_restore can list it; the drill proves it restores. `infra/aapanel/restore-drill.sh` (root, monthly) exports the
 * dump of the newest set through {@see export()} (any backup disk, an S3 bucket included), restores it into a throw-away
 * database `onhost_drill_<time>`, measures it (migrations, users, documents, ledger postings, the double-entry balance) and drops
 * it again; {@see record()} judges the measurement against the counts the backup wrote into its manifest and keeps the verdict
 * next to the set (`<set>/restore-drill.json`, like `verified.json`) and in one pointer outside the sets, which the retention
 * prune never touches. The doctor row and the metric read the pointer: no drill, a failed one or one older than 35 days warns.
 */
final class RestoreDrill
{
    public const CHECK = 'platform restore drill within 35 days';

    public const MAX_AGE_DAYS = 35;

    public const CACHE_KEY = 'onhost:platform:restore-drill';

    /** Outside the sets directory: prune() deletes directories under PlatformBackup::PREFIX only. */
    public const LATEST = PlatformBackup::PREFIX.'-restore-drill.json';

    /** What the drill measures on the restored copy, all integers. */
    public const MEASURES = ['migrations', 'users', 'invoices', 'ledger_postings', 'ledger_imbalance', 'unbalanced_transactions'];

    private const SET_PATTERN = '#^'.PlatformBackup::PREFIX.'/\d{8}-\d{6}$#';

    public function __construct(private readonly PlatformBackup $backup) {}

    /**
     * Copies the database dump of a set (the newest by default) to a new local file, readable by its owner only, and checks
     * its size and SHA-256 against the manifest. An existing file is never overwritten.
     *
     * @return array{set:string, file:string, driver:string, bytes:int, counts:array<string,int>|null}
     */
    public function export(?string $set, string $to): array
    {
        $set ??= $this->backup->latestSet();
        if ($set === null) {
            throw new RuntimeException('no backup set found');
        }
        $manifest = $this->manifest($set);
        $name = null;
        foreach (array_keys((array) ($manifest['files'] ?? [])) as $file) {
            if (str_starts_with((string) $file, 'database.')) {
                $name = (string) $file;
            }
        }
        if ($name === null) {
            throw new RuntimeException("{$set}: the manifest names no database dump");
        }
        $expected = (array) $manifest['files'][$name];
        if (! is_dir(dirname($to))) {
            throw new RuntimeException('the target directory does not exist: '.dirname($to));
        }
        $target = @fopen($to, 'xb'); // x: refuses an existing file (or a symlink planted in its place)
        if ($target === false) {
            throw new RuntimeException("cannot create {$to} (it must not exist yet)");
        }
        @chmod($to, 0600); // the whole database: the owner only, before a byte is written
        $stream = $this->backup->disk()->readStream($set.'/'.$name);
        if (! is_resource($stream)) {
            fclose($target);
            @unlink($to);
            throw new RuntimeException("{$set}/{$name}: unreadable");
        }
        stream_copy_to_stream($stream, $target);
        fclose($stream);
        fclose($target);
        clearstatcache(true, $to);
        $bytes = (int) filesize($to);
        if ($bytes !== (int) ($expected['bytes'] ?? -1) || hash_file('sha256', $to) !== (string) ($expected['sha256'] ?? '')) {
            @unlink($to);
            throw new RuntimeException("{$set}/{$name}: size or checksum differs from the manifest");
        }

        return ['set' => $set, 'file' => $to, 'driver' => (string) ($manifest['database'] ?? ''), 'bytes' => $bytes, 'counts' => self::counts($manifest)];
    }

    /**
     * Judges what the drill measured on the restored copy and keeps the verdict.
     *
     * @param  array<string,int>  $measured  every key of MEASURES
     * @return array{set:string, at:string, ok:bool, problems:list<string>, measured:array<string,int>, expected:array<string,int>|null, seconds:int, database:string}
     */
    public function record(string $set, array $measured, int $seconds, string $database): array
    {
        $manifest = $this->manifest($set);
        $missing = array_diff(self::MEASURES, array_keys($measured));
        if ($missing !== []) {
            throw new RuntimeException('measurement incomplete: '.implode(', ', $missing));
        }
        if (preg_match('/^onhost_drill_[0-9]{8,14}$/', $database) !== 1) {
            throw new RuntimeException('the drill database must be named onhost_drill_<time>');
        }
        $expected = self::counts($manifest);
        $problems = [];
        if ($measured['migrations'] <= 0) {
            $problems[] = 'no migrations in the restored copy';
        } elseif ($expected !== null && isset($expected['migrations']) && $measured['migrations'] !== $expected['migrations']) {
            $problems[] = "migrations {$measured['migrations']} ≠ {$expected['migrations']} when the backup was made";
        }
        foreach (['users', 'invoices', 'ledger_postings'] as $table) { // counted just before the dump: the dump holds at least as many
            if ($expected !== null && isset($expected[$table]) && $measured[$table] < $expected[$table]) {
                $problems[] = "{$table} {$measured[$table]} < {$expected[$table]} counted before the dump";
            }
        }
        if ($measured['ledger_imbalance'] !== 0) {
            $problems[] = "ledger debits and credits differ by {$measured['ledger_imbalance']} minor units";
        }
        if ($measured['unbalanced_transactions'] !== 0) {
            $problems[] = "{$measured['unbalanced_transactions']} ledger transaction(s) out of balance";
        }
        $result = [
            'set' => $set, 'at' => now()->toIso8601String(), 'ok' => $problems === [], 'problems' => $problems,
            'measured' => array_intersect_key($measured, array_flip(self::MEASURES)), 'expected' => $expected, 'seconds' => max(0, $seconds), 'database' => $database,
        ];
        $json = (string) json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->backup->disk()->put($set.'/restore-drill.json', $json);
        $this->backup->disk()->put(self::LATEST, $json);
        cache()->forever(self::CACHE_KEY, $result);

        return $result;
    }

    /** @return array<string,mixed>|null the newest drill (cache, else the pointer on the backup disk) */
    public function latest(): ?array
    {
        $cached = cache()->get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }
        $disk = $this->backup->disk();
        $stored = $disk->exists(self::LATEST) ? json_decode((string) $disk->get(self::LATEST), true) : null;

        return is_array($stored) ? $stored : null;
    }

    /** @return list<array{area:string, check:string, ok:bool, detail:string, blocking:bool, remedy:string}> */
    public function checks(): array
    {
        $remedy = 'run infra/aapanel/restore-drill.sh as root (monthly; docs/runbooks/backups.md "Restore drill")';
        $latest = $this->latest();
        if ($latest === null) {
            return [$this->row(false, 'no restore drill recorded yet', $remedy)];
        }
        $at = CarbonImmutable::parse((string) ($latest['at'] ?? '1970-01-01'));
        $when = 'set '.($latest['set'] ?? '?').' restored '.$at->toDateString();
        if (($latest['ok'] ?? false) !== true) {
            return [$this->row(false, $when.' and FAILED: '.implode('; ', (array) ($latest['problems'] ?? [])), 'read the problems, fix the backup, then '.$remedy)];
        }
        if ($at->lt(now()->subDays(self::MAX_AGE_DAYS))) {
            return [$this->row(false, $when.', more than '.self::MAX_AGE_DAYS.' days ago', $remedy)];
        }
        $m = (array) ($latest['measured'] ?? []);

        return [$this->row(true, $when.' in '.(int) ($latest['seconds'] ?? 0).' s: '.(int) ($m['migrations'] ?? 0).' migrations, '.(int) ($m['users'] ?? 0).' users, '
            .(int) ($m['invoices'] ?? 0).' documents, ledger balanced')];
    }

    /** @return array<string,mixed> */
    private function manifest(string $set): array
    {
        if (preg_match(self::SET_PATTERN, $set) !== 1) {
            throw new RuntimeException('not a backup set: '.$set);
        }
        $manifest = json_decode((string) ($this->backup->disk()->get($set.'/manifest.json') ?? ''), true);
        if (! is_array($manifest)) {
            throw new RuntimeException("{$set}: manifest missing or unreadable");
        }

        return $manifest;
    }

    /** @return array<string,int>|null the counts the backup took before its dump (sets made before P13 have none) */
    private static function counts(array $manifest): ?array
    {
        $counts = $manifest['counts'] ?? null;

        return is_array($counts) ? array_map('intval', $counts) : null;
    }

    /** @return array{area:string, check:string, ok:bool, detail:string, blocking:bool, remedy:string} */
    private function row(bool $ok, string $detail, string $remedy = ''): array
    {
        return ['area' => 'storage', 'check' => self::CHECK, 'ok' => $ok, 'detail' => $detail, 'blocking' => false, 'remedy' => $ok ? '' : $remedy];
    }
}
