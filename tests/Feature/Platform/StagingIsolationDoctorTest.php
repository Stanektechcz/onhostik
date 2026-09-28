<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Onhost\Domain\Identity\Authorization\ApprovalService;
use Onhost\Platform\Files\VirusScanner;

/*
 * TASK-0045 — staging isolation as code, not runbook text (pre-mortem 2026-09-27; onboarding audit C7b, F2, F5). The
 * doctor now says when this installation writes into Redis/cache keys another installation shares, when Turnstile is
 * switched on but has no keys (which means off), who decides approvals and whether the sole approver's waiver is in
 * effect; ClamAV with enforcement and no host is UNAVAILABLE in production instead of a silent pass.
 */

/** @return array<string, array{area:string, check:string, status:string, detail:string}> */
function isolationDoctorRows(): array
{
    Artisan::call('onhost:doctor', ['--json' => true]);
    $rows = [];
    foreach (json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR)['checks'] as $row) {
        $rows[$row['area'].'|'.$row['check']] = $row;
    }

    return $rows;
}

const ISOLATION_PREFIX_ROW = 'storage|Redis and cache key prefixes are this installation\'s own';

it('fails outside local when Redis or cache keys carry Laravel\'s default prefix, which another installation shares', function () {
    config(['cache.default' => 'database', 'queue.default' => 'redis', 'app.name' => 'ONhost']); // Redis carries the queue, the cache is a shared database table
    foreach ([['onhost-database-', 'onhost_staging'], ['onhost_staging', 'onhost-cache-'], ['laravel_database_', 'onhost_staging'], ['', 'onhost_staging']] as [$redis, $cache]) {
        config(['database.redis.options.prefix' => $redis, 'cache.prefix' => $cache]);
        $row = isolationDoctorRows()[ISOLATION_PREFIX_ROW];

        expect($row['status'])->toBe('FAIL', "redis '{$redis}', cache '{$cache}' (not production, still FAIL)")
            ->and($row['detail'])->toContain('REDIS_PREFIX')->toContain('CACHE_PREFIX');
    }

    config(['database.redis.options.prefix' => 'onhost_staging_', 'cache.prefix' => 'onhost_staging']);
    expect(isolationDoctorRows()[ISOLATION_PREFIX_ROW]['status'])->toBe('OK');

    // a developer machine shares nothing with anybody
    config(['database.redis.options.prefix' => 'onhost-database-', 'cache.prefix' => 'onhost-cache-']);
    app()->instance('env', 'local');
    expect(isolationDoctorRows()[ISOLATION_PREFIX_ROW]['status'])->toBe('OK');
});

it('does not judge the Redis prefix of an installation that uses Redis for nothing', function () {
    config(['cache.default' => 'database', 'queue.default' => 'database', 'session.driver' => 'database', 'database.redis.options.prefix' => 'onhost-database-', 'cache.prefix' => 'onhost_staging']);

    $row = isolationDoctorRows()[ISOLATION_PREFIX_ROW];
    expect($row['status'])->toBe('OK')->and($row['detail'])->toContain('Redis unused');
});

it('calls Turnstile off when it is enforced without keys: a warning outside production, a failure in production', function () {
    $name = 'security|Turnstile protects registration and public forms';
    config(['onhost.turnstile.site_key' => '', 'onhost.turnstile.secret' => '', 'onhost.turnstile.enforce_register' => true, 'onhost.turnstile.enforce_forms' => true]);
    expect(isolationDoctorRows()[$name]['status'])->toBe('WARN')
        ->and(isolationDoctorRows()[$name]['detail'])->toContain('TURNSTILE_SITE_KEY');

    app()->instance('env', 'production');
    expect(isolationDoctorRows()[$name]['status'])->toBe('FAIL');

    config(['onhost.turnstile.site_key' => '0x4AAAAAAAsite', 'onhost.turnstile.secret' => '0x4AAAAAAAsecret']);
    $row = isolationDoctorRows()[$name];
    expect($row['status'])->toBe('OK')->and($row['detail'])->not->toContain('0x4AAAAAAAsecret');
});

it('says who decides approvals and whether the sole approver\'s waiver is in effect', function () {
    $deciders = 'identity|who decides approvals';
    $first = $this->staff('iam_admin');
    config(['onhost.identity.four_eyes' => true]);

    // four eyes on and one person who may decide: a critical action of that person can never be approved
    $rows = isolationDoctorRows();
    expect(ApprovalService::deciders()->count())->toBe(1)
        ->and($rows[$deciders]['status'])->toBe('WARN')
        ->and($rows[$deciders]['detail'])->toContain($first->name);
    app()->instance('env', 'production');
    expect(isolationDoctorRows()[$deciders]['status'])->toBe('FAIL');

    // the solo operator's switch: the waiver is scoped to the sole approver and waits a time lock (TASK-0037)
    config(['onhost.identity.four_eyes' => false, 'onhost.four_eyes_time_lock_hours' => 24]);
    $rows = isolationDoctorRows();
    expect($rows[$deciders]['status'])->toBe('WARN')
        ->and($rows[$deciders]['detail'])->toContain('sole approver')->toContain($first->name)->toContain('24 h')
        ->and($rows['identity|four eyes in effect']['detail'])->toContain('24 h time lock')
        ->and($rows['identity|four eyes in effect']['detail'])->not->toContain('take one person and a step-up'); // the old waiver, gone since TASK-0037

    // a second person who may decide: the switch spares nobody any more
    $this->staff('iam_admin');
    $rows = isolationDoctorRows();
    expect($rows[$deciders]['status'])->toBe('WARN')->and($rows[$deciders]['detail'])->toContain('no effect');
    config(['onhost.identity.four_eyes' => true]);
    expect(isolationDoctorRows()[$deciders]['status'])->toBe('OK');
});

it('treats an enforced scanner without a host as unavailable in production, and fails its doctor row there', function () {
    config(['onhost.storage.clamav.host' => '', 'onhost.storage.clamav.enforce' => true]);
    $scanner = app(VirusScanner::class);
    $stream = fopen('php://memory', 'rb+');
    fwrite($stream, 'plain text');
    rewind($stream);

    // outside production nothing changes: no host = no scanner, files are unscanned but allowed
    expect($scanner->scanStream($stream)['result'])->toBe(VirusScanner::OFF)->and($scanner->allows(VirusScanner::OFF))->toBeTrue()
        ->and(isolationDoctorRows()['files|virus scanner (clamd)']['status'])->toBe('WARN'); // (also resolves the test env's secret store before "production" refuses it)

    app()->instance('env', 'production');
    rewind($stream);
    expect($scanner->scanStream($stream)['result'])->toBe(VirusScanner::UNAVAILABLE)
        ->and($scanner->scanPath('evidence/any.pdf')['result'])->toBe(VirusScanner::UNAVAILABLE)
        ->and($scanner->allows(VirusScanner::OFF))->toBeFalse()
        ->and($scanner->allows(VirusScanner::UNAVAILABLE))->toBeFalse();
    $row = isolationDoctorRows()['files|virus scanner (clamd)'];
    expect($row['status'])->toBe('FAIL')->and($row['detail'])->toContain('refused');

    // enforcement switched off deliberately: files pass unscanned, and the doctor still fails the row in production
    config(['onhost.storage.clamav.enforce' => false]);
    expect($scanner->allows(VirusScanner::OFF))->toBeTrue()->and(isolationDoctorRows()['files|virus scanner (clamd)']['status'])->toBe('FAIL');
    fclose($stream);
});
