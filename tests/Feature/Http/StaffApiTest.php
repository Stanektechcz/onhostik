<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Provisioning\FreezeSwitch;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ResourceDrift;
use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Audit\AuditEvent;

function failedOperation(Service $service, string $key = 'failed-1'): Operation
{
    return Operation::query()->create(['service_id' => $service->id, 'organization_id' => $service->organization_id, 'kind' => 'service.action', 'workflow' => ServiceActionWorkflow::class, 'state' => Operation::FAILED, 'step' => 0, 'steps_total' => 2, 'actor_type' => 'user', 'idempotency_key' => $key, 'correlation_id' => 'c1', 'desired' => ['action' => 'power', 'power_action' => 'start', 'service_id' => $service->id], 'context' => [], 'queue' => 'provider-proxmox', 'queued_at' => now()->subHour(), 'next_run_at' => now()->subHour(), 'retry_until' => now()->addHour(), 'error' => ['message' => 'provider timeout', 'retryable' => true], 'provider_instance_id' => $service->provider_instance_id]);
}

beforeEach(fn () => Http::preventStrayRequests());

it('exposes the operations queue, integrations and capacity to staff only', function () {
    Http::fake();
    [$customer, $org] = $this->customerWithOrganization();
    $instance = pveLab();
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'VPS', 'state' => 'ACTIVE', 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'entitlements' => [], 'desired_spec' => [], 'sla_class' => 'standard']);
    $operation = failedOperation($service);

    $this->actingAs($customer, 'sanctum');
    $this->getJson('/v1/staff/provisioning/jobs')->assertForbidden();
    $this->getJson('/v1/staff/integrations')->assertForbidden();
    $this->getJson('/v1/staff/customers')->assertForbidden();

    $sre = $this->staff('sre');
    $this->actingAs($sre, 'sanctum');
    $jobs = $this->getJson('/v1/staff/provisioning/jobs?state=FAILED')->assertOk()->assertHeader('X-Total-Count', '1');
    expect($jobs->json('data.0.id'))->toBe($operation->id)->and($jobs->json('data.0.error_detail.message'))->toBe('provider timeout');
    $this->getJson('/v1/staff/provisioning/jobs/'.$operation->id)->assertOk()->assertJsonPath('data.workflow', ServiceActionWorkflow::class);
    $this->getJson('/v1/staff/integrations')->assertOk()->assertJsonPath('data.0.key', 'proxmox-cz1');
    $this->getJson('/v1/staff/capacity')->assertOk()->assertJsonPath('data.nodes.0.name', 'prg1-n2');
    $this->getJson('/v1/staff/customers')->assertOk()->assertHeader('X-Total-Count', '1')->assertJsonPath('data.0.services', 1);
    $this->getJson('/v1/staff/customers/'.$org->id)->assertOk()->assertJsonCount(1, 'data.services');
});

it('retries a failed operation and cancels with a mandatory reason, both audited', function () {
    Http::fake([
        PVE.'/nodes/prg1-n2/qemu/1042/status/current' => Http::response(['data' => ['status' => 'running']]),
        PVE.'/nodes/prg1-n2/qemu/1042/config' => Http::response(pveVmConfig()),
    ]);
    [$customer, $org] = $this->customerWithOrganization();
    $instance = pveLab();
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'VPS', 'state' => 'ACTIVE', 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'entitlements' => [], 'desired_spec' => [], 'sla_class' => 'standard']);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => 'prg1-n2', 'meta' => [], 'ownership' => [], 'idempotency_key' => 'b1', 'adapter_version' => '1.0.0']);
    $operation = failedOperation($service);
    $this->actingAs($this->staff('sre'), 'sanctum');

    $operation->forceFill(['attempts' => 4, 'step' => 1, 'context' => ['site_remote_id' => 'gone'], 'started_at' => now()->subHours(3), 'external_handle' => ['kind' => 'x', 'handle' => 'h', 'timeout' => 60]])->save(); // leftovers of the compensated run
    $this->postJson("/v1/staff/provisioning/jobs/{$operation->id}/retry", ['reason' => 'provider recovered'])->assertStatus(202);
    driveOperation($operation);
    $rerun = $operation->fresh(); // the failure was compensated, so the retry started the workflow over with a fresh clock and context
    expect($rerun->attempts)->toBe(1)->and($rerun->started_at?->gt(now()->subMinute()))->toBeTrue()->and((array) $rerun->result)->not->toHaveKey('site_remote_id');
    expect($operation->fresh()->state)->toBe(Operation::SUCCEEDED); // VM already running → power start is a no-op, verify passes
    expect(AuditEvent::query()->where('action', 'provisioning.retry')->where('result', 'succeeded')->exists())->toBeTrue();

    $waiting = failedOperation($service, 'waiting-1');
    $waiting->forceFill(['state' => Operation::WAITING, 'next_run_at' => now()->addMinutes(5)])->save();
    $this->postJson("/v1/staff/provisioning/jobs/{$waiting->id}/cancel", ['reason' => 'duplicate request'])->assertForbidden(); // SRE may retry, cancelling is an infrastructure-admin call
    $infra = $this->staff('infrastructure_admin');
    $this->actingAs($infra, 'sanctum');
    $this->postJson("/v1/staff/provisioning/jobs/{$waiting->id}/cancel", [])->assertUnprocessable()->assertJsonValidationErrors(['reason']);
    $this->postJson("/v1/staff/provisioning/jobs/{$waiting->id}/cancel", ['reason' => 'duplicate request'])->assertForbidden()->assertJsonPath('error', 'step_up_required'); // destructive: fresh step-up
    app(StepUpService::class)->grant($infra, 'totp', null, '127.0.0.1');
    $this->postJson("/v1/staff/provisioning/jobs/{$waiting->id}/cancel", ['reason' => 'duplicate request'])->assertOk()->assertJsonPath('state', Operation::CANCELLED);
    expect(AuditEvent::query()->where('action', 'provisioning.cancel')->where('result', 'succeeded')->exists())->toBeTrue();
});

it('freeze switch needs step-up, drift resolution needs a note', function () {
    Http::fake();
    [$customer, $org] = $this->customerWithOrganization();
    $instance = pveLab();
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'VPS', 'state' => 'ACTIVE', 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'entitlements' => [], 'desired_spec' => [], 'sla_class' => 'standard']);
    $drift = ResourceDrift::query()->create(['service_id' => $service->id, 'field' => 'ram_mb', 'ownership' => 'ONHOST_MANAGED', 'expected' => ['value' => 8192], 'actual' => ['value' => 4096], 'classification' => 'REQUIRES_APPROVAL', 'state' => 'open', 'detected_at' => now()]);
    $sre = $this->staff('sre');
    $this->actingAs($sre, 'sanctum');

    $this->postJson('/v1/staff/provisioning/freeze', ['reason' => 'storage incident'])->assertForbidden();
    app(StepUpService::class)->grant($sre, 'totp', null, '127.0.0.1');
    $this->postJson('/v1/staff/provisioning/freeze', ['reason' => 'storage incident'])->assertOk()->assertJsonPath('frozen', true);
    expect(app(FreezeSwitch::class)->isFrozen())->toBeTrue();
    $this->postJson('/v1/staff/provisioning/thaw')->assertOk()->assertJsonPath('frozen', false);

    $this->getJson('/v1/staff/resource-mappings')->assertOk()->assertHeader('X-Total-Count', '1')->assertJsonPath('data.0.classification', 'REQUIRES_APPROVAL');
    $this->postJson("/v1/staff/resource-mappings/{$drift->id}/resolve", ['resolution' => 'approved', 'note' => 'customer-side change confirmed'])->assertForbidden(); // SRE reads drift, infrastructure admins decide it
    $infra = $this->staff('infrastructure_admin');
    $this->actingAs($infra, 'sanctum');
    $this->postJson("/v1/staff/resource-mappings/{$drift->id}/resolve", ['resolution' => 'approved', 'note' => 'ok'])->assertUnprocessable();
    $this->postJson("/v1/staff/resource-mappings/{$drift->id}/resolve", ['resolution' => 'approved', 'note' => 'customer-side change confirmed'])->assertOk()->assertJsonPath('state', 'approved');
    expect($drift->fresh()->resolved_by)->toBe($infra->id);
    $this->getJson('/v1/staff/resource-mappings')->assertOk()->assertHeader('X-Total-Count', '0');

    $this->actingAs($this->staff('support_l1'), 'sanctum');
    $this->postJson('/v1/staff/provisioning/thaw')->assertForbidden();
});
