<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Onhost\Domain\Billing\ReinstatementHealth;
use Onhost\Domain\Billing\ServiceReinstatement;
use Onhost\Domain\Provisioning\AutomationLedger;

/*
 * G6: pay and restore (`services.reinstate`, owner decision 23) was default-off until the owner decided; it is on by default since H0
 * (owner decision H-R3, 2026-10-06) and staff may still switch it off. The doctor
 * says so in a row of its own — what happens while it is off (a paid invoice of a cancelled service restores nothing, the purge
 * then removes a paid service; an undone cancellation is not billed again), and what to do — instead of one name in the list of
 * switched-off rules.
 */

/** @return array<string,string> */
function g6ReinstateRow(): array
{
    Artisan::call('onhost:doctor', ['--json' => true]);

    return collect(json_decode(Artisan::output(), true)['checks'])->firstWhere('check', 'pay and restore (services.reinstate)') ?? [];
}

it('warns while pay and restore is off, says what that means and how to switch it on', function () {
    expect(g6ReinstateRow()['status'] ?? null)->toBe('OK'); // H0 (owner decision H-R3): on by default
    app(AutomationLedger::class)->setEnabled(ServiceReinstatement::RULE, false);
    $off = g6ReinstateRow();
    expect($off['status'] ?? null)->toBe('WARN')->and($off['area'])->toBe('billing')
        ->and($off['detail'])->toContain('off')->toContain('paid invoice')->toContain('not billed again')
        ->and($off['remedy'])->toContain('onhost:billing:reinstatement-audit')->toContain('PUT /v1/staff/automation/services.reinstate');

    app(AutomationLedger::class)->setEnabled(ServiceReinstatement::RULE, true);
    $on = g6ReinstateRow();
    expect($on['status'])->toBe('OK')->and($on['detail'])->toContain('on')->and($on['remedy'])->toBe('');
});

it('never fails a production deploy over it: the switch is the owner\'s decision, not a defect', function () {
    app(AutomationLedger::class)->setEnabled(ServiceReinstatement::RULE, false); // H0 (H-R3): on by default — the off row is the one judged here
    $row = app(ReinstatementHealth::class)->checks()[0];
    expect($row['ok'])->toBeFalse()->and($row['blocking'])->toBeFalse()->and($row['fail_everywhere'] ?? false)->toBeFalse();
});
