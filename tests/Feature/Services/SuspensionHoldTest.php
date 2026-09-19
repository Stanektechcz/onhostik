<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\SuspensionHold;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/*
 * A suspension belongs to whoever imposed it (Brain card H17). A service quarantined for abuse, stopped for an unpaid
 * invoice or deactivated because its subscription ended is not the customer's to switch back on: the quarantine holds
 * until the case is closed, the stop until the money arrives, and the data and the audit trail stay where they are.
 * What the customer paused themselves, the customer resumes.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Queue::fake(); // the operations themselves are not the subject
});

/** A service that was suspended before holds existed: only the reason column says why. */
function legacySuspended(Service $service, string $reason): Service
{
    $service->forceFill(['state' => ServiceStateMachine::SUSPENDED, 'suspended_at' => now(), 'suspended_reason' => $reason])->save();

    return $service->fresh();
}

/** Walk a service through SUSPENDING → SUSPENDED the way the workflow does, as the given actor. */
function suspendAs(Service $service, CommandContext $context, string $reason): Service
{
    $services = app(ServiceService::class);
    $operation = Operation::query()->create(['organization_id' => $service->organization_id, 'service_id' => $service->id, 'kind' => 'service.action', 'workflow' => 'x', 'state' => Operation::SUCCEEDED, 'idempotency_key' => 'hold-'.uniqid(), 'queue' => 'default', 'desired' => [], 'finished_at' => now()]);
    $services->transition($service, ServiceStateMachine::SUSPENDING, $context, $reason);
    $services->settleTransient($service->fresh(), ServiceStateMachine::SUSPENDED, $context, $reason, $operation);

    return $service->fresh();
}

it('does not let a customer lift a quarantine, a non-payment stop or a staff decision, and tells them the way out', function (string $reason, string $hold) {
    [$owner, $org] = $this->customerWithOrganization();
    $service = legacySuspended(featureWebService($org, 'aapanel'), $reason);
    $this->actingAs($owner, 'sanctum');

    $refused = $this->withHeader('Idempotency-Key', "hold-{$hold}")->postJson("/v1/services/{$service->id}/resume")->assertStatus(409)->assertJsonPath('error', 'service_suspension_held')->assertJsonPath('hold', $hold);
    expect($refused->json('message'))->not->toContain('AB-2026'); // the case number is staff's, the customer gets the way out
    expect($service->fresh()->state)->toBe(ServiceStateMachine::SUSPENDED)->and(Operation::query()->where('service_id', $service->id)->count())->toBe(0);
    // the customer sees why on the service itself and on the panel row the workbench reads
    expect($this->getJson("/v1/services/{$service->id}")->assertOk()->json('data.suspension'))->toMatchArray(['hold' => $hold, 'customer_can_resume' => false]);
    expect($this->get('/surfaces/onhost-panel.js')->assertOk()->getContent())->toContain('"suspension":{"hold":"'.$hold.'"');
})->with([['abuse:AB-2026-0007', 'abuse'], ['dunning', 'payment'], ['subscription ended', 'payment'], ['risk:order held', 'review']]);

it('records who imposed a suspension: the customer resumes their own pause, nobody else\'s', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $this->actingAs($owner, 'sanctum');

    // the customer's own pause carries no hold
    $own = suspendAs(featureWebService($org, 'aapanel'), $this->contextFor($owner, $org), 'na dovolené');
    expect(SuspensionHold::holds($own))->toBe([])->and($this->getJson("/v1/services/{$own->id}")->json('data.suspension'))->toMatchArray(['hold' => null, 'customer_can_resume' => true]);
    $this->withHeader('Idempotency-Key', 'own-resume')->postJson("/v1/services/{$own->id}/resume")->assertStatus(202);

    // staff suspend with a free-text reason: a hold all the same, because of who did it
    $byStaff = suspendAs(legacySuspendedReset($own), $this->contextFor($this->staff('support_l2'), $org), 'zákazník požádal telefonicky, ověřujeme identitu');
    expect(SuspensionHold::holds($byStaff))->toBe(['review']);
    $this->withHeader('Idempotency-Key', 'staff-held')->postJson("/v1/services/{$byStaff->id}/resume")->assertStatus(409)->assertJsonPath('hold', 'review');
});

/** Back to ACTIVE with nothing left of the previous suspension. */
function legacySuspendedReset(Service $service): Service
{
    Operation::query()->where('service_id', $service->id)->update(['state' => Operation::SUCCEEDED, 'finished_at' => now()]);
    $service->forceFill(['state' => ServiceStateMachine::ACTIVE, 'suspended_at' => null, 'suspended_reason' => null, 'tags' => array_diff_key((array) $service->tags, ['suspension' => 1])])->save();

    return $service->fresh();
}

it('keeps a quarantine when the invoice is paid, and an unpaid stop when the quarantine ends', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $services = app(ServiceService::class);
    $system = fn (string $why) => CommandContext::system($why)->withScope($org->id);

    // overdue first, then reported for phishing: the site is already down, so the abuse team only adds its hold
    $service = suspendAs(featureWebService($org, 'aapanel'), $system('dunning'), 'dunning');
    $service = $services->imposeHold($service, SuspensionHold::ABUSE, 'abuse:AB-2026-0007', $this->contextFor($this->staff('abuse_trust_safety'), $org));
    expect(SuspensionHold::holds($service))->toBe(['abuse', 'payment'])->and($service->suspended_reason)->toBe('dunning');

    // the money arrives: the payment hold goes, the quarantine — and the suspension — stay
    expect(fn () => $services->requestAction($service, 'resume', $system('dunning resolved'), 'paid-1', ['reason' => 'dunning resolved', 'lift' => SuspensionHold::PAYMENT]))
        ->toThrow(fn (DomainError $e) => expect($e->error)->toBe('service_suspension_held')->and($e->extra['holds'])->toBe(['abuse'])->and($e->extra['lifted'])->toBe('payment'));
    $service = $service->fresh();
    expect($service->state)->toBe(ServiceStateMachine::SUSPENDED)->and(SuspensionHold::holds($service))->toBe(['abuse'])->and(Operation::query()->where('service_id', $service->id)->where('state', Operation::PENDING)->count())->toBe(0);
    // the platform cannot lift a hold it does not name
    expect(fn () => $services->requestAction($service, 'resume', $system('dunning resolved'), 'paid-2', ['lift' => SuspensionHold::PAYMENT]))->toThrow(DomainError::class);

    // staff end the quarantine: on the record, with a reason
    $abuse = $this->contextFor($this->staff('abuse_trust_safety'), $org);
    expect(fn () => $services->requestAction($service, 'resume', $abuse, 'lift-0'))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('reason_required'));
    expect($services->requestAction($service, 'resume', $abuse, 'lift-1', ['reason' => 'obsah odstraněn, případ uzavřen']))->toBeInstanceOf(Operation::class);
    expect(AuditEvent::query()->where('action', 'service.hold.lift')->where('resource_id', $service->id)->count())->toBe(2);
});

it('does not dissolve a hold in a failed resume or in a cancellation on top of it', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $services = app(ServiceService::class);
    $service = suspendAs(featureWebService($org, 'aapanel'), CommandContext::system('dunning')->withScope($org->id), 'dunning');
    $operation = Operation::query()->where('service_id', $service->id)->firstOrFail();

    // the panel refused the resume: the service is suspended again — for the same reason, under the same hold
    $service->forceFill(['state' => ServiceStateMachine::RESUMING])->save();
    $services->settleTransient($service->fresh(), ServiceStateMachine::SUSPENDED, CommandContext::system('dunning resolved')->withScope($org->id), 'resume failed: panel error', $operation);
    $after = $service->fresh();
    expect($after->suspended_reason)->toBe('dunning')->and(SuspensionHold::holds($after))->toBe(['payment']);

    // the customer cancels the unpaid service: it is deactivated with a restore window, and still not theirs to restore for free
    $services->settleTransient($after, ServiceStateMachine::SUSPENDED, $this->contextFor($owner, $org), 'terminate', $operation, null, 'service.deactivated');
    $cancelled = $service->fresh();
    expect($cancelled->suspended_reason)->toBe('dunning')->and(SuspensionHold::holds($cancelled))->toBe(['payment']);
    $this->actingAs($owner, 'sanctum');
    $this->withHeader('Idempotency-Key', 'free-restore')->postJson("/v1/services/{$cancelled->id}/resume")->assertStatus(409)->assertJsonPath('hold', 'payment');

    // back in service: nothing of the suspension is left behind
    $cancelled->forceFill(['state' => ServiceStateMachine::RESUMING])->save();
    $services->settleTransient($cancelled->fresh(), ServiceStateMachine::ACTIVE, CommandContext::system('dunning resolved')->withScope($org->id), 'resumed', $operation);
    expect($service->fresh()->suspended_at)->toBeNull()->and(data_get($service->fresh()->tags, 'suspension'))->toBeNull();
});
