<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Onhost\Platform\Ops\PlatformBackup;
use Onhost\Platform\Ops\RestoreDrill;

/*
 * P13 (phase R): the restore drill. The backup counts the rows the drill compares before it dumps; the export copies the dump of
 * a set to a new owner-only file and checks it against the manifest; the record judges what the drill measured on the restored copy
 * and keeps the verdict next to the set and in a pointer the retention never prunes; the doctor row and /metrics read it. The
 * shell half (restore-drill.sh with pg_restore) runs for real in CI (job restore-drill).
 */

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-20 03:00:00', 'UTC')); // first, before anything is written (TASK-0192)
    Http::preventStrayRequests();
    Storage::fake('backups');
    config()->set('onhost.platform_backup.disk', 'backups');
    config()->set('onhost.platform_backup.files_root', storage_path('framework/testing/no-private-files'));
});

/** @return array<string,int> what a good drill of the current database measures */
function drillMeasured(array $override = []): array
{
    $backup = app(PlatformBackup::class)->counts();

    return array_merge(['migrations' => $backup['migrations'], 'users' => $backup['users'], 'invoices' => $backup['invoices'], 'ledger_postings' => $backup['ledger_postings'],
        'ledger_imbalance' => 0, 'unbalanced_transactions' => 0], $override);
}

function drillRow(): array
{
    return app(RestoreDrill::class)->checks()[0];
}

it('counts the rows the drill compares into the manifest, before the dump', function () {
    $this->customerWithOrganization(['email' => 'drill@firma.cz']);
    $set = app(PlatformBackup::class)->run()['set'];
    $manifest = json_decode((string) Storage::disk('backups')->get($set.'/manifest.json'), true);

    expect($manifest['counts'])->toHaveKeys(['migrations', 'users', 'invoices', 'ledger_postings'])
        ->and($manifest['counts']['users'])->toBe(DB::table('users')->count())
        ->and($manifest['counts']['migrations'])->toBeGreaterThan(80);
});

it('exports the dump of the newest set to a new owner-only file, checked against the manifest', function () {
    $set = app(PlatformBackup::class)->run()['set'];
    $to = storage_path('framework/testing/drill-'.uniqid().'.dump');
    $drill = app(RestoreDrill::class);

    $r = $drill->export(null, $to);
    expect($r['set'])->toBe($set)->and($r['driver'])->toBe(DB::connection()->getDriverName())->and(filesize($to))->toBe($r['bytes'])->and($r['counts'])->toHaveKey('users');
    if (DIRECTORY_SEPARATOR === '/') {
        expect(fileperms($to) & 0777)->toBe(0600);
    }
    // an existing file is never overwritten
    expect(fn () => $drill->export(null, $to))->toThrow(RuntimeException::class, 'must not exist yet');
    File::delete($to);

    // a dump that does not match its manifest is refused and leaves no file behind
    $dumpName = collect(Storage::disk('backups')->files($set))->first(fn (string $f) => str_contains($f, '/database.'));
    Storage::disk('backups')->put($dumpName, 'garbage');
    expect(fn () => $drill->export($set, $to))->toThrow(RuntimeException::class, 'checksum differs')->and(file_exists($to))->toBeFalse();

    // a set is a set name, nothing else (no path out of the backup prefix)
    expect(fn () => $drill->export('platform-backups/../../etc', $to))->toThrow(RuntimeException::class, 'not a backup set');
});

it('passes a drill whose restored copy matches the backup and keeps the verdict next to the set and in the pointer', function () {
    $set = app(PlatformBackup::class)->run()['set'];

    $r = app(RestoreDrill::class)->record($set, drillMeasured(), 42, 'onhost_drill_20261020030000');
    expect($r['ok'])->toBeTrue()->and($r['problems'])->toBe([])
        ->and(Storage::disk('backups')->exists($set.'/restore-drill.json'))->toBeTrue()
        ->and(Storage::disk('backups')->exists(RestoreDrill::LATEST))->toBeTrue();

    Cache::flush(); // a deploy flushed the cache: the verdict comes back from the backup disk
    expect(drillRow())->toMatchArray(['check' => 'platform restore drill within 35 days', 'ok' => true, 'blocking' => false])
        ->and(drillRow()['detail'])->toContain($set)->toContain('in 42 s')->toContain('ledger balanced');

    // the retention prune removes old set directories only: the pointer outlives the set it names
    $this->travelTo(now()->addDays(31));
    app(PlatformBackup::class)->run();
    app(PlatformBackup::class)->run();
    expect(Storage::disk('backups')->exists(RestoreDrill::LATEST))->toBeTrue();
});

it('fails a drill on an unbalanced ledger, a missing migration or fewer rows than the backup counted', function () {
    $this->customerWithOrganization(['email' => 'drill2@firma.cz']);
    $set = app(PlatformBackup::class)->run()['set'];
    $drill = app(RestoreDrill::class);
    $counted = app(PlatformBackup::class)->counts();

    $r = $drill->record($set, drillMeasured(['ledger_imbalance' => 5, 'unbalanced_transactions' => 1, 'migrations' => $counted['migrations'] - 1, 'users' => $counted['users'] - 1]), 9, 'onhost_drill_20261020030001');
    expect($r['ok'])->toBeFalse()->and($r['problems'])->toHaveCount(4)
        ->and(implode(' | ', $r['problems']))->toContain('differ by 5')->toContain('1 ledger transaction(s) out of balance')->toContain('migrations')->toContain('users');
    expect(drillRow()['ok'])->toBeFalse()->and(drillRow()['detail'])->toContain('FAILED')->and(drillRow()['remedy'])->toContain('restore-drill.sh');

    // more rows than counted is fine (the dump ran after the count)
    expect($drill->record($set, drillMeasured(['users' => $counted['users'] + 3]), 9, 'onhost_drill_20261020030002')['ok'])->toBeTrue();
});

it('refuses an incomplete measurement, a foreign database name and an unknown set', function () {
    $set = app(PlatformBackup::class)->run()['set'];
    $drill = app(RestoreDrill::class);

    expect(fn () => $drill->record($set, ['migrations' => 1], 1, 'onhost_drill_20261020030000'))->toThrow(RuntimeException::class, 'measurement incomplete')
        ->and(fn () => $drill->record($set, drillMeasured(), 1, 'onhost_live'))->toThrow(RuntimeException::class, 'onhost_drill_')
        ->and(fn () => $drill->record('platform-backups/20990101-000000', drillMeasured(), 1, 'onhost_drill_20261020030000'))->toThrow(RuntimeException::class, 'manifest missing');
    expect(app(RestoreDrill::class)->latest())->toBeNull();
});

it('warns without a drill and after 35 days, in the doctor and as a metric', function () {
    expect(drillRow())->toMatchArray(['ok' => false, 'detail' => 'no restore drill recorded yet']);
    $this->artisan('onhost:doctor')->expectsOutputToContain('platform restore drill within 35 days');
    $metrics = (string) $this->get('/metrics', ['REMOTE_ADDR' => '127.0.0.1'])->assertOk()->getContent();
    expect($metrics)->toContain("onhost_platform_restore_drill_timestamp 0\n")->toContain("onhost_platform_restore_drill_ok 0\n");

    $set = app(PlatformBackup::class)->run()['set'];
    app(RestoreDrill::class)->record($set, drillMeasured(), 30, 'onhost_drill_20261020030000');
    $metrics = (string) $this->get('/metrics', ['REMOTE_ADDR' => '127.0.0.1'])->assertOk()->getContent();
    expect($metrics)->toContain('onhost_platform_restore_drill_timestamp '.now()->getTimestamp()."\n")->toContain("onhost_platform_restore_drill_ok 1\n");

    $this->travelTo(now()->addDays(34));
    expect(drillRow()['ok'])->toBeTrue();
    $this->travelTo(now()->addDays(2));
    expect(drillRow()['ok'])->toBeFalse()->and(drillRow()['detail'])->toContain('more than 35 days ago');
});

it('records through the artisan commands the shell script calls, and exports with --json', function () {
    $set = app(PlatformBackup::class)->run()['set'];
    $to = storage_path('framework/testing/drill-cli-'.uniqid().'.dump');
    $this->artisan('onhost:platform:backup:export-db', ['--to' => $to, '--json' => true])->expectsOutputToContain('"set":"'.$set.'"')->assertExitCode(0);
    File::delete($to);
    $this->artisan('onhost:platform:backup:export-db')->assertExitCode(2);

    $m = drillMeasured();
    $measured = implode(',', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($m), $m));
    $this->artisan('onhost:platform:backup:drill-record', ['set' => $set, '--measured' => $measured, '--seconds' => 12, '--database' => 'onhost_drill_20261020030000'])
        ->expectsOutputToContain('OK '.$set)->assertExitCode(0);
    $this->artisan('onhost:platform:backup:drill-record', ['set' => $set, '--measured' => str_replace('ledger_imbalance=0', 'ledger_imbalance=-7', $measured), '--database' => 'onhost_drill_20261020030001'])
        ->expectsOutputToContain('differ by -7')->assertExitCode(1);
    $this->artisan('onhost:platform:backup:drill-record', ['set' => $set, '--measured' => 'users=1;drop', '--database' => 'onhost_drill_20261020030002'])->assertExitCode(2);
});
