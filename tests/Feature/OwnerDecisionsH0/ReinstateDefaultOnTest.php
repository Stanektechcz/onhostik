<?php

declare(strict_types=1);

use Onhost\Domain\Billing\ServiceReinstatement;
use Onhost\Domain\Provisioning\AutomationLedger;

/*
 * H0, owner decision H-R3 (2026-10-06): pay and restore (`services.reinstate`) is on by default, so a cancellation the customer
 * takes back is billed again and a paid invoice brings its service back. It stays a switchable rule (staff can switch it off in
 * Automatizace), and `onhost:billing:reinstatement-audit` still lists who was restored unbilled before (read only).
 */

it('is on without anybody switching it, and staff can still switch it off and on again', function () {
    $ledger = app(AutomationLedger::class);
    expect(app(ServiceReinstatement::class)->enabled())->toBeTrue()
        ->and(collect(AutomationLedger::RULES)->firstWhere('key', ServiceReinstatement::RULE))->not->toHaveKey('default_off');

    $ledger->setEnabled(ServiceReinstatement::RULE, false);
    expect(app(ServiceReinstatement::class)->enabled())->toBeFalse();
    $ledger->setEnabled(ServiceReinstatement::RULE, true);
    expect(app(ServiceReinstatement::class)->enabled())->toBeTrue();

    $this->artisan('onhost:billing:reinstatement-audit')->assertExitCode(0); // the read-only look stays
});
