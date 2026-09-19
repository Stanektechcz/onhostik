<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Onhost\Domain\Provisioning\Models\IntegrationHealth;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Services\ControlPlaneStatus;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/*
 * The customer's service and the panel API we manage it through fail independently (Brain card H324). A panel under
 * maintenance stops changes, not the web site: the customer is told so, with the planned end, instead of an action
 * that fails in the queue — and the state of the service itself is never touched by any of it.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Queue::fake(); // the operations themselves are not the subject here
});

function controlPlaneInstance(string $serviceInstanceId): ProviderInstance
{
    return ProviderInstance::query()->findOrFail($serviceInstanceId);
}

it('refuses a customer change on a panel under maintenance with the reason and the planned end, and lets staff and system runs through', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $until = now()->addHours(2)->startOfMinute();
    controlPlaneInstance($service->provider_instance_id)->forceFill(['state' => 'maintenance', 'maintenance_until' => $until, 'state_reason' => 'panel upgrade'])->save();

    $this->actingAs($owner, 'sanctum');
    $response = $this->withHeader('Idempotency-Key', 'cp-1')->postJson("/v1/services/{$service->id}/actions", ['action' => 'backup'])
        ->assertStatus(503)->assertJsonPath('error', 'control_plane_maintenance')
        ->assertJsonPath('control_plane.state', 'maintenance')->assertJsonPath('control_plane.available', false);
    expect($response->json('control_plane.until'))->toBe($until->toIso8601String())
        ->and((int) $response->headers->get('Retry-After'))->toBeGreaterThan(7000)
        ->and($response->json('message'))->toContain('Služba běží dál')
        ->and($response->json('control_plane.message'))->not->toContain('panel upgrade'); // the staff reason is internal
    expect(Operation::query()->where('service_id', $service->id)->count())->toBe(0)
        ->and($service->fresh()->state)->toBe(ServiceStateMachine::ACTIVE); // the service itself is not declared down

    // the people doing the maintenance, and the platform's own runs, are not locked out by it
    $services = app(ServiceService::class);
    $byStaff = $services->requestAction($service->fresh(), 'backup', $this->contextFor($this->staff(), $org), 'cp-staff');
    expect($byStaff)->toBeInstanceOf(Operation::class);
    Operation::query()->whereKey($byStaff->id)->update(['state' => Operation::SUCCEEDED, 'finished_at' => now()]);
    $bySystem = $services->requestAction($service->fresh(), 'backup', CommandContext::system('nightly backup')->withScope($org->id), 'cp-system');
    expect($bySystem)->toBeInstanceOf(Operation::class);
    Operation::query()->whereKey($bySystem->id)->update(['state' => Operation::SUCCEEDED, 'finished_at' => now()]); // one operation per service at a time

    // a customer calling the domain directly (chat agent, action hook acting as the user) meets the same answer
    expect(fn () => $services->requestAction($service->fresh(), 'backup', $this->contextFor($owner, $org), 'cp-2'))
        ->toThrow(fn (DomainError $e) => expect($e->error)->toBe('control_plane_maintenance')->and($e->status)->toBe(503));
});

it('reports a panel that does not answer apart from the service and still accepts the change', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $failedAt = now()->subMinutes(4)->startOfSecond();
    IntegrationHealth::query()->updateOrCreate(['provider_instance_id' => $service->provider_instance_id], ['up' => false, 'last_failure_at' => $failedAt, 'last_error' => 'connection refused', 'checked_at' => now()]);

    $status = ControlPlaneStatus::of($service);
    expect($status)->toMatchArray(['available' => false, 'state' => 'unreachable', 'since' => $failedAt->toIso8601String(), 'until' => null])
        ->and($status['message'])->not->toContain('connection refused'); // vendor errors stay with staff

    // an outage nobody planned has no end to quote: the change is queued and runs once the panel answers
    $this->actingAs($owner, 'sanctum');
    $this->withHeader('Idempotency-Key', 'cp-3')->postJson("/v1/services/{$service->id}/actions", ['action' => 'backup'])->assertStatus(202);
    expect($service->fresh()->state)->toBe(ServiceStateMachine::ACTIVE);

    // the panel is back
    IntegrationHealth::query()->where('provider_instance_id', $service->provider_instance_id)->update(['up' => true]);
    expect(ControlPlaneStatus::of($service))->toMatchArray(['available' => true, 'state' => 'available', 'message' => null]);
});

it('carries the control plane in the customer API and on the panel row, separate from the state of the service', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $this->actingAs($owner, 'sanctum');

    $api = $this->getJson("/v1/services/{$service->id}")->assertOk()->json('data');
    expect($api['control_plane'])->toMatchArray(['available' => true, 'state' => 'available'])->and($api['state'])->toBe(ServiceStateMachine::ACTIVE);
    expect($this->get('/surfaces/onhost-panel.js')->assertOk()->getContent())->not->toContain('údržba panelu');

    controlPlaneInstance($service->provider_instance_id)->forceFill(['state' => 'maintenance', 'maintenance_until' => now()->addHour()])->save();
    $api = $this->getJson("/v1/services/{$service->id}")->assertOk()->json('data');
    expect($api['control_plane']['state'])->toBe('maintenance')->and($api['control_plane']['until'])->not->toBeNull()
        ->and($api['state'])->toBe(ServiceStateMachine::ACTIVE); // two layers, two answers
    $panel = $this->get('/surfaces/onhost-panel.js')->assertOk()->getContent();
    expect($panel)->toContain('údržba panelu')
        ->and($panel)->toContain('"control_plane":{"available":false,"state":"maintenance"'); // the workbench reads the selected service from this row
});

it('never blames the control plane for a service that has no panel, and names a panel that is gone', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');

    expect(ControlPlaneStatus::forInstance(null, null))->toMatchArray(['available' => false, 'state' => 'disabled']);
    $service->provider_instance_id = null; // not saved: a service that was never placed
    expect(ControlPlaneStatus::of($service))->toMatchArray(['available' => true, 'state' => 'unassigned', 'message' => null]);
});
