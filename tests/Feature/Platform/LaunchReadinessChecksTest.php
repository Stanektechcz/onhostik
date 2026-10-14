<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Onhost\Domain\Platform\LaunchReadinessChecks;

/*
 * Phase W, W2: the fail-closed launch switches are visible in the doctor. Both rows are standing WARN, never FAIL,
 * not even in production.
 */

/** @return array<string, array{area:string, check:string, status:string, detail:string}> */
function launchDoctorRows(): array
{
    Artisan::call('onhost:doctor', ['--json' => true]);
    $rows = [];
    foreach (json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR)['checks'] as $row) {
        $rows[$row['area'].'|'.$row['check']] = $row;
    }

    return $rows;
}

const LAUNCH_SELL_ROW = 'sales|'.LaunchReadinessChecks::SELL_ROW;
const LAUNCH_COMGATE_ROW = 'payments|'.LaunchReadinessChecks::COMGATE_ROW;

it('warns that nothing is for sale while every switch is off', function () {
    config(['onhost-sell.default' => false, 'onhost-sell.families' => ['web' => null, 'mail' => null, 'game' => false]]);
    $row = (new LaunchReadinessChecks)->sellMatrix();

    expect($row['ok'])->toBeFalse()->and($row['blocking'])->toBeFalse()
        ->and($row['detail'])->toContain('nothing is for sale (ONHOST_SELL_DEFAULT off)')->toContain('web, mail, game');
    expect(launchDoctorRows()[LAUNCH_SELL_ROW]['status'])->toBe('WARN');
});

it('is OK and lists the open and the closed families when something sells', function () {
    config(['onhost-sell.default' => false, 'onhost-sell.families' => ['web' => true, 'mail' => null, 'game' => false]]);
    $row = (new LaunchReadinessChecks)->sellMatrix();

    expect($row['ok'])->toBeTrue()->and($row['detail'])->toBe('for sale: web; off: mail, game');

    config(['onhost-sell.default' => true, 'onhost-sell.families' => ['web' => null, 'game' => false]]);
    expect((new LaunchReadinessChecks)->sellMatrix()['detail'])->toBe('for sale: web; off: game');
    expect(launchDoctorRows()[LAUNCH_SELL_ROW]['status'])->toBe('OK');
});

it('warns on an empty Comgate allow-list and is OK once it holds addresses', function () {
    foreach ([null, '', ' , ', '0.0.0.0/0'] as $empty) {
        config(['onhost.payments.comgate.callback_allowlist' => $empty]);
        $row = (new LaunchReadinessChecks)->comgateAllowlist();
        expect($row['ok'])->toBeFalse()->and($row['blocking'])->toBeFalse()
            ->and($row['detail'])->toContain('every notification is refused; payments settle via return/sweep');
    }

    config(['onhost.payments.comgate.callback_allowlist' => '203.0.113.7, 198.51.100.0/24']);
    $row = (new LaunchReadinessChecks)->comgateAllowlist();
    expect($row['ok'])->toBeTrue()->and($row['detail'])->toContain('2 address entries');
});

it('shows the Comgate row only when Comgate is the card gateway, and never as FAIL, even in production', function () {
    config(['onhost.payments.default' => 'comgate', 'onhost.payments.comgate.callback_allowlist' => '']);
    expect(launchDoctorRows()[LAUNCH_COMGATE_ROW]['status'])->toBe('WARN');

    app()->instance('env', 'production');
    config(['onhost-sell.default' => false, 'onhost-sell.families' => []]);
    $rows = launchDoctorRows();
    expect($rows[LAUNCH_COMGATE_ROW]['status'])->toBe('WARN')->and($rows[LAUNCH_SELL_ROW]['status'])->toBe('WARN');
});
