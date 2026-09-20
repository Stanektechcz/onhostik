<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\SuspensionHold;
use Onhost\Platform\Outbox\OutboxMessage;

/*
 * A suspend or a resume the panel refuses must leave the service in the state it is really in — running, or down — and
 * usable. The failure handler always meant to do that, but the state machine did not allow the way back, so the service
 * stayed in SUSPENDING / RESUMING, where no action is permitted and only a hand on the database helped.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** An aaPanel that answers everything except the named site action, which it refuses while `$refuses` is true (fakes stack, so the switch is a flag). */
function panelRefusing(string $action, bool &$refuses): void
{
    Http::fake(function (Request $request) use ($action, &$refuses) {
        if ($refuses && str_contains($request->url(), "action={$action}")) {
            return Http::response(['status' => false, 'msg' => 'The specified site does not exist!']);
        }

        return Http::response(['status' => true, 'msg' => 'ok', 'data' => []]);
    });
}

it('returns a service to ACTIVE when the panel refuses the suspend', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $refuses = true;
    panelRefusing('SiteStop', $refuses);
    $this->actingAs($owner, 'sanctum');

    $id = $this->withHeader('Idempotency-Key', 'fail-suspend')->postJson("/v1/services/{$service->id}/suspend")->assertStatus(202)->json('operation_id');
    $operation = driveOperation(Operation::query()->findOrFail($id));

    expect($operation->state)->toBeIn([Operation::FAILED, 'COMPENSATED'])
        ->and($service->fresh()->state)->toBe(ServiceStateMachine::ACTIVE) // it never stopped: say so, and stay usable
        ->and($service->fresh()->suspended_at)->toBeNull();
    // usable: the next action is accepted instead of service_state_invalid
    $refuses = false;
    $this->withHeader('Idempotency-Key', 'after-fail')->postJson("/v1/services/{$service->id}/actions", ['action' => 'backup'])->assertStatus(202);
});

it('returns a service to SUSPENDED when the panel refuses the resume, with the reason it was suspended for', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $refuses = true;
    panelRefusing('SiteStart', $refuses);
    $this->actingAs($owner, 'sanctum');
    $suspend = $this->withHeader('Idempotency-Key', 'pause-1')->postJson("/v1/services/{$service->id}/suspend", ['reason' => 'rekonstrukce webu'])->assertStatus(202)->json('operation_id');
    expect(driveOperation(Operation::query()->findOrFail($suspend))->state)->toBe(Operation::SUCCEEDED);
    expect($service->fresh()->state)->toBe(ServiceStateMachine::SUSPENDED)->and(SuspensionHold::holds($service->fresh()))->toBe([]);

    $resume = $this->withHeader('Idempotency-Key', 'resume-1')->postJson("/v1/services/{$service->id}/resume")->assertStatus(202)->json('operation_id');
    $operation = driveOperation(Operation::query()->findOrFail($resume));

    $after = $service->fresh();
    expect($operation->state)->toBeIn([Operation::FAILED, 'COMPENSATED'])
        ->and($after->state)->toBe(ServiceStateMachine::SUSPENDED)
        ->and($after->suspended_reason)->toBe('rekonstrukce webu') // not "resume failed: …": why it is down has not changed
        ->and($this->getJson("/v1/services/{$service->id}")->json('data.suspension.customer_can_resume'))->toBeTrue();

    // and the customer can simply try again once the panel is well
    $refuses = false;
    $again = $this->withHeader('Idempotency-Key', 'resume-2')->postJson("/v1/services/{$service->id}/resume")->assertStatus(202)->json('operation_id');
    expect(driveOperation(Operation::query()->findOrFail($again))->state)->toBe(Operation::SUCCEEDED)
        ->and($service->fresh()->state)->toBe(ServiceStateMachine::ACTIVE)->and(data_get($service->fresh()->tags, 'suspension'))->toBeNull();
});

it('lists the services an older deployment stranded and releases them only when asked to', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $operation = Operation::query()->create(['organization_id' => $org->id, 'service_id' => $service->id, 'kind' => 'service.action', 'workflow' => 'x', 'state' => Operation::FAILED, 'idempotency_key' => 'stranded-1', 'queue' => 'default', 'desired' => ['action' => 'resume'], 'finished_at' => now()->subHour()]);
    // what the old failure handler left behind: RESUMING, the suspension's reason still on the row, nothing running
    $service->forceFill(['state' => ServiceStateMachine::RESUMING, 'suspended_at' => now()->subDay(), 'suspended_reason' => 'dunning'])->save();
    Service::query()->whereKey($service->id)->update(['updated_at' => now()->subHour()]);
    // a service in the middle of a real resume is not stranded
    $busy = Service::query()->create(array_merge($service->only(['organization_id', 'product_key', 'family', 'name', 'region_code', 'provider_instance_id', 'node_id', 'desired_spec', 'entitlements', 'sla_class']), ['hostname' => 'bezi.cz', 'state' => ServiceStateMachine::RESUMING]));
    Service::query()->whereKey($busy->id)->update(['updated_at' => now()->subHour()]);
    Operation::query()->create(['organization_id' => $org->id, 'service_id' => $busy->id, 'kind' => 'service.action', 'workflow' => 'x', 'state' => Operation::RUNNING, 'idempotency_key' => 'stranded-2', 'queue' => 'default', 'desired' => []]);

    $this->artisan('onhost:doctor')->expectsOutputToContain('no service stranded in a transient state');
    $this->artisan('onhost:services:release-stranded')->expectsOutputToContain($service->id)->doesntExpectOutputToContain($busy->id)->assertExitCode(0);
    expect($service->fresh()->state)->toBe(ServiceStateMachine::RESUMING); // listing changes nothing

    $this->artisan('onhost:services:release-stranded', ['--apply' => true])->expectsOutputToContain('released')->assertExitCode(0);
    $released = $service->fresh();
    expect($released->state)->toBe(ServiceStateMachine::SUSPENDED)->and($released->suspended_reason)->toBe('dunning')->and(SuspensionHold::holds($released))->toBe(['payment'])
        ->and($busy->fresh()->state)->toBe(ServiceStateMachine::RESUMING);
    $this->artisan('onhost:services:release-stranded')->expectsOutputToContain('No stranded services.');
    expect($operation->fresh()->state)->toBe(Operation::FAILED);
    // the release runs by itself every ten minutes; staff hear what it put back
    expect(collect(Schedule::events())->contains(fn ($e) => str_contains((string) $e->command, 'onhost:services:release-stranded --apply')))->toBeTrue();
    $told = OutboxMessage::query()->where('name', 'provisioning.stranded.released')->firstOrFail();
    expect($told->payload['count'])->toBe(1)->and($told->payload['services'][0])->toMatchArray(['id' => $service->id, 'from' => ServiceStateMachine::RESUMING, 'to' => ServiceStateMachine::SUSPENDED]);
});
