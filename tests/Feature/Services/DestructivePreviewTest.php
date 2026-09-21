<?php

declare(strict_types=1);

use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\DestructivePreview;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Errors\DomainError;

/*
 * "Opravdu?" is not a confirmation (Brain card H414, audit §5ai). The customer is told the service by name, the
 * things that would actually go, what else hangs on them and how far back they could come — and the answer carries a
 * fingerprint of the target. The card's acceptance scenario is the security of it: **changing the target after the
 * preview requires a new confirmation**. Against the old code nothing of this existed: a destructive action took a
 * `confirm: true` flag at most, which says nothing about what is being confirmed.
 */

it('says what would go, what hangs on it and how far back one could come', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $vps = previewVps($org);
    Service::query()->create([ // an add-on bought for this server
        'organization_id' => $org->id, 'product_key' => 'backup-hourly', 'family' => 'addon', 'name' => 'Hodinové zálohy', 'state' => ServiceStateMachine::ACTIVE,
        'region_code' => $vps->region_code, 'provider_instance_id' => $vps->provider_instance_id, 'entitlements' => [], 'sla_class' => 'standard',
        'activated_at' => now(), 'tags' => ['parent_service_id' => $vps->id], 'desired_spec' => [],
    ]);
    $this->actingAs($owner, 'sanctum');

    $preview = $this->getJson("/v1/services/{$vps->id}/actions/terminate/preview", ['X-Organization' => $org->id])->assertOk()->json('data');
    expect($preview['service']['id'])->toBe($vps->id)
        ->and(implode(' ', $preview['what']))->toContain('ochranné lhůtě')
        ->and(implode(' ', $preview['depends']))->toContain('backup-hourly')->toContain('Předplatné')
        ->and($preview['recovery'])->toMatchArray(['kind' => 'final_archive'])
        ->and($preview['recovery']['note'])->toContain('60')
        ->and($preview['fingerprint'])->toHaveLength(64);

    // a restore names the backup it would write over the service, and the copy that is made before it
    $backup = Backup::query()->create(['service_id' => $vps->id, 'organization_id' => $org->id, 'kind' => 'manual', 'state' => 'completed', 'remote_id' => 'pbs:1', 'finished_at' => now()->subDay(), 'size_bytes' => 1024]);
    $restore = $this->getJson("/v1/services/{$vps->id}/actions/restore/preview?params[backup_id]={$backup->id}", ['X-Organization' => $org->id])->assertOk()->json('data');
    expect(implode(' ', $restore['what']))->toContain('přepíše zálohou')
        ->and($restore['recovery']['kind'])->toBe('safety_copy')->and($restore['recovery']['note'])->toContain('Než cokoli přepíšeme');

    // an action that destroys nothing has no preview to give
    $this->getJson("/v1/services/{$vps->id}/actions/power/preview", ['X-Organization' => $org->id])->assertStatus(422)->assertJsonPath('error', 'action_not_destructive');
});

it('refuses a confirmation that no longer describes what would happen', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $vps = previewVps($org);
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $this->actingAs($owner, 'sanctum');
    $older = Backup::query()->create(['service_id' => $vps->id, 'organization_id' => $org->id, 'kind' => 'manual', 'state' => 'completed', 'remote_id' => 'pbs:1', 'finished_at' => now()->subDays(3), 'size_bytes' => 10]);
    $newer = Backup::query()->create(['service_id' => $vps->id, 'organization_id' => $org->id, 'kind' => 'manual', 'state' => 'completed', 'remote_id' => 'pbs:2', 'finished_at' => now()->subHour(), 'size_bytes' => 20]);

    $preview = $this->getJson("/v1/services/{$vps->id}/actions/restore/preview?params[backup_id]={$older->id}", ['X-Organization' => $org->id])->assertOk()->json('data');

    // the same confirmation, pointed at another backup: this is the click the customer never saw
    $this->withHeader('Idempotency-Key', 'prev-1')
        ->postJson("/v1/services/{$vps->id}/actions", ['action' => 'restore', 'params' => ['backup_id' => $newer->id], 'confirm' => $preview['fingerprint']], ['X-Organization' => $org->id])
        ->assertStatus(409)->assertJsonPath('error', 'target_changed');

    // and the one it was taken for is accepted
    $this->withHeader('Idempotency-Key', 'prev-2')
        ->postJson("/v1/services/{$vps->id}/actions", ['action' => 'restore', 'params' => ['backup_id' => $older->id], 'confirm' => $preview['fingerprint']], ['X-Organization' => $org->id])
        ->assertStatus(202);
});

it('goes stale when the service itself changed under the preview', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $vps = previewVps($org);
    $preview = app(DestructivePreview::class)->of($vps, 'terminate');

    $vps->forceFill(['state' => ServiceStateMachine::DEGRADED])->save(); // the same service, not the same situation
    expect(fn () => app(DestructivePreview::class)->assertFresh($vps->fresh(), 'terminate', [], $preview['fingerprint']))
        ->toThrow(fn (DomainError $e) => expect($e->error)->toBe('target_changed'));

    // an action that cannot go stale is never refused this way
    app(DestructivePreview::class)->assertFresh($vps->fresh(), 'power', [], 'whatever');
});

/** A delivered VPS with a subscription behind it. */
function previewVps(object $org): Service
{
    $instance = pveLab();
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'label' => 'muj-server', 'hostname' => 'vm-test.cust.onhost.cz',
        'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id,
        'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'], 'entitlements' => ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160], 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => [],
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => 'prg1-n2', 'meta' => ['name' => 'vm-test'], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "prev:{$service->id}", 'adapter_version' => '1.0.0']);
    Subscription::query()->create([
        'organization_id' => $org->id, 'service_id' => $service->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => 44900,
        'state' => Subscription::ACTIVE, 'current_period_start' => now()->subDays(5), 'current_period_end' => now()->addDays(25),
        'next_renewal_at' => now()->addDays(25), 'auto_renew' => true, 'renewal_priority' => 'normal',
    ]);

    return $service;
}
