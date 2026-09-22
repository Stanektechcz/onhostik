<?php

declare(strict_types=1);

use Onhost\Domain\Compliance\ComplianceService;
use Onhost\Domain\Compliance\Models\AbuseCase;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\SuspensionHold;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Outbox\OutboxMessage;

/*
 * A quarantine has to end. A site suspended for abuse carries a hold of its own so that nothing else can lift it by
 * accident — and **nothing ever lifted it**: closing the case only wrote a state, so a customer who won the appeal
 * stayed suspended for ever, with no way back except a staff member who happened to know to resume the service by
 * hand and name the hold. Closing a case that suspended a service now has to say what happens to the service.
 */

/** A case that has already suspended the customer's service, as `actionAbuse` leaves it. */
function quarantinedCase(string $serviceId, string $organizationId, string $number, string $state = 'APPEALED'): AbuseCase
{
    return AbuseCase::query()->create([
        'number' => $number, 'organization_id' => $organizationId, 'service_id' => $serviceId, 'category' => 'phishing', 'state' => $state,
        'reporter' => ['name' => 'Jana Nováková', 'email' => 'jana@example.org', 'trusted_flagger' => false], 'allegation' => 'Stránka napodobuje přihlášení do banky.',
        'target_url' => 'https://shop.cz/login', 'evidence' => [], 'art18' => false, 'action_taken' => 'service_suspended', 'decision' => 'action',
        'decision_reason' => 'Aktivní phishing ohrožuje třetí osoby.', 'decided_at' => now()->subDay(), 'appeal_deadline_at' => now()->addMonths(6),
    ]);
}

it('does not let a case that suspended a service be closed without saying what happens to it', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $case = quarantinedCase($service->id, $org->id, 'ABU-2026-0101');

    expect(fn () => app(ComplianceService::class)->closeAbuse($case, CommandContext::system('test')))
        ->toThrow(DomainError::class, 'jestli se služba vrací');
});

it('gives the service back when the case is closed in the customer\'s favour', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $service->forceFill(['state' => ServiceStateMachine::SUSPENDED, 'suspended_at' => now()->subHour(), 'suspended_reason' => 'abuse'])->save();
    app(ServiceService::class)->imposeHold($service->refresh(), SuspensionHold::ABUSE, 'abuse:ABU-2026-0102', CommandContext::system('test'));
    $case = quarantinedCase($service->id, $org->id, 'ABU-2026-0102');

    app(ComplianceService::class)->closeAbuse($case, CommandContext::system('test'), 'stížnosti bylo vyhověno', restore: true);

    expect($case->fresh()->state)->toBe('CLOSED')
        ->and(SuspensionHold::holds($service->fresh()))->toBe([])   // the hold the quarantine imposed is gone
        ->and(data_get(OutboxMessage::query()->where('name', 'abuse.case.closed')->first()?->payload, 'release'))->toBe('resumed');
});

it('leaves the service down when the suspension is meant to stand, and says so on the record', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $service->forceFill(['state' => ServiceStateMachine::SUSPENDED, 'suspended_at' => now()->subHour(), 'suspended_reason' => 'abuse'])->save();
    app(ServiceService::class)->imposeHold($service->refresh(), SuspensionHold::ABUSE, 'abuse:ABU-2026-0103', CommandContext::system('test'));
    $case = quarantinedCase($service->id, $org->id, 'ABU-2026-0103', 'ACTIONED');

    app(ComplianceService::class)->closeAbuse($case, CommandContext::system('test'), 'obsah byl skutečně nelegální', restore: false);

    expect(SuspensionHold::holds($service->fresh()))->toBe([SuspensionHold::ABUSE])
        ->and($service->fresh()->state)->toBe(ServiceStateMachine::SUSPENDED)
        ->and(data_get(OutboxMessage::query()->where('name', 'abuse.case.closed')->first()?->payload, 'restored'))->toBeFalse();
});

it('never lets one closed case release a service another open case is holding', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $service->forceFill(['state' => ServiceStateMachine::SUSPENDED, 'suspended_at' => now()->subHour(), 'suspended_reason' => 'abuse'])->save();
    app(ServiceService::class)->imposeHold($service->refresh(), SuspensionHold::ABUSE, 'abuse:ABU-2026-0104', CommandContext::system('test'));
    $first = quarantinedCase($service->id, $org->id, 'ABU-2026-0104');
    quarantinedCase($service->id, $org->id, 'ABU-2026-0105', 'ACTIONED'); // still open, and it wants the service off

    app(ComplianceService::class)->closeAbuse($first, CommandContext::system('test'), null, restore: true);

    expect(SuspensionHold::holds($service->fresh()))->toBe([SuspensionHold::ABUSE])
        ->and($service->fresh()->state)->toBe(ServiceStateMachine::SUSPENDED)
        ->and(data_get(OutboxMessage::query()->where('name', 'abuse.case.closed')->first()?->payload, 'release'))->toBe('held');
});

it('closes a case that never touched the service without asking anything', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $case = quarantinedCase($service->id, $org->id, 'ABU-2026-0106');
    $case->forceFill(['action_taken' => 'warning'])->save();

    expect(app(ComplianceService::class)->closeAbuse($case->refresh(), CommandContext::system('test'))->state)->toBe('CLOSED')
        ->and($service->fresh()->state)->toBe(ServiceStateMachine::ACTIVE);
});
