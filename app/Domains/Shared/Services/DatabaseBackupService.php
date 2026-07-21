<?php

declare(strict_types=1);

namespace App\Domains\Shared\Services;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Offsite database backups (audit INFRA #7).
 *
 * The `backup-s3` disk existed but nothing wrote to it — a hosting platform
 * with no offsite DB dump is one disk failure away from losing every customer,
 * invoice and order. This dumps the database, gzips it, and uploads it to the
 * configured disk, keeping a rolling window.
 *
 * The dump credentials are passed to mysqldump through a temporary
 * defaults-file (chmod 600), never on the command line — a password in argv is
 * visible to every process via `ps`, which is exactly the kind of leak the
 * secret-redaction rules exist to prevent.
 */
final class DatabaseBackupService
{
    /**
     * @return array{stored: ?string, skipped: ?string, pruned: int, bytes: int}
     */
    public function run(string $disk, int $keepDays): array
    {
        $connection = (string) config('database.default');
        $config     = (array) config("database.connections.{$connection}");
        $driver     = (string) ($config['driver'] ?? '');

        $dump = match ($driver) {
            'mysql', 'mariadb' => $this->dumpMysql($config),
            'sqlite'           => $this->dumpSqlite($config),
            default            => ['path' => null, 'skip' => "Unsupported driver [{$driver}] for backup."],
        };

        if ($dump['path'] === null) {
            return ['stored' => null, 'skipped' => $dump['skip'], 'pruned' => 0, 'bytes' => 0];
        }

        // gzip the dump before it leaves the box.
        $raw     = (string) file_get_contents($dump['path']);
        $gzipped = (string) gzencode($raw, 6);
        @unlink($dump['path']);

        $name   = $this->safeName((string) ($config['database'] ?? 'database'));
        $remote = 'db/' . $name . '-' . now()->format('Y-m-d-His') . $dump['ext'] . '.gz';

        Storage::disk($disk)->put($remote, $gzipped);

        $pruned = $this->prune($disk, $keepDays);

        return ['stored' => $remote, 'skipped' => null, 'pruned' => $pruned, 'bytes' => strlen($gzipped)];
    }

    /**
     * Delete backups older than the retention window.
     */
    public function prune(string $disk, int $keepDays): int
    {
        if ($keepDays <= 0) {
            return 0;
        }

        $cutoff  = now()->subDays($keepDays)->getTimestamp();
        $deleted = 0;
        $fs      = Storage::disk($disk);

        foreach ($fs->files('db') as $file) {
            if ($fs->lastModified($file) < $cutoff) {
                $fs->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{path: ?string, ext: string, skip: ?string}
     */
    private function dumpMysql(array $config): array
    {
        $binary = (new ExecutableFinder())->find('mysqldump');

        if ($binary === null) {
            return ['path' => null, 'ext' => '.sql', 'skip' => 'mysqldump binary not found on PATH.'];
        }

        // Password via a temp defaults-file, chmod 600 — never in argv.
        $defaults = tempnam(sys_get_temp_dir(), 'mysqldump_');
        file_put_contents($defaults, sprintf(
            "[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n",
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? '3306',
            $config['username'] ?? '',
            $config['password'] ?? '',
        ));
        @chmod($defaults, 0600);

        $target = tempnam(sys_get_temp_dir(), 'dbdump_') . '.sql';

        $process = new Process([
            $binary,
            '--defaults-extra-file=' . $defaults,
            '--single-transaction',
            '--quick',
            '--routines',
            '--no-tablespaces',
            (string) ($config['database'] ?? ''),
        ]);
        $process->setTimeout(1800);

        $out = fopen($target, 'w');
        $process->run(function ($type, $buffer) use ($out): void {
            if ($type === Process::OUT && is_resource($out)) {
                fwrite($out, $buffer);
            }
        });
        if (is_resource($out)) {
            fclose($out);
        }

        // The file held the password for a moment — remove it immediately.
        @unlink($defaults);

        if (! $process->isSuccessful()) {
            @unlink($target);

            // Deliberately do NOT include the process output — it can echo the
            // connection string. A short, safe message only.
            return ['path' => null, 'ext' => '.sql', 'skip' => 'mysqldump failed (exit ' . $process->getExitCode() . ').'];
        }

        return ['path' => $target, 'ext' => '.sql', 'skip' => null];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{path: ?string, ext: string, skip: ?string}
     */
    private function dumpSqlite(array $config): array
    {
        $database = (string) ($config['database'] ?? '');

        // An in-memory database has nothing on disk to copy.
        if ($database === '' || $database === ':memory:' || ! is_file($database)) {
            return ['path' => null, 'ext' => '.sqlite', 'skip' => 'In-memory or missing SQLite database — nothing to back up.'];
        }

        $target = tempnam(sys_get_temp_dir(), 'dbdump_') . '.sqlite';
        copy($database, $target);

        return ['path' => $target, 'ext' => '.sqlite', 'skip' => null];
    }

    private function safeName(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '_', $name) ?: 'database';
    }
}
