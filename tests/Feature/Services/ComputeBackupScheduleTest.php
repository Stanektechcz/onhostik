<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Addons;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\BackupPolicy;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\Web\BackupScheduler;

/*
 * A managed database (db-s / db-m, family `data`, one KVM VM on Proxmox) is sold with 14 or 30 days of backups, and
 * nothing ever took one: the scheduler looked at web, managed and mail only, and the features of a server had no
 * backup schedule to read. The same held for a VPS whose customer bought a backup add-on — the add-on wrote a policy
 * nobody read (TASK-0019).
 *
 * Starting to back up existing servers is the owner's decision, so it sits behind the automation rule
 * `backups.compute`, off by default; `onhost:backups:compute-plan` shows who it would touch before it is switched on.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Queue::fake(); // the backup operation itself is not the subject here: what the scheduler asks for is
    $this->travelTo(now()->startOfDay()->setTime(3, 5)); // after the daily slot of 02:30, inside the same day
});

/** A managed database the platform provisioned on the lab Proxmox: an instance, a binding, the plan's backup days. */
function computeBackupDataService(Organization $org, array $entitlements = ['vcpu' => 2, 'ram_mb' => 4096, 'nvme_gb' => 40, 'backup_days' => 14, 'pitr_days' => 7], string $family = 'data', string $vmid = '2042'): Service
{
    $instance = pveLab();
    $node = Node::query()->where('name', 'prg1-n2')->firstOrFail();
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => $family === 'data' ? 'database' : 'vps', 'family' => $family, 'name' => $family === 'data' ? 'DB S' : 'Compute 4', 'hostname' => 'db-'.$vmid.'.cust.onhost.cz',
        'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id,
        'desired_spec' => ['executor' => 'proxmox', 'family' => $family, 'entitlements' => $entitlements], 'entitlements' => $entitlements, 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => [],
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => $vmid, 'remote_node' => 'prg1-n2', 'meta' => ['name' => 'db-'.$vmid], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "provision:{$service->id}:qemu", 'adapter_version' => '1.0.0']);

    return $service;
}

/** The backup add-on bought for a VPS, as checkout leaves it: an active add-on service and the policy it wrote. */
function computeBackupAddon(Service $parent, string $product = 'backup-plus', array $entitlements = ['daily' => 30, 'weekly' => 4, 'monthly' => 6, 'offsite' => true, 'restore_test' => 'monthly'], string $state = ServiceStateMachine::ACTIVE): Service
{
    $addon = Service::query()->create(['organization_id' => $parent->organization_id, 'product_key' => $product, 'family' => 'addon', 'name' => 'Zálohy VPS', 'state' => $state, 'region_code' => 'cz1',
        'entitlements' => $entitlements, 'desired_spec' => ['parent_service_id' => $parent->id, 'addon' => $product], 'tags' => ['parent_service_id' => $parent->id], 'sla_class' => 'standard', 'activated_at' => now()]);
    BackupPolicy::query()->create((array) Addons::backupPolicy($product, $entitlements) + ['service_id' => $parent->id, 'product_key' => $product, 'state' => 'active']);

    return $addon;
}

/**
 * Many web sites on the lab ISPConfig, each with its own site id (a binding is unique per instance and remote id).
 *
 * @return Collection<int, Service>
 */
function computeBackupWebFleet(Organization $org, int $count)
{
    $first = featureWebService($org, 'ispconfig');
    $binding = $first->primaryBinding();

    return collect([$first])->concat(collect(range(2, $count))->map(function (int $i) use ($first, $binding) {
        $service = $first->replicate(['id', 'name_prefix'])->fill(['name' => 'Webhosting '.$i, 'hostname' => 'shop'.$i.'.cz']);
        $service->save();
        ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $binding->provider_instance_id, 'remote_type' => $binding->remote_type, 'remote_id' => (string) (1000 + $i),
            'remote_node' => $binding->remote_node, 'meta' => $binding->meta, 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "fleet:{$service->id}", 'adapter_version' => '1.0.0']);

        return $service;
    }));
}

function computeBackupSwitch(bool $on): void
{
    app(AutomationLedger::class)->setEnabled(BackupScheduler::COMPUTE_RULE, $on, 'test');
}

/** @return Collection<int, Operation> */
function computeBackupOperations(Service $service)
{
    return Operation::query()->where('service_id', $service->id)->where('idempotency_key', 'like', 'backup:auto:'.$service->id.':%')->get();
}

/**
 * The backup storage as Proxmox shows it: volumes by volid (protected or not), refusing to delete a protected one.
 *
 * @param  array{volumes:array<string,bool>, calls:list<string>}  $pve
 */
function computeBackupStorageFake(array &$pve): void
{
    Http::fake(function (Request $r) use (&$pve) {
        $path = rawurldecode((string) parse_url($r->url(), PHP_URL_PATH));
        $pve['calls'][] = $r->method().' '.$path;
        if (str_ends_with($path, '/nodes')) {
            return Http::response(['data' => [['node' => 'prg1-n2', 'status' => 'online']]]);
        }
        if (preg_match('~/storage/pbs-cz1/content/(.+)$~', $path, $m) === 1) {
            $volid = $m[1];
            if (! array_key_exists($volid, $pve['volumes'])) {
                return Http::response(['errors' => ['volume' => 'does not exist']], 404);
            }
            if ($r->method() === 'PUT') {
                $pve['volumes'][$volid] = (bool) ($r->data()['protected'] ?? true);

                return Http::response(['data' => null]);
            }
            if ($r->method() === 'DELETE') {
                if ($pve['volumes'][$volid]) {
                    return Http::response(['errors' => ['volume' => 'backup is protected']], 400);
                }
                unset($pve['volumes'][$volid]);

                return Http::response(['data' => 'UPID:prg1-n2:delete']);
            }
        }

        return Http::response(['data' => []]);
    });
}

/** A finished vzdump backup of the service's VM, as the backup step leaves it. */
function computeBackupRow(Service $service, string $volid, string $kind, array $extra = []): Backup
{
    return Backup::query()->create(array_merge(['service_id' => $service->id, 'organization_id' => $service->organization_id, 'provider_instance_id' => $service->provider_instance_id, 'kind' => $kind, 'state' => 'completed',
        'started_at' => now()->subDays(20), 'finished_at' => now()->subDays(20), 'retention_until' => now()->subDays(6), 'protected' => false, 'remote_id' => $volid, 'remote_datastore' => 'pbs-cz1'], $extra));
}

it('keeps the rule off by default: a managed database sold with backups gets none, web backups run as before', function () {
    [, $org] = $this->customerWithOrganization();
    $database = computeBackupDataService($org);
    $web = featureWebService($org, 'ispconfig');

    expect(app(AutomationLedger::class)->enabled(BackupScheduler::COMPUTE_RULE))->toBeFalse(); // nobody switched it on
    $stats = app(BackupScheduler::class)->tick();

    expect(computeBackupOperations($database))->toHaveCount(0)
        ->and(computeBackupOperations($web))->toHaveCount(1) // today's behaviour for the web
        ->and($stats['started'])->toBe(1)
        ->and(BackupScheduler::health($database->fresh()))->toBe([]) // not even a record is written on the server
        ->and(app(ServiceFeatures::class)->features($database->fresh()))->not->toHaveKey('backup_schedule'); // the customer's feature list is unchanged
});

it('backs a managed database up once per slot with the retention its plan sells, once the rule is on', function () {
    [, $org] = $this->customerWithOrganization();
    $database = computeBackupDataService($org);
    computeBackupSwitch(true);

    app(BackupScheduler::class)->tick();
    $ops = computeBackupOperations($database);
    expect($ops)->toHaveCount(1);
    $op = $ops->first();
    expect($op->idempotency_key)->toBe('backup:auto:'.$database->id.':'.now()->format('Ymd').'0230')
        ->and($op->desired['action'])->toBe('backup')
        ->and($op->desired['kind'])->toBe('scheduled')
        ->and($op->desired['retention_days'])->toBe(14); // db-s sells 14 days

    app(BackupScheduler::class)->tick(); // the same slot looked at again
    expect(computeBackupOperations($database))->toHaveCount(1);

    $schedule = app(BackupScheduler::class)->scheduleFor($database->fresh());
    expect($schedule['frequency'])->toBe('daily')->and($schedule['days'])->toBe(14)->and($schedule['generations'])->toBe(14);
});

it('lets the customer set a database schedule within its plan only while the rule is on', function () {
    [$user, $org] = $this->customerWithOrganization();
    $database = computeBackupDataService($org);
    $url = "/v1/services/{$database->id}/backups/schedule";

    $this->actingAs($user, 'sanctum')->putJson($url, ['frequency' => 'daily'])->assertUnprocessable()->assertJsonPath('error', 'feature_unavailable');

    computeBackupSwitch(true);
    $this->actingAs($user, 'sanctum')->withHeader('Idempotency-Key', 'db-sched-1')->putJson($url, ['frequency' => 'hourly'])->assertUnprocessable()->assertJsonPath('error', 'backup_frequency_above_plan');
    $this->actingAs($user, 'sanctum')->withHeader('Idempotency-Key', 'db-sched-2')->putJson($url, ['frequency' => 'weekly', 'days' => 40, 'generations' => 40])->assertOk();

    $policy = BackupPolicy::query()->where('service_id', $database->id)->sole();
    expect($policy->schedule['frequency'])->toBe('weekly')->and($policy->retention['days'])->toBe(14)->and($policy->retention['generations'])->toBe(14); // never above what db-s sells
});

it('gives a managed database with no backup days no schedule, rule on or not', function () {
    [, $org] = $this->customerWithOrganization();
    $database = computeBackupDataService($org, ['vcpu' => 2, 'ram_mb' => 4096, 'nvme_gb' => 40, 'backup_days' => 0]);
    computeBackupSwitch(true);

    app(BackupScheduler::class)->tick();

    expect(computeBackupOperations($database))->toHaveCount(0);
});

it('backs a VPS up only while the backup add-on bought for it is active', function () {
    [, $org] = $this->customerWithOrganization();
    $vps = computeBackupDataService($org, ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160, 'backup' => 'addon'], 'cloud', '3042');
    computeBackupSwitch(true);

    app(BackupScheduler::class)->tick();
    expect(computeBackupOperations($vps))->toHaveCount(0); // a VPS without the add-on sells no backups

    $addon = computeBackupAddon($vps);
    app(BackupScheduler::class)->tick();
    $ops = computeBackupOperations($vps);
    expect($ops)->toHaveCount(1)->and($ops->first()->desired['retention_days'])->toBe(30);
    $schedule = app(BackupScheduler::class)->scheduleFor($vps->fresh());
    expect($schedule['frequency'])->toBe('daily')->and($schedule['generations'])->toBe(40);

    // the add-on cancelled, but its policy row left behind (the customer re-saved it under the plan's own key): no more free backups
    $addon->forceFill(['state' => ServiceStateMachine::SUSPENDED])->save();
    expect(app(BackupScheduler::class)->scheduleFor($vps->fresh()))->toBeNull();
});

it('removes an expired scheduled backup from the backup storage, and never a final archive, a protected one or one under legal hold', function () {
    [, $org] = $this->customerWithOrganization();
    $database = computeBackupDataService($org);
    $held = computeBackupDataService($org, vmid: '2043');
    $held->forceFill(['legal_hold' => true])->save();
    computeBackupSwitch(true);
    $old = 'pbs-cz1:backup/vzdump-qemu-2042-2026_09_01-02_30_00.vma.zst';
    $final = 'pbs-cz1:backup/vzdump-qemu-2042-2026_08_01-02_30_00.vma.zst';
    $kept = 'pbs-cz1:backup/vzdump-qemu-2042-2026_09_02-02_30_00.vma.zst';
    $heldVol = 'pbs-cz1:backup/vzdump-qemu-2043-2026_09_01-02_30_00.vma.zst';
    $expired = computeBackupRow($database, $old, 'scheduled');
    $finalRow = computeBackupRow($database, $final, 'final'); // even unprotected, the final archive is FinalArchive's alone
    $protectedRow = computeBackupRow($database, $kept, 'scheduled', ['protected' => true]);
    $heldRow = computeBackupRow($held, $heldVol, 'scheduled');
    $pve = ['volumes' => [$old => false, $final => false, $kept => true, $heldVol => false], 'calls' => []];
    computeBackupStorageFake($pve);

    $stats = app(BackupScheduler::class)->tick();

    expect($stats['deleted'])->toBe(1)
        ->and($expired->refresh()->state)->toBe('deleted')
        ->and(data_get($expired->meta, 'deleted_by'))->toBe('retention')
        ->and($pve['volumes'])->not->toHaveKey($old); // gone from the backup storage, not only from our table
    // unprotected first, then deleted — exactly as the final archive's own expiry does it
    $calls = array_values(array_filter($pve['calls'], fn (string $c) => str_contains($c, '/content/')));
    expect($calls)->toBe(['PUT /api2/json/nodes/prg1-n2/storage/pbs-cz1/content/'.$old, 'DELETE /api2/json/nodes/prg1-n2/storage/pbs-cz1/content/'.$old]);

    expect($finalRow->refresh()->state)->toBe('completed')->and($pve['volumes'])->toHaveKey($final)
        ->and($protectedRow->refresh()->state)->toBe('completed')->and($pve['volumes'])->toHaveKey($kept)
        ->and($heldRow->refresh()->state)->toBe('completed')->and($pve['volumes'])->toHaveKey($heldVol);
});

it('keeps the row when the backup storage could not remove the volume, and tries again next time', function () {
    [, $org] = $this->customerWithOrganization();
    $database = computeBackupDataService($org);
    computeBackupSwitch(true);
    $vol = 'pbs-cz1:backup/vzdump-qemu-2042-2026_09_01-02_30_00.vma.zst';
    $row = computeBackupRow($database, $vol, 'scheduled');
    Http::fake(fn () => Http::response(['errors' => ['node' => 'down']], 500));

    $stats = app(BackupScheduler::class)->tick();

    expect($row->refresh()->state)->toBe('completed')->and($stats['deleted'])->toBe(0)
        ->and(data_get($row->meta, 'delete_blocked.why'))->not->toBeNull(); // the row says why the volume is still there
});

it('never takes a volume it cannot prove belongs to the service\'s own VM', function () {
    [, $org] = $this->customerWithOrganization();
    $database = computeBackupDataService($org);
    computeBackupSwitch(true);
    $stranger = 'pbs-cz1:backup/vzdump-qemu-777-2026_09_01-02_30_00.vma.zst'; // another VM's backup, adopted by mistake
    $row = computeBackupRow($database, $stranger, 'scheduled');
    $pve = ['volumes' => [$stranger => false], 'calls' => []];
    computeBackupStorageFake($pve);

    app(BackupScheduler::class)->tick();

    expect($row->refresh()->state)->toBe('completed')->and($pve['volumes'])->toHaveKey($stranger)
        ->and(array_filter($pve['calls'], fn (string $c) => str_contains($c, '/content/')))->toBe([]);
});

it('never prunes a final archive of a web service either, even an unprotected one past its date', function () {
    Storage::fake('local');
    [, $org] = $this->customerWithOrganization();
    $web = featureWebService($org, 'ispconfig');
    $set = FinalArchive::PREFIX.'/'.$org->id.'/'.$web->id.'-20260801-020000';
    Storage::disk('local')->put($set.'/manifest.json', '{}');
    $final = Backup::query()->create(['service_id' => $web->id, 'organization_id' => $org->id, 'kind' => 'final', 'state' => 'completed', 'protected' => false,
        'started_at' => now()->subDays(70), 'finished_at' => now()->subDays(70), 'retention_until' => now()->subDays(10), 'meta' => ['set' => $set]]);

    app(BackupScheduler::class)->tick();

    expect($final->refresh()->state)->toBe('completed')->and(Storage::disk('local')->exists($set.'/manifest.json'))->toBeTrue();
});

it('lists the servers the rule would start backing up, and writes nothing', function () {
    [, $org] = $this->customerWithOrganization();
    $database = computeBackupDataService($org);
    $covered = computeBackupDataService($org, ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160], 'cloud', '3051');
    computeBackupAddon($covered, 'backup-hourly', ['interval_hours' => 1, 'retention_days' => 30, 'offsite' => true]);
    $plain = computeBackupDataService($org, ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160], 'cloud', '3050'); // no add-on: not listed
    $web = featureWebService($org, 'ispconfig');
    $operations = Operation::query()->count();
    $backups = Backup::query()->count();
    $policies = BackupPolicy::query()->count();

    expect(Artisan::call('onhost:backups:compute-plan'))->toBe(0);
    $out = Artisan::output();

    expect($out)->toContain($database->id)->toContain('data')->toContain('daily')->toContain('14')->toContain('pbs-cz1')->toContain('off')
        ->toContain($covered->id)->toContain('hourly')->toContain('720')
        ->not->toContain($plain->id)->not->toContain($web->id);
    expect(Operation::query()->count())->toBe($operations)
        ->and(Backup::query()->count())->toBe($backups)
        ->and(BackupPolicy::query()->count())->toBe($policies)
        ->and($database->fresh()->tags)->toBe([])
        ->and(app(AutomationLedger::class)->enabled(BackupScheduler::COMPUTE_RULE))->toBeFalse();
});

it('visits every due service in one tick, not only the first hundred', function () {
    [, $org] = $this->customerWithOrganization();
    $webs = computeBackupWebFleet($org, 105);

    $stats = app(BackupScheduler::class)->tick(); // the command's default --limit=100 is now the size of one chunk

    $started = Operation::query()->where('idempotency_key', 'like', 'backup:auto:%')->pluck('service_id')->unique();
    expect($started)->toHaveCount(105)->and($stats['started'])->toBe(105)
        ->and($webs->pluck('id')->diff($started)->all())->toBe([]); // before: the services after the first hundred by id never got a backup

    app(BackupScheduler::class)->tick(); // the same slot again: still one operation per service
    expect(Operation::query()->where('idempotency_key', 'like', 'backup:auto:%')->count())->toBe(105);
});

it('walks the servers in chunks too: a chunk of one still reaches every managed database', function () {
    [, $org] = $this->customerWithOrganization();
    $databases = collect(['2101', '2102', '2103'])->map(fn (string $vmid) => computeBackupDataService($org, vmid: $vmid));
    computeBackupSwitch(true);

    app(BackupScheduler::class)->tick(1);

    expect($databases->every(fn (Service $db) => computeBackupOperations($db)->count() === 1))->toBeTrue()
        ->and(app(BackupScheduler::class)->computePlan(1))->toHaveCount(3);
});
