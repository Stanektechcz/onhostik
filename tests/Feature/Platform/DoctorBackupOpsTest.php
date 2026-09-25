<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Web\BackupScheduler;

/*
 * TASK-0024 (backup-ops): what the doctor says about server and database backups. Before this, `onhost:doctor` looked at
 * web, managed and mail schedules only, never said that `backups.compute` was still off while servers were sold backups,
 * never named a Proxmox instance without a `backup_storage`, never counted a server backup whose volume could not be
 * removed (`meta.delete_blocked`) or a second volume made by a retried backup (`meta.orphan_volumes`), and never looked at
 * whether the backup tick itself was alive and inside its fifteen minutes.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

/** A managed database the platform provisioned on the lab Proxmox (instance + binding), sold 14 days of backups. */
function doctorBackupOpsDatabase(Organization $org, string $vmid = '2042', array $tags = []): Service
{
    $instance = pveLab();
    $node = Node::query()->where('name', 'prg1-n2')->firstOrFail();
    $entitlements = ['vcpu' => 2, 'ram_mb' => 4096, 'nvme_gb' => 40, 'backup_days' => 14];
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'database', 'family' => 'data', 'name' => 'DB S', 'hostname' => 'db-'.$vmid.'.cust.onhost.cz',
        'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id,
        'desired_spec' => ['executor' => 'proxmox', 'family' => 'data', 'entitlements' => $entitlements], 'entitlements' => $entitlements, 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => $tags,
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => $vmid, 'remote_node' => 'prg1-n2', 'meta' => ['name' => 'db-'.$vmid], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "provision:{$service->id}:qemu", 'adapter_version' => '1.0.0']);

    return $service;
}

/** @return Collection<string, array{area:string, check:string, status:string, detail:string}> the doctor's rows by check */
function doctorBackupOpsRows(): Collection
{
    Artisan::call('onhost:doctor', ['--json' => true]);

    return collect(json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR)['checks'])->keyBy('check');
}

function doctorBackupOpsSwitch(string $rule, bool $on): void
{
    app(AutomationLedger::class)->setEnabled($rule, $on, 'test');
}

it('warns that servers sold backups get none while backups.compute is off', function () {
    [, $org] = $this->customerWithOrganization();
    doctorBackupOpsDatabase($org);

    $row = doctorBackupOpsRows()->get('backups: servers and databases sold backups get them');
    expect($row)->not->toBeNull()
        ->and($row['status'])->toBe('WARN')
        ->and($row['detail'])->toContain('backups.compute')->toContain('1 service(s)')->toContain('onhost:backups:compute-plan');

    doctorBackupOpsSwitch(BackupScheduler::COMPUTE_RULE, true);
    expect(doctorBackupOpsRows()->get('backups: servers and databases sold backups get them')['status'])->toBe('OK');
});

it('says nothing is wrong when no server is sold backups, rule on or off', function () {
    $row = doctorBackupOpsRows()->get('backups: servers and databases sold backups get them');

    expect($row['status'])->toBe('OK');
});

it('names the Proxmox instance without backup_storage, and fails production only once the rule is on', function () {
    [, $org] = $this->customerWithOrganization();
    doctorBackupOpsDatabase($org);
    $instance = ProviderInstance::query()->where('key', 'proxmox-cz1')->firstOrFail();
    $options = (array) $instance->options;
    unset($options['backup_storage']);
    $instance->forceFill(['options' => $options])->save();
    config(['onhost.secrets.driver' => 'db']); // production refuses environment secrets; the lab token is read the same way under `db`
    app()->instance('env', 'production');

    $off = doctorBackupOpsRows()->get('backups: every Proxmox instance carrying sold backups has a backup_storage');
    expect($off)->not->toBeNull()->and($off['status'])->toBe('WARN') // the rule is off: nothing is being lost yet
        ->and($off['detail'])->toContain('proxmox-cz1')->toContain('backup_storage');

    doctorBackupOpsSwitch(BackupScheduler::COMPUTE_RULE, true);
    $on = doctorBackupOpsRows()->get('backups: every Proxmox instance carrying sold backups has a backup_storage');
    expect($on['status'])->toBe('FAIL'); // every tick now misses a paid backup there
});

it('counts stalled and paused schedules of managed databases too', function () {
    [, $org] = $this->customerWithOrganization();
    doctorBackupOpsDatabase($org, '2042', ['backup_schedule' => ['missed' => 3, 'last_error' => 'x']]);
    doctorBackupOpsDatabase($org, '2043', ['backup_schedule' => ['paused_at' => now()->toIso8601String(), 'failures' => 5]]);

    $rows = doctorBackupOpsRows();

    expect($rows->get('backup schedules keeping up')['status'])->toBe('WARN')
        ->and($rows->get('backup schedules keeping up')['detail'])->toContain('1 service(s)')
        ->and($rows->get('no backup schedule is waiting for a person')['status'])->toBe('WARN')
        ->and($rows->get('no backup schedule is waiting for a person')['detail'])->toContain('1 schedule(s)');
});

it('counts expired server backups whose volume could not be removed, with the reason', function () {
    [, $org] = $this->customerWithOrganization();
    $database = doctorBackupOpsDatabase($org);
    $blocked = Backup::query()->create(['service_id' => $database->id, 'organization_id' => $org->id, 'provider_instance_id' => $database->provider_instance_id, 'kind' => 'scheduled', 'state' => 'completed',
        'started_at' => now()->subDays(20), 'finished_at' => now()->subDays(20), 'retention_until' => now()->subDays(6), 'protected' => false, 'remote_id' => 'pbs-cz1:backup/vzdump-qemu-2042-2026_09_01-02_30_00.vma.zst',
        'meta' => ['delete_blocked' => ['at' => now()->toIso8601String(), 'why' => 'the volume is protected at the hypervisor']]]);

    $row = doctorBackupOpsRows()->get('backups: expired server backups are gone from the backup storage');

    expect($row)->not->toBeNull()->and($row['status'])->toBe('WARN')
        ->and($row['detail'])->toContain('1 backup(s)')->toContain($blocked->id)->toContain('protected at the hypervisor');
});

it('counts orphaned backup volumes and never deletes them', function () {
    [, $org] = $this->customerWithOrganization();
    $database = doctorBackupOpsDatabase($org);
    $row = Backup::query()->create(['service_id' => $database->id, 'organization_id' => $org->id, 'provider_instance_id' => $database->provider_instance_id, 'kind' => 'scheduled', 'state' => 'completed',
        'started_at' => now()->subDay(), 'finished_at' => now()->subDay(), 'retention_until' => now()->addDays(13), 'protected' => false, 'remote_id' => 'pbs-cz1:backup/vzdump-qemu-2042-2026_09_24-02_30_40.vma.zst',
        'meta' => ['orphan_volumes' => ['pbs-cz1:backup/vzdump-qemu-2042-2026_09_24-02_30_10.vma.zst']]]);

    $check = doctorBackupOpsRows()->get('backups: no orphaned backup volume');

    expect($check)->not->toBeNull()->and($check['status'])->toBe('WARN')
        ->and($check['detail'])->toContain('1 backup(s)')->toContain($row->id)
        ->and($row->refresh()->state)->toBe('completed'); // report only
});

it('says when the backup tick is missing, errored, late or over its budget', function () {
    $ledger = app(AutomationLedger::class);
    $check = 'backups: the backup tick ran within 30 min and inside its budget';

    expect(doctorBackupOpsRows()->get($check)['status'])->toBe('WARN')
        ->and(doctorBackupOpsRows()->get($check)['detail'])->toContain('no run recorded');

    $ledger->record('backups.run', ['started' => 3, 'errors' => 0, 'seconds' => 40]);
    expect(doctorBackupOpsRows()->get($check)['status'])->toBe('OK');

    $ledger->record('backups.run', ['started' => 3, 'errors' => 0, 'seconds' => 900]);
    expect(doctorBackupOpsRows()->get($check)['status'])->toBe('WARN')
        ->and(doctorBackupOpsRows()->get($check)['detail'])->toContain('900 s')->toContain((string) BackupScheduler::TICK_BUDGET_SECONDS);

    $ledger->record('backups.run', ['started' => 3, 'errors' => 2, 'seconds' => 40]);
    expect(doctorBackupOpsRows()->get($check)['status'])->toBe('WARN')
        ->and(doctorBackupOpsRows()->get($check)['detail'])->toContain('2 error(s)');

    $ledger->record('backups.run', ['started' => 3, 'errors' => 0, 'seconds' => 40]);
    $this->travel(31)->minutes();
    expect(doctorBackupOpsRows()->get($check)['status'])->toBe('WARN');
});

it('adds a coverage row for servers only while backups.compute is on', function () {
    [, $org] = $this->customerWithOrganization();
    $database = doctorBackupOpsDatabase($org);
    $database->forceFill(['created_at' => now()->subDays(10)])->save();
    $check = 'every server sold backups has one from the last 3 days';

    expect(doctorBackupOpsRows()->has($check))->toBeFalse();

    doctorBackupOpsSwitch(BackupScheduler::COMPUTE_RULE, true);
    $row = doctorBackupOpsRows()->get($check);
    expect($row)->not->toBeNull()->and($row['status'])->toBe('WARN')->and($row['detail'])->toContain($database->id);

    Backup::query()->create(['service_id' => $database->id, 'organization_id' => $org->id, 'provider_instance_id' => $database->provider_instance_id, 'kind' => 'scheduled', 'state' => 'completed',
        'started_at' => now()->subHour(), 'finished_at' => now()->subHour(), 'retention_until' => now()->addDays(14), 'protected' => false, 'remote_id' => 'pbs-cz1:backup/vzdump-qemu-2042-2026_09_25-02_30_00.vma.zst']);
    expect(doctorBackupOpsRows()->get($check)['status'])->toBe('OK');
});

it('says which web plans are not backed up as often as sold while backups.as_sold is off', function () {
    [, $org] = $this->customerWithOrganization();
    $web = featureWebService($org, 'ispconfig');
    $web->forceFill(['family' => 'managed', 'entitlements' => array_merge((array) $web->entitlements, ['backup_frequency' => '1h', 'backup_days' => 30])])->save();
    $check = 'backups: every plan is backed up as often and as long as sold';

    $off = doctorBackupOpsRows()->get($check);
    expect($off)->not->toBeNull()->and($off['status'])->toBe('WARN')
        ->and($off['detail'])->toContain('backups.as_sold')->toContain('onhost:backups:frequency-plan')->toContain('1 service(s)');

    doctorBackupOpsSwitch(BackupScheduler::AS_SOLD_RULE, true);
    expect(doctorBackupOpsRows()->get($check)['status'])->toBe('OK');
});
