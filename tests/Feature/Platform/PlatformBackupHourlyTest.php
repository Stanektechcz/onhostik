<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Onhost\Platform\Ops\PlatformBackup;

/*
 * A2 (owner decision 2026-10-15, RPO 1 h): `onhost:platform:backup --db-only` takes the database alone every hour as
 * `platform-backups/<Ymd-His>-db`, kept 48 h on the same backup disk; it shares a lock with the nightly full set, never
 * becomes "the newest full set" for verify/export-db, and its time feeds the doctor row and onhost_platform_backup_db_timestamp.
 */

function a2HourlyBackupDisk(): string
{
    Storage::fake('a2-backups');
    config()->set('onhost.platform_backup.disk', 'a2-backups');
    $files = storage_path('framework/testing/a2-files-'.uniqid());
    File::ensureDirectoryExists($files);
    File::put($files.'/a.txt', 'a');
    config()->set('onhost.platform_backup.files_root', $files);

    return $files;
}

it('takes a database-only set without the file archive and keeps it apart from the full sets', function () {
    $files = a2HourlyBackupDisk();
    $backup = app(PlatformBackup::class);
    $this->travelTo(Carbon::parse('2026-10-20 02:15:00', 'UTC'));
    $full = $backup->run();
    $this->travelTo(Carbon::parse('2026-10-20 03:40:00', 'UTC'));
    $db = $backup->run(true);

    expect($db['set'])->toBe('platform-backups/20261020-034000-db')
        ->and(array_keys($db['files']))->toHaveCount(1)->and(array_keys($db['files'])[0])->toStartWith('database.') // .sqlite here, .pgdump on PostgreSQL
        ->and(array_keys($full['files']))->toContain('files.tar.gz')
        ->and($backup->latestSet())->toBe($full['set'])
        ->and($backup->latestSet(true))->toBe($db['set'])
        ->and($backup->verify($db['set'])['ok'])->toBeTrue();

    $status = $backup->status();
    expect($status['last']['set'])->toBe($full['set'])->and($status['last_db']['set'])->toBe($db['set']);
    Cache::forget(PlatformBackup::LAST_DB_KEY); // a flushed cache reads the time from the newest set's name
    expect($backup->status()['last_db']['at'])->toBe('2026-10-20T03:40:00+00:00');
    File::deleteDirectory($files);
});

it('prunes database-only sets after 48 hours and keeps the newest of each kind', function () {
    $files = a2HourlyBackupDisk();
    $backup = app(PlatformBackup::class);
    $this->travelTo(Carbon::parse('2026-10-20 02:15:00', 'UTC'));
    $full = $backup->run();
    $this->travelTo(Carbon::parse('2026-10-20 03:40:00', 'UTC'));
    $old = $backup->run(true);
    $this->travelTo(Carbon::parse('2026-10-22 03:39:00', 'UTC'));
    $kept = $backup->run(true);
    expect(Storage::disk('a2-backups')->directories(PlatformBackup::PREFIX))->toContain($old['set']);

    $this->travelTo(Carbon::parse('2026-10-22 04:40:00', 'UTC'));
    $newest = $backup->run(true);
    $sets = Storage::disk('a2-backups')->directories(PlatformBackup::PREFIX);
    expect($newest['pruned'])->toBe(1)
        ->and($sets)->not->toContain($old['set'])
        ->and($sets)->toContain($kept['set'], $newest['set'], $full['set']);
    File::deleteDirectory($files);
});

it('runs hourly with --db-only and skips while another platform backup holds the lock', function () {
    $files = a2HourlyBackupDisk();
    $lock = Cache::lock(PlatformBackup::LOCK_KEY, 60);
    expect($lock->get())->toBeTrue();
    expect(Artisan::call('onhost:platform:backup', ['--db-only' => true, '--no-ansi' => true]))->toBe(0)
        ->and(Artisan::output())->toContain('another platform backup is running')
        ->and(Storage::disk('a2-backups')->directories(PlatformBackup::PREFIX))->toBe([]);
    $lock->release();

    expect(Artisan::call('onhost:platform:backup', ['--db-only' => true, '--no-ansi' => true]))->toBe(0)
        ->and(Storage::disk('a2-backups')->directories(PlatformBackup::PREFIX)[0] ?? '')->toEndWith('-db');
    File::deleteDirectory($files);
});
