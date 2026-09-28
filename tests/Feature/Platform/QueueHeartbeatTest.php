<?php

declare(strict_types=1);

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Onhost\Domain\Platform\QueueLaneHeartbeat;
use Onhost\Platform\Settings\SettingsStore;
use Symfony\Component\Process\Process;

/*
 * TASK-0045 — a heartbeat per queue lane (staging pre-mortem 2026-09-27). One heartbeat job on the default queue proved
 * that *a* worker ran: a dead `mails` or `provider-proxmox` lane — each its own systemd unit — went unnoticed while the
 * default lane answered. Every worker loop now stamps its own lane; the doctor has one row per lane that has ever run.
 */

/** @return array<string, array{area:string, check:string, status:string, detail:string}> the doctor's rows by `area|check` */
function laneDoctorRows(): array
{
    Artisan::call('onhost:doctor', ['--json' => true]);
    $rows = [];
    foreach (json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR)['checks'] as $row) {
        $rows[$row['area'].'|'.$row['check']] = $row;
    }

    return $rows;
}

/** The deployer's judge on a report whose HARD and GATED rows are OK, plus the rows given. */
function laneGateVerdict(array $extra, bool $production): Process
{
    $gate = base_path('infra/aapanel/deploy-gate.php');
    $namesRun = new Process([PHP_BINARY, $gate, 'names']);
    $namesRun->run();
    $names = json_decode($namesRun->getOutput(), true);
    $rows = [];
    foreach (array_merge($names['hard'], $names['gated']) as $name) {
        [$area, $check] = explode('|', $name, 2);
        $rows[] = ['area' => $area, 'check' => $check, 'status' => 'OK', 'detail' => ''];
    }
    foreach ($extra as $name => $status) {
        [$area, $check] = explode('|', $name, 2);
        $rows[] = ['area' => $area, 'check' => $check, 'status' => $status, 'detail' => ''];
    }
    $file = tempnam(sys_get_temp_dir(), 'lane-gate');
    file_put_contents($file, (string) json_encode(['environment' => $production ? 'production' : 'staging', 'fail' => 0, 'warn' => 0, 'checks' => $rows]));
    $process = new Process([PHP_BINARY, $gate, 'verdict', '--report', $file, '--doctor-rc', '0', '--env', $production ? 'production' : 'staging', '--production', $production ? '1' : '0', '--sha', str_repeat('ab', 20), '--override', '']);
    $process->setTimeout(30);
    $process->run();
    @unlink($file);

    return $process;
}

beforeEach(function () {
    config(['queue.default' => 'database']); // a real driver: under `sync` there is no worker and no lane to report
});

it('lets every worker loop stamp its own lane, and gives the doctor one row per lane that has run', function () {
    expect(laneDoctorRows())->not->toHaveKey('automation|queue worker alive (mails)'); // a lane that never ran is not expected

    Event::dispatch(new Looping('redis', 'default,mails'));
    Event::dispatch(new Looping('redis', 'provider-ispconfig'));

    $rows = laneDoctorRows();
    expect($rows['automation|queue worker alive (default)']['status'])->toBe('OK')
        ->and($rows['automation|queue worker alive (mails)']['status'])->toBe('OK')
        ->and($rows['automation|queue worker alive (provider-ispconfig)']['status'])->toBe('OK')
        ->and($rows)->toHaveKey('automation|queue worker alive'); // the aggregate row keeps its name: the deployer judges it by name
    expect(app(QueueLaneHeartbeat::class)->lanes())->toBe(['default', 'mails', 'provider-ispconfig']);
});

it('reports a lane whose worker stopped looping: a warning outside production, a failure in production', function () {
    Event::dispatch(new Looping('redis', 'mails'));
    Event::dispatch(new Looping('redis', 'provider-proxmox'));
    $this->travel(QueueLaneHeartbeat::STALE_MINUTES - 1)->minutes();
    Event::dispatch(new Looping('redis', 'provider-proxmox')); // this lane keeps looping, mails went quiet

    $this->travel(2)->minutes();
    $rows = laneDoctorRows();
    expect($rows['automation|queue worker alive (mails)']['status'])->toBe('WARN')
        ->and($rows['automation|queue worker alive (mails)']['detail'])->toContain('onhost-queue@mails')
        ->and($rows['automation|queue worker alive (provider-proxmox)']['status'])->toBe('OK');

    app()->instance('env', 'production');
    expect(laneDoctorRows()['automation|queue worker alive (mails)']['status'])->toBe('FAIL');
});

it('keeps one stamp per lane in a busy loop and ignores a queue name that is not a lane name', function () {
    $heartbeat = app(QueueLaneHeartbeat::class);
    Event::dispatch(new Looping('redis', 'mails'));
    $first = $heartbeat->lastSeenAt('mails');
    $this->travel(5)->seconds();
    Event::dispatch(new Looping('redis', 'mails')); // the worker loops every couple of seconds: no write each time
    expect($heartbeat->lastSeenAt('mails')?->equalTo($first))->toBeTrue();
    $this->travel(QueueLaneHeartbeat::STAMP_EVERY_SECONDS)->seconds();
    Event::dispatch(new Looping('redis', 'mails'));
    expect($heartbeat->lastSeenAt('mails')?->greaterThan($first))->toBeTrue();

    Event::dispatch(new Looping('redis', "bad lane\n; drop"));
    expect($heartbeat->lanes())->toBe(['mails']);
});

it('forgets a lane retired on purpose, so the doctor stops expecting it', function () {
    Event::dispatch(new Looping('redis', 'provider-kubernetes'));
    expect(laneDoctorRows())->toHaveKey('automation|queue worker alive (provider-kubernetes)');

    $this->artisan('onhost:queue:lanes', ['--forget' => 'provider-kubernetes'])->assertSuccessful();

    expect(laneDoctorRows())->not->toHaveKey('automation|queue worker alive (provider-kubernetes)')
        ->and(app(QueueLaneHeartbeat::class)->lanes())->toBe([]);
    $this->artisan('onhost:queue:lanes', ['--forget' => 'provider-nothing'])->assertFailed();
});

it('reports no lane rows under the sync driver, where no worker exists', function () {
    Event::dispatch(new Looping('redis', 'mails'));
    config(['queue.default' => 'sync']);

    expect(laneDoctorRows())->not->toHaveKey('automation|queue worker alive (mails)');
});

it('lets the deployer report the lane rows as liveness rows, judged after the units start', function () {
    $staging = laneGateVerdict(['automation|queue worker alive (mails)' => 'WARN'], false);
    expect($staging->getExitCode())->toBe(0)->and($staging->getOutput())->toContain('REPORT automation|queue worker alive (mails) (WARN)');

    $production = laneGateVerdict(['automation|queue worker alive (provider-proxmox)' => 'FAIL'], true);
    expect($production->getExitCode())->toBe(0)->and($production->getOutput())->toContain('REPORT automation|queue worker alive (provider-proxmox) (FAIL)');

    // only the liveness family: any other row outside the lists is still refused on staging
    expect(laneGateVerdict(['automation|queue worker alive-ish' => 'WARN'], false)->getExitCode())->toBe(12);
});

it('judges a lane-like row name that ends in a newline like any other row (review round 1: \z, not $)', function () {
    expect(laneGateVerdict(["automation|queue worker alive (mails)\n" => 'WARN'], false)->getExitCode())->toBe(12);
});

it('never lets a heartbeat that cannot be written stop the worker it reports on (review round 1)', function () {
    $broken = Mockery::mock(Repository::class);
    $broken->shouldReceive('put')->andThrow(new RuntimeException('Redis went away'));
    $heartbeat = new QueueLaneHeartbeat($broken, app(SettingsStore::class));

    $heartbeat->handle(new Looping('redis', 'mails')); // reported, not thrown

    expect(true)->toBeTrue();
});
