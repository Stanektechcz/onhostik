<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Ipam\IpamService;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Tests\TestCase;

/*
 * What a customer may say about a core action (Brain card H21: a VPS is created, restarted and restored "without
 * access to another customer"). The workflow reads its parameters from one bag — the size to resize to, whether the
 * archive before a deletion is skipped, the kind of a backup, the VM a restore lands in — and the customer API used to
 * hand that bag over as it came. A member could resize a VPS for free, switch off the mandatory archive before a
 * cancellation, mark a backup as the final one, and restore a backup into a VM id of their choosing.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Queue::fake(); // the subject is what reaches the workflow, not the workflow
});

function paramsVps(Organization $org, string $vmid): Service
{
    $instance = pveLab();
    $node = Node::query()->where('name', 'prg1-n2')->firstOrFail();
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'hostname' => "vm-{$vmid}.cust.onhost.cz", 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id,
        'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'], 'entitlements' => ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160], 'sla_class' => 'standard', 'activated_at' => now(),
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => $vmid, 'remote_node' => 'prg1-n2', 'meta' => [], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "provision:{$service->id}", 'adapter_version' => '1.0.0']);
    app(IpamService::class)->allocate(4, 'cz1', 'vps', $service->id, $org->id);

    return $service;
}

function paramsBackup(Service $service, string $state = 'completed'): Backup
{
    return Backup::query()->create(['service_id' => $service->id, 'organization_id' => $service->organization_id, 'kind' => 'manual', 'state' => $state, 'remote_id' => $state === 'completed' ? 'pbs-cz1:backup/vm/'.Str::random(6) : null, 'started_at' => now()->subHour(), 'finished_at' => $state === 'completed' ? now()->subHour() : null, 'size_bytes' => 1024]);
}

function paramsPost(TestCase $test, string $uri, array $body = []): TestResponse
{
    return $test->withHeader('Idempotency-Key', (string) Str::ulid())->postJson($uri, $body);
}

function paramsFinish(string $operationId): array
{
    $operation = Operation::query()->findOrFail($operationId);
    $desired = (array) $operation->desired;
    $operation->forceFill(['state' => Operation::SUCCEEDED])->save(); // make room for the next request

    return $desired;
}

it('keeps only what a customer may choose: no free resize, no skipped archive, no backup dressed as final, no restore elsewhere', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [, $otherOrg] = $this->customerWithOrganization();
    $vps = paramsVps($org, '1042');
    $second = paramsVps($org, '1043');
    $foreign = paramsVps($otherOrg, '1044');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $this->actingAs($owner, 'sanctum');
    $base = "/v1/services/{$vps->id}";

    // the size of a service is what was paid for
    paramsPost($this, "{$base}/resize", ['params' => ['entitlements' => ['vcpu' => 32, 'ram_mb' => 131072, 'nvme_gb' => 2000]]])->assertForbidden()->assertJsonPath('error', 'resize_requires_plan_change');
    paramsPost($this, "{$base}/actions", ['action' => 'resize', 'params' => ['entitlements' => ['ram_mb' => 65536]]])->assertForbidden()->assertJsonPath('error', 'resize_requires_plan_change');
    expect(Operation::query()->where('service_id', $vps->id)->exists())->toBeFalse()->and($vps->fresh()->entitlements['ram_mb'])->toBe(8192);

    // a backup is a backup: its kind, retention and protection are the platform's
    $backup = paramsFinish(paramsPost($this, "{$base}/backup", ['reason' => 'před aktualizací', 'params' => ['kind' => 'final', 'retention_days' => 36500, 'protected' => true, 'policy' => ['storage' => 'somewhere-else']]])->assertStatus(202)->json('operation_id'));
    expect($backup)->toMatchArray(['action' => 'backup', 'reason' => 'před aktualizací'])->not->toHaveKeys(['kind', 'retention_days', 'protected', 'policy']);

    // a restore goes onto the service the backup was taken from, into its own VM — and nowhere the request says
    $own = paramsBackup($vps);
    $restore = paramsFinish(paramsPost($this, "{$base}/restore", ['params' => ['backup_id' => $own->id, 'target' => 'new', 'options' => ['vmid' => 1044, 'storage' => 'other-pool', 'start' => 1]]])->assertStatus(202)->json('operation_id'));
    expect($restore)->toMatchArray(['action' => 'restore', 'backup_id' => $own->id])->not->toHaveKeys(['options', 'target']);
    foreach ([paramsBackup($foreign)->id, paramsBackup($second)->id, 'bkp_does_not_exist'] as $notMine) { // another customer's, another service's, nobody's
        paramsPost($this, "{$base}/restore", ['params' => ['backup_id' => $notMine]])->assertNotFound();
    }
    paramsPost($this, "{$base}/restore", ['params' => ['backup_id' => paramsBackup($vps, 'running')->id]])->assertStatus(409)->assertJsonPath('error', 'backup_not_restorable');
    paramsPost($this, "/v1/services/{$foreign->id}/restore", ['params' => ['backup_id' => $own->id]])->assertForbidden(); // and somebody else's service is not mine to restore onto

    // the archive before a cancellation is not the customer's to switch off
    $terminate = paramsFinish(paramsPost($this, "{$base}/terminate", ['reason' => 'končíme', 'params' => ['archive_before_delete' => false, 'archive_skip_reason' => 'nechci zálohu', 'force' => true]])->assertStatus(202)->json('operation_id'));
    expect($terminate)->toMatchArray(['action' => 'terminate', 'reason' => 'končíme'])->not->toHaveKeys(['archive_before_delete', 'archive_skip_reason', 'force']);
});

it('leaves staff their overrides, on the record', function () {
    [, $org] = $this->customerWithOrganization();
    $vps = paramsVps($org, '1042');
    $vps->forceFill(['state' => ServiceStateMachine::SUSPENDED, 'terminate_at' => now()->addDays(20)])->save();
    $staff = $this->staff('platform_owner');
    app(StepUpService::class)->grant($staff, 'totp', null, '127.0.0.1');
    $this->actingAs($staff, 'sanctum');

    $operationId = paramsPost($this, "/v1/staff/provisioning/services/{$vps->id}/purge", ['reason' => 'soudní příkaz k okamžitému odstranění'])->assertSuccessful()->json('operation_id');
    expect((array) Operation::query()->findOrFail($operationId)->desired)->toMatchArray(['action' => 'purge', 'force' => true]); // a forced purge inside the restore window stays a staff decision with a reason
});
