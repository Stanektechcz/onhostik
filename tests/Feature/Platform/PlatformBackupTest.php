<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\Models\LegalEntity;
use Onhost\Platform\Ops\PlatformBackup;

/*
 * Backups of the control plane itself (go-live checklist §1): a dated set with the database dump and the private
 * files lands on the backup disk with a manifest, verification reads it back (sizes, hashes, dump integrity), old
 * sets are pruned, the doctor knows the age of the last verified set; the production preparation purges the
 * development accounts and refuses to write a placeholder legal entity.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

it('backs up the database and private files, verifies the set and prunes old ones', function () {
    Storage::fake('backups');
    config()->set('onhost.platform_backup.disk', 'backups');
    config()->set('onhost.platform_backup.retention_days', 7);
    $files = storage_path('framework/testing/private-'.uniqid());
    File::ensureDirectoryExists($files.'/invoices/2026');
    File::put($files.'/invoices/2026/FV-1.pdf', '%PDF-1.4 test');
    config()->set('onhost.platform_backup.files_root', $files);
    [$user] = $this->customerWithOrganization(['email' => 'zaloha@firma.cz']);

    $backup = app(PlatformBackup::class);
    $r = $backup->run();
    $driver = DB::connection()->getDriverName();
    $dumpName = ['sqlite' => 'database.sqlite', 'pgsql' => 'database.pgdump'][$driver] ?? 'database.sql';
    expect($r['database'])->toBe($driver === 'mariadb' ? 'mysql' : $driver)->and(array_keys($r['files']))->toBe([$dumpName, 'files.tar.gz'])->and($r['pruned'])->toBe(0);
    $disk = Storage::disk('backups');
    expect($disk->exists($r['set'].'/manifest.json'))->toBeTrue()->and($disk->exists($r['set'].'/'.$dumpName))->toBeTrue();
    $manifest = json_decode((string) $disk->get($r['set'].'/manifest.json'), true);
    expect($manifest['files'][$dumpName]['bytes'])->toBeGreaterThan(1000)->and($manifest['files']['files.tar.gz']['sha256'])->toHaveLength(64);
    if ($driver === 'sqlite') { // the dump carries the data (pg_dump's custom format is checked by pg_restore --list in verify())
        $dump = storage_path('framework/testing/dump-check.sqlite');
        File::put($dump, (string) $disk->get($r['set'].'/database.sqlite'));
        $pdo = new PDO('sqlite:'.$dump);
        expect((int) $pdo->query("SELECT count(*) FROM users WHERE email = 'zaloha@firma.cz'")->fetchColumn())->toBe(1);
        unset($pdo);
        File::delete($dump);
    }

    $v = $backup->verify();
    expect($v)->toMatchArray(['set' => $r['set'], 'ok' => true, 'problems' => []])->and($backup->status()['verified']['set'])->toBe($r['set']);
    $this->artisan('onhost:doctor')->expectsOutputToContain('platform backup verified within 26 h');

    // a tampered dump fails verification; an old set is pruned by the next run, the newest never
    $disk->put($r['set'].'/'.$dumpName, 'garbage');
    expect($backup->verify()['problems'][0])->toContain($dumpName);
    $old = PlatformBackup::PREFIX.'/'.now()->utc()->subDays(9)->format('Ymd-His');
    $disk->put($old.'/manifest.json', '{}');
    $again = $backup->run();
    expect($again['pruned'])->toBe(1)->and($disk->exists($old.'/manifest.json'))->toBeFalse()->and($disk->exists($r['set'].'/manifest.json'))->toBeTrue();
    File::deleteDirectory($files);
    expect($user->exists)->toBeTrue();
});

it('prepares production: purges the development accounts and refuses a placeholder legal entity', function () {
    foreach (['demo@onhost.cz', 'admin@onhost.cz'] as $email) {
        User::factory()->create(['email' => $email]);
    }
    $this->artisan('onhost:production:prepare --purge-dev-accounts --yes')->expectsOutputToContain('Deleted 2 development account(s)');
    expect(User::query()->whereIn('email', ['demo@onhost.cz', 'admin@onhost.cz'])->count())->toBe(0);
    $this->artisan('onhost:production:prepare --legal')->expectsOutputToContain('Legal entity variables missing')->assertExitCode(1);
    expect(LegalEntity::query()->count())->toBe(0);
});

it('writes a tar.gz that tar itself reads back', function () {
    Storage::fake('backups');
    config()->set('onhost.platform_backup.disk', 'backups');
    $files = storage_path('framework/testing/private-'.uniqid());
    File::ensureDirectoryExists($files.'/deep/'.str_repeat('d', 60).'/'.str_repeat('e', 60));
    File::put($files.'/a.txt', str_repeat('A', 1000));
    File::put($files.'/deep/'.str_repeat('d', 60).'/'.str_repeat('e', 60).'/long-name-'.str_repeat('x', 40).'.bin', random_bytes(3000));
    config()->set('onhost.platform_backup.files_root', $files);
    $r = app(PlatformBackup::class)->run();
    $local = storage_path('framework/testing/check.tar.gz');
    File::put($local, (string) Storage::disk('backups')->get($r['set'].'/files.tar.gz'));
    $names = [];
    $tar = gzopen($local, 'rb');
    while (($header = gzread($tar, 512)) !== false && strlen($header) === 512 && trim($header, "\0") !== '') {
        $name = rtrim(substr($header, 0, 100), "\0");
        $prefix = rtrim(substr($header, 345, 155), "\0");
        $size = octdec(trim(substr($header, 124, 12)));
        $names[($prefix !== '' ? $prefix.'/' : '').$name] = $size;
        gzseek($tar, (int) (gztell($tar) + $size + (512 - $size % 512) % 512));
    }
    gzclose($tar);
    expect($names)->toHaveKey('a.txt')->and($names['a.txt'])->toBe(1000)->and(array_values($names))->toContain(3000)->and(implode(' ', array_keys($names)))->toContain(str_repeat('d', 60).'/'.str_repeat('e', 60).'/long-name-');
    File::delete($local);
    File::deleteDirectory($files);
});
