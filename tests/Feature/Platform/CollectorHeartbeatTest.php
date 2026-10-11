<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\CollectorHeartbeat;

function heartbeatDoctorRows(): array
{
    Artisan::call('onhost:doctor', ['--json' => true]);

    return collect(json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR)['checks'])->keyBy('check')->all();
}

const HEARTBEAT_CHECK = 'collector node disk usage (nodes.usage)';

afterEach(fn () => Carbon::setTestNow());

it('is ok when the collector succeeded within twice its interval', function () {
    app(AutomationLedger::class)->record('nodes.usage', ['checked' => 2]);
    Carbon::setTestNow(now()->addMinutes(29));

    expect(heartbeatDoctorRows()[HEARTBEAT_CHECK]['status'])->toBe('OK');
});

it('warns when the last success is older than twice the interval', function () {
    app(AutomationLedger::class)->record('nodes.usage', ['checked' => 2]);
    Carbon::setTestNow(now()->addMinutes(31));

    $row = heartbeatDoctorRows()[HEARTBEAT_CHECK];

    expect($row['status'])->toBe('WARN')->and($row['detail'])->toContain('expected every 15 min');
});

it('warns when the collector never ran or its last run failed', function () {
    expect(heartbeatDoctorRows()[HEARTBEAT_CHECK]['detail'])->toContain('never ran');

    app(AutomationLedger::class)->record('nodes.usage', [], 'boom');

    $row = heartbeatDoctorRows()[HEARTBEAT_CHECK];
    expect($row['status'])->toBe('WARN')->and($row['detail'])->toContain('boom');
});

it('skips a collector staff switched off', function () {
    app(AutomationLedger::class)->setEnabled('nodes.usage', false, 'test');

    expect(heartbeatDoctorRows())->not->toHaveKey(HEARTBEAT_CHECK);
    expect(collect(app(CollectorHeartbeat::class)->checks())->pluck('check'))->not->toContain(HEARTBEAT_CHECK);
});

it('skips the backup verify while the backup itself is switched off', function () {
    app(AutomationLedger::class)->setEnabled('platform.backup', false, 'test');

    expect(heartbeatDoctorRows())->not->toHaveKey('collector platform backup verify (platform.backup.verify)');
});

it('records the dns drift run so the collector can report', function () {
    Artisan::call('onhost:dns:drift');

    expect(app(AutomationLedger::class)->last('dns.drift'))->not->toBeNull();
});
