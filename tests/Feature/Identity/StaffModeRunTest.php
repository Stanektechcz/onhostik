<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Compliance\Models\AbuseCase;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\OperationService;
use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\SuspensionHold;
use Onhost\Platform\Commands\CommandContext;
use Tests\TestCase;

/*
 * TASK-0039 review round 1 (HIGH, permission program §3 "staffMode persisted", IF-8): staff mode has to survive the queue.
 *
 * The hold a suspension carries is decided when the run SETTLES it (ServiceService::settleTransient → SuspensionHold::kindFor),
 * and the run's actor used to be rebuilt from the operation as a plain person — never in staff mode. Once kindFor stopped reading
 * `is_staff`, the abuse team's quarantine (abuse.action `service_suspended`, reason `abuse:<case>`) and every staff suspend with a
 * free-text reason settled as the customer's own pause: the customer switched a quarantined site back on. The operation now
 * carries `desired.staff_mode`, written by OperationService::start from the starting context alone (a caller's parameter of that
 * name is dropped), and the runner rebuilds the context with it; StaffActor still asks whether the person is staff and active.
 * These tests go through the runner — the earlier ones called settleTransient with a staff context directly and missed it.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok', 'data' => []])); // an aaPanel that does what it is told
});

/** A triaged abuse case about the service, ready for a decision. */
function smrCase(Service $service, string $number): AbuseCase
{
    return AbuseCase::query()->create([
        'number' => $number, 'organization_id' => $service->organization_id, 'service_id' => $service->id, 'category' => 'phishing', 'state' => 'TRIAGED',
        'reporter' => ['name' => 'Jana Nováková', 'email' => 'jana@example.org', 'trusted_flagger' => false], 'allegation' => 'Stránka napodobuje přihlášení do banky.',
        'target_url' => 'https://shop.cz/login', 'evidence' => [], 'art18' => false,
    ]);
}

/** The customer tries to switch the service back on, on their own route. */
function smrCustomerResumes(TestCase $test, User $owner, Service $service, string $key): TestResponse
{
    app('auth')->forgetGuards();
    $test->flushHeaders();

    return $test->actingAs($owner, 'sanctum')->withHeader('Idempotency-Key', $key)->postJson("/v1/services/{$service->id}/resume");
}

it('keeps the abuse team\'s quarantine an ABUSE hold after the run settles it, and the customer cannot resume it', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $case = smrCase($service, 'ABU-2026-0201');
    $this->actingAs($this->steppedUpStaff('abuse_trust_safety'), 'sanctum');

    $this->withHeader('Idempotency-Key', 'smr-abuse')->postJson("/v1/staff/abuse-cases/{$case->id}/action", ['action' => 'service_suspended', 'reason' => 'Aktivní phishing ohrožuje třetí osoby.'])->assertSuccessful();
    $operation = Operation::query()->where('service_id', $service->id)->sole();
    expect((array) $operation->desired)->toMatchArray(['action' => 'suspend', 'staff_mode' => true]) // the abuse.action context came from /v1/staff/*
        ->and(driveOperation($operation)->state)->toBe(Operation::SUCCEEDED);

    expect($service->fresh()->state)->toBe(ServiceStateMachine::SUSPENDED)->and(SuspensionHold::holds($service->fresh()))->toBe([SuspensionHold::ABUSE]);
    smrCustomerResumes($this, $owner, $service, 'smr-abuse-resume')->assertStatus(409)->assertJsonPath('error', 'service_suspension_held')->assertJsonPath('hold', 'abuse');
});

it('keeps a staff suspend on /v1/staff a REVIEW hold after the run, whatever its reason says', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $this->actingAs($this->steppedUpStaff('platform_owner'), 'sanctum');

    $id = $this->withHeader('Idempotency-Key', 'smr-review')->postJson("/v1/staff/services/{$service->id}/actions", ['action' => 'suspend', 'reason' => 'zákazník požádal telefonicky, ověřujeme identitu'])
        ->assertStatus(202)->json('operation_id');
    expect(driveOperation(Operation::query()->findOrFail($id))->state)->toBe(Operation::SUCCEEDED);

    expect($service->fresh()->state)->toBe(ServiceStateMachine::SUSPENDED)->and(SuspensionHold::holds($service->fresh()))->toBe([SuspensionHold::REVIEW]);
    smrCustomerResumes($this, $owner, $service, 'smr-review-resume')->assertStatus(409)->assertJsonPath('hold', 'review');
});

it('writes staff mode on an operation only from the context that started it, never from its parameters', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $staff = $this->staff('platform_owner');
    $start = fn (CommandContext $context, string $key): array => (array) app(OperationService::class)->start(ServiceActionWorkflow::class, $key, ['action' => 'backup', 'service_id' => $service->id, 'staff_mode' => true], $context, $service->id, $org->id, dispatch: false)->desired;

    expect($start($this->contextFor($owner, $org), 'smr-p1'))->not->toHaveKey('staff_mode')           // a customer naming it
        ->and($start($this->contextFor($staff, $org), 'smr-p2'))->not->toHaveKey('staff_mode')          // staff on a customer route
        ->and($start($this->staffContextFor($owner, $org), 'smr-p3'))->not->toHaveKey('staff_mode')     // "staff mode" of somebody who is no staff
        ->and($start(CommandContext::system('test')->withScope($org->id), 'smr-p4'))->not->toHaveKey('staff_mode')
        ->and($start($this->staffContextFor($staff, $org), 'smr-p5'))->toMatchArray(['staff_mode' => true]);
});

it('settles a staff member\'s suspend on the customer route as the customer\'s own pause, whatever the parameters say', function () {
    $staff = $this->steppedUpStaff('platform_owner'); // … who also owns an organization of their own (EXPL-1..3)
    $org = app(OrganizationService::class)->create($staff, ['name' => 'Staff s.r.o.', 'type' => 'company', 'country' => 'CZ', 'currency' => 'CZK'], CommandContext::system('test'));
    $service = featureWebService($org, 'aapanel');
    $this->actingAs($staff, 'sanctum');

    $id = $this->withHeader('Idempotency-Key', 'smr-own')->postJson("/v1/services/{$service->id}/actions", ['action' => 'suspend', 'params' => ['staff_mode' => true], 'reason' => 'rekonstrukce webu'])
        ->assertStatus(202)->json('operation_id');
    $operation = driveOperation(Operation::query()->findOrFail($id));
    expect((array) $operation->desired)->not->toHaveKey('staff_mode')->and($operation->state)->toBe(Operation::SUCCEEDED)
        ->and(SuspensionHold::holds($service->fresh()))->toBe([]); // their organization's own pause: any member switches it back on
});

it('sends what staff confirm in the admin console\'s assistant through /v1/staff, not the customer route', function () {
    $drawer = (string) file_get_contents(base_path('apps/surfaces/api/onhost-admin-customer.api.js'));

    expect($drawer)->toContain("A().post('/staff/services/' + encodeURIComponent(a.service_id) + '/actions'")
        ->not->toContain("A().post('/services/' + encodeURIComponent(a.service_id) + '/actions'");
});
