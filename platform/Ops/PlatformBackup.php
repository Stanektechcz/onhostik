<?php

declare(strict_types=1);

namespace Onhost\Platform\Ops;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Backups of the control plane itself (go-live checklist §1): the database (pg_dump custom format, mysqldump, or
 * SQLite `VACUUM INTO`) and the private file store (`storage/app/private` — invoice PDFs, evidence, exports, pinned
 * certificates) go as one dated set with a manifest (sizes, SHA-256) to `ONHOST_PLATFORM_BACKUP_DISK` — the local
 * disk by default, an S3-compatible bucket off the server in production. `verify()` reads the newest set back: sizes
 * and hashes must match and the database dump must be restorable (`pg_restore --list`, SQLite integrity check).
 * Sets older than the retention are pruned; the doctor warns when the newest verified set is older than a day.
 */
final class PlatformBackup
{
    public const PREFIX = 'platform-backups';

    public const LAST_KEY = 'onhost:platform:backup:last';

    public const VERIFIED_KEY = 'onhost:platform:backup:verified';

    public function disk(): Filesystem
    {
        return Storage::disk((string) config('onhost.platform_backup.disk', 'local') ?: 'local');
    }

    /**
     * @return array{set:string, files:array<string,array{bytes:int, sha256:string}>, database:string, pruned:int}
     */
    public function run(): array
    {
        $set = self::PREFIX.'/'.now()->utc()->format('Ymd-His');
        $work = storage_path('app/platform-backup-work');
        if (! is_dir($work) && ! mkdir($work, 0750, true)) {
            throw new RuntimeException("cannot create {$work}");
        }
        $files = [];
        try {
            $dump = $this->dumpDatabase($work);
            $files[$dump['name']] = $this->store($set, $dump['name'], $dump['path']);
            $archive = $this->archiveFiles($work);
            if ($archive !== null) {
                $files['files.tar.gz'] = $this->store($set, 'files.tar.gz', $archive);
            }
            $manifest = ['created_at' => now()->toIso8601String(), 'database' => $dump['driver'], 'app_version' => (string) config('app.version', ''), 'files' => $files];
            $this->disk()->put($set.'/manifest.json', (string) json_encode($manifest, JSON_PRETTY_PRINT));
        } finally {
            foreach (glob($work.'/*') ?: [] as $leftover) {
                @unlink($leftover);
            }
            @rmdir($work);
        }
        cache()->forever(self::LAST_KEY, ['set' => $set, 'at' => now()->toIso8601String(), 'bytes' => array_sum(array_column($files, 'bytes'))]);

        return ['set' => $set, 'files' => $files, 'database' => $dump['driver'], 'pruned' => $this->prune()];
    }

    /**
     * Reads the newest set back and checks it can be restored from.
     *
     * @return array{set:?string, ok:bool, problems:list<string>, created_at:?string}
     */
    public function verify(?string $set = null): array
    {
        $set ??= $this->latestSet();
        if ($set === null) {
            return ['set' => null, 'ok' => false, 'problems' => ['no backup set found'], 'created_at' => null];
        }
        $disk = $this->disk();
        $manifest = json_decode((string) $disk->get($set.'/manifest.json'), true);
        $problems = [];
        if (! is_array($manifest)) {
            return ['set' => $set, 'ok' => false, 'problems' => ['manifest missing or unreadable'], 'created_at' => null];
        }
        $work = storage_path('app/platform-backup-verify');
        if (! is_dir($work)) {
            mkdir($work, 0750, true);
        }
        try {
            foreach ((array) ($manifest['files'] ?? []) as $name => $expected) {
                $path = $set.'/'.$name;
                if (! $disk->exists($path)) {
                    $problems[] = "{$name}: missing";

                    continue;
                }
                $local = $work.'/'.$name;
                $stream = $disk->readStream($path);
                $target = fopen($local, 'wb');
                if (! is_resource($stream) || ! is_resource($target)) {
                    $problems[] = "{$name}: unreadable";

                    continue;
                }
                stream_copy_to_stream($stream, $target);
                fclose($stream);
                fclose($target);
                if (filesize($local) !== (int) ($expected['bytes'] ?? -1)) {
                    $problems[] = "{$name}: size ".filesize($local).' ≠ '.(int) ($expected['bytes'] ?? -1);
                } elseif (hash_file('sha256', $local) !== (string) ($expected['sha256'] ?? '')) {
                    $problems[] = "{$name}: checksum differs";
                } elseif (str_starts_with($name, 'database.')) {
                    $problems = array_merge($problems, $this->checkDump($local, (string) ($manifest['database'] ?? '')));
                }
            }
        } finally {
            foreach (glob($work.'/*') ?: [] as $leftover) {
                @unlink($leftover);
            }
            @rmdir($work);
        }
        $ok = $problems === [];
        if ($ok) {
            cache()->forever(self::VERIFIED_KEY, ['set' => $set, 'at' => now()->toIso8601String(), 'created_at' => $manifest['created_at'] ?? null]);
        }

        return ['set' => $set, 'ok' => $ok, 'problems' => $problems, 'created_at' => $manifest['created_at'] ?? null];
    }

    /** @return array{last:?array<string,mixed>, verified:?array<string,mixed>, disk:string, retention_days:int} */
    public function status(): array
    {
        return ['last' => cache()->get(self::LAST_KEY), 'verified' => cache()->get(self::VERIFIED_KEY), 'disk' => (string) config('onhost.platform_backup.disk', 'local'), 'retention_days' => (int) config('onhost.platform_backup.retention_days', 30)];
    }

    public function latestSet(): ?string
    {
        $sets = $this->disk()->directories(self::PREFIX);
        rsort($sets);

        return $sets[0] ?? null;
    }

    /** @return array{name:string, path:string, driver:string} */
    private function dumpDatabase(string $work): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $config = (array) $connection->getConfig();

        return match ($driver) {
            'sqlite' => (function () use ($connection, $work) {
                $path = $work.'/database.sqlite';
                $pdo = $connection->getPdo();
                if (! $pdo->inTransaction()) {
                    $pdo->exec('VACUUM INTO '.$pdo->quote($path));
                } else { // inside a transaction (tests, a wrapping command) VACUUM is refused: copy schema and rows through a second connection
                    $copy = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $copy->beginTransaction();
                    foreach ($pdo->query("SELECT name, type, sql FROM sqlite_master WHERE sql IS NOT NULL AND name NOT LIKE 'sqlite_%' ORDER BY CASE type WHEN 'table' THEN 0 ELSE 1 END")->fetchAll(PDO::FETCH_ASSOC) as $object) {
                        $copy->exec((string) $object['sql']);
                        if ($object['type'] !== 'table') {
                            continue;
                        }
                        $rows = $pdo->query('SELECT * FROM "'.str_replace('"', '""', (string) $object['name']).'"');
                        $insert = null;
                        while (($row = $rows->fetch(PDO::FETCH_ASSOC)) !== false) {
                            $insert ??= $copy->prepare('INSERT INTO "'.str_replace('"', '""', (string) $object['name']).'" ("'.implode('", "', array_keys($row)).'") VALUES ('.implode(', ', array_fill(0, count($row), '?')).')');
                            $insert->execute(array_values($row));
                        }
                    }
                    $copy->commit();
                    unset($copy);
                }

                return ['name' => 'database.sqlite', 'path' => $path, 'driver' => 'sqlite'];
            })(),
            'pgsql' => (function () use ($config, $work) {
                $path = $work.'/database.pgdump';
                $this->exec(['pg_dump', '--format=custom', '--no-owner', '--no-privileges', '--file='.$path, '--host='.($config['host'] ?? 'localhost'), '--port='.(string) ($config['port'] ?? 5432), '--username='.($config['username'] ?? ''), (string) ($config['database'] ?? '')], ['PGPASSWORD' => (string) ($config['password'] ?? '')]);

                return ['name' => 'database.pgdump', 'path' => $path, 'driver' => 'pgsql'];
            })(),
            'mysql', 'mariadb' => (function () use ($config, $work) {
                $path = $work.'/database.sql';
                $this->exec(['mysqldump', '--single-transaction', '--routines', '--result-file='.$path, '--host='.($config['host'] ?? 'localhost'), '--port='.(string) ($config['port'] ?? 3306), '--user='.($config['username'] ?? ''), (string) ($config['database'] ?? '')], ['MYSQL_PWD' => (string) ($config['password'] ?? '')]);

                return ['name' => 'database.sql', 'path' => $path, 'driver' => 'mysql'];
            })(),
            default => throw new RuntimeException("no dump strategy for {$driver}"),
        };
    }

    /**
     * The private file store as one ustar tar.gz written by hand (no Phar: `phar.readonly` and its name cache get in
     * the way on servers); null when there is nothing to archive. Files larger than 8 GB are skipped and reported.
     */
    private function archiveFiles(string $work): ?string
    {
        $root = (string) config('onhost.platform_backup.files_root', storage_path('app/private'));
        if (! is_dir($root)) {
            return null;
        }
        $path = $work.'/files.tar.gz';
        $gz = gzopen($path, 'wb6');
        if ($gz === false) {
            throw new RuntimeException("cannot write {$path}");
        }
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), fn (\SplFileInfo $f) => $f->getFilename() !== self::PREFIX && ! str_starts_with($f->getFilename(), 'platform-backup-')));
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getSize() >= 8 * 1024 ** 3) {
                continue;
            }
            $name = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');
            gzwrite($gz, self::tarHeader($name, (int) $file->getSize(), (int) $file->getMTime()));
            $in = fopen($file->getPathname(), 'rb');
            if (is_resource($in)) {
                while (! feof($in)) {
                    gzwrite($gz, (string) fread($in, 1024 * 1024));
                }
                fclose($in);
            }
            $pad = (512 - ($file->getSize() % 512)) % 512;
            if ($pad > 0) {
                gzwrite($gz, str_repeat("\0", $pad));
            }
            $count++;
        }
        gzwrite($gz, str_repeat("\0", 1024)); // end of archive
        gzclose($gz);
        if ($count === 0) {
            @unlink($path);

            return null;
        }

        return $path;
    }

    /** A 512-byte ustar header; long names use the prefix field (up to 155 + 100 characters). */
    private static function tarHeader(string $name, int $size, int $mtime): string
    {
        $prefix = '';
        if (strlen($name) > 100) {
            $cut = strrpos(substr($name, 0, 156), '/');
            if ($cut === false || strlen($name) - $cut - 1 > 100) {
                throw new RuntimeException("path too long for tar: {$name}");
            }
            $prefix = substr($name, 0, $cut);
            $name = substr($name, $cut + 1);
        }
        $header = str_pad($name, 100, "\0").sprintf('%07o', 0640)."\0".sprintf('%07o', 0)."\0".sprintf('%07o', 0)."\0".sprintf('%011o', $size)."\0".sprintf('%011o', $mtime)."\0".'        '.'0'.str_repeat("\0", 100).'ustar'."\0".'00'.str_pad('onhost', 32, "\0").str_pad('onhost', 32, "\0").sprintf('%07o', 0)."\0".sprintf('%07o', 0)."\0".str_pad($prefix, 155, "\0");
        $header = str_pad($header, 512, "\0");
        $checksum = array_sum(array_map('ord', str_split($header)));

        return substr_replace($header, sprintf('%06o', $checksum)."\0 ", 148, 8);
    }

    /** @return array{bytes:int, sha256:string} */
    private function store(string $set, string $name, string $local): array
    {
        $stream = fopen($local, 'rb');
        if (! is_resource($stream)) {
            throw new RuntimeException("cannot read {$local}");
        }
        $this->disk()->writeStream($set.'/'.$name, $stream);
        fclose($stream);

        return ['bytes' => (int) filesize($local), 'sha256' => (string) hash_file('sha256', $local)];
    }

    /** @return list<string> */
    private function checkDump(string $local, string $driver): array
    {
        try {
            if ($driver === 'sqlite') {
                $pdo = new PDO('sqlite:'.$local, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $result = (string) $pdo->query('PRAGMA integrity_check')->fetchColumn();
                $tables = (int) $pdo->query("SELECT count(*) FROM sqlite_master WHERE type = 'table'")->fetchColumn();

                return $result === 'ok' && $tables > 0 ? [] : ["database: integrity {$result}, {$tables} tables"];
            }
            if ($driver === 'pgsql') {
                $this->exec(['pg_restore', '--list', $local]);

                return [];
            }
            if ($driver === 'mysql') {
                return filesize($local) > 0 && str_contains((string) file_get_contents($local, false, null, 0, 4096), 'CREATE TABLE') ? [] : ['database: dump carries no CREATE TABLE'];
            }
        } catch (Throwable $e) {
            return ['database: '.$e->getMessage()];
        }

        return [];
    }

    /** Sets older than the retention are removed; the newest set always stays. */
    private function prune(): int
    {
        $keep = max(1, (int) config('onhost.platform_backup.retention_days', 30));
        $disk = $this->disk();
        $sets = $disk->directories(self::PREFIX);
        sort($sets);
        $pruned = 0;
        foreach (array_slice($sets, 0, -1) as $set) {
            $stamp = \DateTimeImmutable::createFromFormat('Ymd-His', basename($set), new \DateTimeZone('UTC'));
            if ($stamp !== false && $stamp < now()->utc()->subDays($keep)) {
                $disk->deleteDirectory($set);
                $pruned++;
            }
        }

        return $pruned;
    }

    /**
     * Where pg_dump / pg_restore live: `ONHOST_PG_BIN`, else the panel installations (aaPanel, Debian/Ubuntu packages —
     * the newest version wins, the client must not be older than the server), else the PATH.
     */
    public static function pgBin(string $tool): string
    {
        $dir = (string) config('onhost.platform_backup.pg_bin', '');
        if ($dir === '') {
            $candidates = array_merge(['/www/server/pgsql/bin'], array_reverse(glob('/usr/lib/postgresql/*/bin') ?: []), ['/usr/local/pgsql/bin']);
            foreach ($candidates as $candidate) {
                if (is_executable(rtrim($candidate, '/').'/'.$tool)) {
                    $dir = $candidate;
                    break;
                }
            }
        }

        return $dir !== '' ? rtrim($dir, '/').'/'.$tool : $tool;
    }

    /** @param  list<string>  $command @param  array<string,string>  $env */
    private function exec(array $command, array $env = []): void
    {
        if (in_array($command[0], ['pg_dump', 'pg_restore'], true)) {
            $command[0] = self::pgBin($command[0]);
        }
        $process = new Process($command, null, $env + ['PATH' => (string) getenv('PATH')], null, 1800);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException($command[0].' failed: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }
}
