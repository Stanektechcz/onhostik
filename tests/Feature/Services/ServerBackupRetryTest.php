<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\OperationRunner;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;

/*
 * TASK-0024 (backup-ops): a server backup whose POST /vzdump started the dump but whose answer was lost (a 5xx from a
 * proxy, a timeout: retryable) was retried by starting a SECOND vzdump — the retry runs the step from the top, lists
 * the storage as "before" (the first volume already in it) and dumps again. The first volume carried the row's marker
 * but was never the row's remote id, so no retention ever removed it: a full copy of the VM left on the storage for good.
 * (A retry after the task finished is a re-poll and never dumped twice; that path is unchanged.)
 *
 * A retry now looks for the volume its first attempt made (by the row's own marker) before it dumps again. A second
 * volume carrying the same marker is written down on the row (`meta.orphan_volumes`) for the doctor — and never deleted
 * automatically: a Proxmox volume is removed only through the proven retention path.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Queue::fake();
    $this->travelTo(now()->startOfDay()->setTime(3, 5));
});

function serverBackupRetryDatabase(Organization $org, string $vmid = '2042'): Service
{
    $instance = pveLab();
    $node = Node::query()->where('name', 'prg1-n2')->firstOrFail();
    $entitlements = ['vcpu' => 2, 'ram_mb' => 4096, 'nvme_gb' => 40, 'backup_days' => 14];
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'database', 'family' => 'data', 'name' => 'DB S', 'hostname' => 'db-'.$vmid.'.cust.onhost.cz',
        'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id,
        'desired_spec' => ['executor' => 'proxmox', 'family' => 'data', 'entitlements' => $entitlements], 'entitlements' => $entitlements, 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => [],
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => $vmid, 'remote_node' => 'prg1-n2', 'meta' => ['name' => 'db-'.$vmid], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "provision:{$service->id}:qemu", 'adapter_version' => '1.0.0']);

    return $service;
}

function serverBackupRetryDrive(Operation $operation, int $maxTicks = 60): Operation
{
    for ($i = 0; $i < $maxTicks; $i++) {
        $operation->refresh();
        if ($operation->isTerminal() || $operation->state === Operation::FAILED) {
            break;
        }
        if ($operation->next_run_at !== null && $operation->next_run_at->isFuture()) {
            Date::setTestNow($operation->next_run_at->copy()->addSecond());
        }
        app(OperationRunner::class)->tick($operation, 60);
    }

    return $operation->refresh();
}

/**
 * The lab Proxmox for one backup: every POST /vzdump is counted and its notes kept; the storage listing shows nothing
 * for its first `$hiddenLists` reads (the volume not visible yet), then what `$volumes` builds from the notes.
 *
 * @param  array{dumps:int, notes:?string, lists:int, calls:list<string>}  $pve
 * @param  callable(string):list<array<string,mixed>>  $volumes
 */
function serverBackupRetryFake(array &$pve, int $hiddenLists, callable $volumes, bool $firstDumpUnanswered = false): void
{
    Http::fake(function (Request $r) use (&$pve, $hiddenLists, $volumes, $firstDumpUnanswered) {
        $path = rawurldecode((string) parse_url($r->url(), PHP_URL_PATH));
        $pve['calls'][] = $r->method().' '.$path;
        if (str_ends_with($path, '/vzdump')) {
            $pve['dumps']++;
            $pve['notes'] = (string) $r['notes-template'];
            if ($firstDumpUnanswered && $pve['dumps'] === 1) {
                return Http::response(['data' => null], 502); // the node started the dump; the answer never came back whole
            }

            return Http::response(['data' => 'UPID:prg1-n2:000A1C30:0004E2F9:66F0AB15:vzdump:2042:onhost@pve!cp:']);
        }
        if (str_contains($path, '/storage/pbs-cz1/content')) {
            $pve['lists']++;
            $notes = str_replace(['{{guestname}}', '{{vmid}}'], ['db-2042', '2042'], (string) $pve['notes']);

            return Http::response(['data' => $pve['lists'] <= $hiddenLists || $pve['notes'] === null ? [] : $volumes($notes)]);
        }
        if (str_contains($path, '/tasks/')) {
            return Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]);
        }

        return Http::response(['data' => []]);
    });
}

it('adopts the first attempt\'s volume on a retry instead of dumping again', function () {
    [$user, $org] = $this->customerWithOrganization();
    $database = serverBackupRetryDatabase($org);
    $pve = ['dumps' => 0, 'notes' => null, 'lists' => 0, 'calls' => []];
    $volid = 'pbs-cz1:backup/vzdump-qemu-2042-2026_09_25-03_05_30.vma.zst';
    // the first POST /vzdump starts the dump but its answer is lost (a retryable 502): the retry used to dump a second time
    serverBackupRetryFake($pve, 1, fn (string $notes) => [['volid' => $volid, 'ctime' => time(), 'size' => 4242, 'protected' => 0, 'notes' => $notes]], true);

    $operation = serverBackupRetryDrive(app(ServiceService::class)->requestAction($database, 'backup', $this->contextFor($user, $org), 'db-backup-retry', ['kind' => 'scheduled', 'retention_days' => 14]));

    expect($operation->state)->toBe(Operation::SUCCEEDED, (string) data_get($operation->error, 'message', ''))
        ->and($pve['dumps'])->toBe(1); // one vzdump, not a second full copy of the VM
    $backup = Backup::query()->where('operation_id', $operation->id)->sole();
    expect($backup->state)->toBe('completed')->and($backup->remote_id)->toBe($volid)->and($backup->size_bytes)->toBe(4242)
        ->and(data_get($backup->meta, 'orphan_volumes'))->toBeNull();
});

it('records a second volume carrying the row\'s marker as an orphan, and deletes nothing', function () {
    [$user, $org] = $this->customerWithOrganization();
    $database = serverBackupRetryDatabase($org);
    $pve = ['dumps' => 0, 'notes' => null, 'lists' => 0, 'calls' => []];
    $older = 'pbs-cz1:backup/vzdump-qemu-2042-2026_09_25-03_05_10.vma.zst';
    $newer = 'pbs-cz1:backup/vzdump-qemu-2042-2026_09_25-03_05_40.vma.zst';
    serverBackupRetryFake($pve, 1, fn (string $notes) => [
        ['volid' => $older, 'ctime' => time() - 30, 'size' => 4000, 'protected' => 0, 'notes' => $notes],
        ['volid' => $newer, 'ctime' => time(), 'size' => 4100, 'protected' => 0, 'notes' => $notes],
        ['volid' => 'pbs-cz1:backup/vzdump-qemu-2042-2026_09_25-03_05_50.vma.zst', 'ctime' => time() + 10, 'size' => 9, 'protected' => 0, 'notes' => 'db-2042 nightly by hand'], // an operator's own: not ours, not an orphan
    ], true);

    $operation = serverBackupRetryDrive(app(ServiceService::class)->requestAction($database, 'backup', $this->contextFor($user, $org), 'db-backup-orphan', ['kind' => 'scheduled', 'retention_days' => 14]));

    expect($operation->state)->toBe(Operation::SUCCEEDED, (string) data_get($operation->error, 'message', ''));
    $backup = Backup::query()->where('operation_id', $operation->id)->sole();
    expect($backup->remote_id)->toBe($newer)
        ->and(data_get($backup->meta, 'orphan_volumes'))->toBe([$older])
        ->and(array_filter($pve['calls'], fn (string $c) => str_starts_with($c, 'DELETE') || str_starts_with($c, 'PUT')))->toBe([]); // report only
});

it('a caller cannot switch the marker requirement off through the request', function () {
    [$user, $org] = $this->customerWithOrganization();
    $database = serverBackupRetryDatabase($org);
    $pve = ['dumps' => 0, 'notes' => null, 'lists' => 0, 'calls' => []];
    $ours = 'pbs-cz1:backup/vzdump-qemu-2042-2026_09_25-03_05_30.vma.zst';
    serverBackupRetryFake($pve, 1, fn (string $notes) => [
        ['volid' => 'pbs-cz1:backup/vzdump-qemu-2042-2026_09_25-03_06_00.vma.zst', 'ctime' => time() + 120, 'size' => 9, 'protected' => 0, 'notes' => 'db-2042 nightly by hand'], // newer, unmarked
        ['volid' => $ours, 'ctime' => time(), 'size' => 4242, 'protected' => 0, 'notes' => $notes],
    ]);

    $operation = serverBackupRetryDrive(app(ServiceService::class)->requestAction($database, 'backup', $this->contextFor($user, $org), 'db-backup-nomarker', ['kind' => 'scheduled', 'retention_days' => 14, 'backup_marker' => '', 'backup_legacy' => true]));

    expect($operation->state)->toBe(Operation::SUCCEEDED, (string) data_get($operation->error, 'message', ''))
        ->and(Backup::query()->where('operation_id', $operation->id)->sole()->remote_id)->toBe($ours);
});
