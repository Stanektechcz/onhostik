<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing;

/**
 * G6: what the doctor says about pay and restore (`services.reinstate`, owner decision 23, TASK-0025). The rule is default-off
 * and is switched on only when the owner decides; until then the doctor names the consequence and the way to switch it on. A
 * warning, never blocking: the switch is a decision, not a defect.
 */
final class ReinstatementHealth
{
    public function __construct(private readonly ServiceReinstatement $reinstatement) {}

    /** @return list<array{area:string, check:string, ok:bool, detail:string, blocking:bool, remedy:string}> */
    public function checks(): array
    {
        $on = $this->reinstatement->enabled();

        return [[
            'area' => 'billing', 'check' => 'pay and restore (services.reinstate)', 'ok' => $on, 'blocking' => false,
            'detail' => $on
                ? 'on: a cancelled service in its restore window comes back once paid; an undone cancellation is billed again'
                : 'off (default, waits for the owner\'s decision): a paid invoice of a service cancelled by dunning restores nothing and the purge then removes a service that was paid for; an expired subscription can be neither paid for nor resumed; a cancellation the customer took back runs on and is not billed again',
            'remedy' => $on ? '' : 'when the owner decides: php artisan onhost:billing:reinstatement-audit (reads only: who is affected), then switch the rule on in the staff console (Automatizace) or PUT /v1/staff/automation/services.reinstate; bill an undone cancellation one service at a time with --apply --service=<id> (docs/runbooks/billing-dunning.md)',
        ]];
    }
}
