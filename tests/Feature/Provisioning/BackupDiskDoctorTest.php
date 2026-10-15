<?php

use Illuminate\Support\Facades\Artisan;

/** A4: the backup-disk doctor row is blocking and asks for an s3 driver, not merely "not local". */
function backupDiskDoctorRow(string $disk, bool $production): array
{
    config(['onhost.platform_backup.disk' => $disk]);
    if ($production) {
        app()->instance('env', 'production');
    }
    Artisan::call('onhost:doctor', ['--json' => true]);

    return collect(json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR)['checks'])->firstWhere('check', 'platform backup disk off the server');
}

it('fails the backup disk row in production for local and public disks, with the PR-10 remedy', function (string $disk) {
    $row = backupDiskDoctorRow($disk, true);

    expect($row['status'])->toBe('FAIL')
        ->and($row['detail'])->toContain($disk)
        ->and($row['remedy'])->toContain('PR-10');
})->with(['local', 'public']);

it('passes the backup disk row in production for an s3 disk', function () {
    config(['filesystems.disks.offsite' => ['driver' => 's3', 'bucket' => 'b', 'region' => 'eu-central-1', 'key' => 'k', 'secret' => 's']]);

    expect(backupDiskDoctorRow('offsite', true)['status'])->toBe('OK');
});

it('keeps the backup disk row OK outside production', function () {
    expect(backupDiskDoctorRow('local', false)['status'])->toBe('OK');
});
