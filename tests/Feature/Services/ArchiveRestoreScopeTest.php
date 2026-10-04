<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\OperationService;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Providers\Contracts\ActualState;
use Onhost\Providers\Contracts\BackupCapable;
use Onhost\Providers\Contracts\FileTransport;
use Onhost\Providers\Contracts\InfrastructureProvider;
use Onhost\Providers\Contracts\ProviderAdapter;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Contracts\WebToolsProvider;

require_once __DIR__.'/../../Support/ArchiveRestoreHelpers.php';

/*
 * TASK-0035 (permission program IF-11 / P0-05, audit SE-2 + SE-14): the archive of a cancelled service goes back only for
 * somebody who may restore for the whole organization or project AND may read the service the archive came from. The generic
 * action endpoint took `archive.restore` with any archive of the organization, checked only against the TARGET service — a
 * guest with `svc_restore` on one service pulled a stranger's cancelled site (wp-config.php, the databases) into it. The
 * restore also wrote over the live target without a copy, and the download fee was waived before the restore was accepted.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake('local');
});

/** The panel of the target service, controlled by the test; `copy_fails` makes the safety copy of the live site impossible. */
function arsPanel(Service $service, array &$log, bool $copyFails = false): void
{
    $transport = Mockery::mock(FileTransport::class)->shouldIgnoreMissing();
    $transport->shouldReceive('archive')->andReturnUsing(function (array $paths, string $target) use (&$log, $copyFails) {
        $log[] = 'archive';
        if ($copyFails) {
            throw new RuntimeException('the agent user cannot log in');
        }
    });
    $transport->shouldReceive('download')->andReturnUsing(fn (string $path, string $localFile) => file_put_contents($localFile, gzencode(str_repeat('live site ', 300))));
    $transport->shouldReceive('upload')->andReturnUsing(function () use (&$log) {
        $log[] = 'upload';
    });
    $transport->shouldReceive('extract')->andReturnUsing(function () use (&$log) {
        $log[] = 'extract';
    });
    $adapter = Mockery::mock(ProviderAdapter::class, InfrastructureProvider::class, WebHostingProvider::class, WebToolsProvider::class, BackupCapable::class)->shouldIgnoreMissing();
    $adapter->shouldReceive('siteFeatures')->andReturn(['backups' => true, 'restore' => true, 'backup_download' => true, 'backup_delete' => true, 'backup_on_demand' => false]);
    $adapter->shouldReceive('listDatabases')->andReturn([]);
    $adapter->shouldReceive('exportDatabase')->andReturnUsing(fn (ResourceRef $site) => ProviderResult::completed($site, ['exported' => true]));
    $adapter->shouldReceive('transport')->andReturn($transport);
    $adapter->shouldReceive('listBackups')->andReturn([]);
    $adapter->shouldReceive('getActualState')->andReturn(new ActualState(true, ['domain' => (string) $service->hostname, 'system_user' => 'web41'], 'active', now()->toISOString()));
    $registry = app(ProviderRegistry::class);
    $known = new ReflectionProperty($registry, 'instances');
    $known->setValue($registry, [(string) $service->provider_instance_id => $adapter] + (array) $known->getValue($registry));
}

function arsGeneric(object $test, Service $target, Backup $archive)
{
    return $test->withHeader('Idempotency-Key', (string) Str::ulid())->postJson("/v1/services/{$target->id}/actions", ['action' => 'archive.restore', 'params' => ['backup_id' => $archive->id]]);
}

it('never lets a restore capability on one service pull another service\'s archive into it', function () {
    [, $org] = $this->customerWithOrganization();
    $stranger = arsWebService($org, 'cizi.cz'); // a service the guest was never given
    $archive = arsArchive($stranger);
    arsCancelled($stranger);
    $mine = arsWebService($org, 'muj.cz');
    $guest = arsPerson($org, 'svc_restore', 'resource', $mine->id);
    $this->actingAs($guest, 'sanctum');

    arsGeneric($this, $mine, $archive)->assertNotFound(); // the archive does not exist for this person
    $this->withHeader('Idempotency-Key', (string) Str::ulid())->postJson("/v1/services/archives/{$archive->id}/restore", ['service_id' => $mine->id])->assertForbidden();
    expect(Operation::query()->where('service_id', $mine->id)->count())->toBe(0)
        ->and(data_get($archive->fresh()->meta, 'download.waived'))->toBeNull(); // nor did the attempt make the download free
});

it('restores for a project only an archive of a service of that project', function () {
    [, $org] = $this->customerWithOrganization();
    $alpha = Project::query()->create(['organization_id' => $org->id, 'slug' => 'alpha', 'name' => 'Alpha']);
    $beta = Project::query()->create(['organization_id' => $org->id, 'slug' => 'beta', 'name' => 'Beta']);
    $betaOld = arsWebService($org, 'beta-old.cz', $beta->id);
    $betaArchive = arsArchive($betaOld);
    arsCancelled($betaOld);
    $alphaOld = arsWebService($org, 'alpha-old.cz', $alpha->id);
    $alphaArchive = arsArchive($alphaOld);
    arsCancelled($alphaOld);
    $alphaNew = arsWebService($org, 'alpha-new.cz', $alpha->id);
    $operator = arsPerson($org, 'cloud_operator', 'project', $alpha->id); // restores in project alpha, nowhere else
    $log = [];
    arsPanel($alphaNew, $log);
    $this->actingAs($operator, 'sanctum');

    arsGeneric($this, $alphaNew, $betaArchive)->assertNotFound();
    arsGeneric($this, $alphaNew, $alphaArchive)->assertStatus(202);
});

it('waives the download fee only once the restore was accepted', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $old = arsWebService($org, 'stary.cz');
    $archive = arsArchive($old);
    arsCancelled($old);
    $new = arsWebService($org, 'novy.cz');
    Operation::query()->create(['kind' => 'service.action', 'workflow' => ServiceActionWorkflow::class, 'state' => Operation::RUNNING, 'service_id' => $new->id, 'organization_id' => $org->id,
        'idempotency_key' => 'ars-busy-'.$new->id, 'desired' => ['action' => 'backup'], 'queued_at' => now()]);
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $this->actingAs($owner, 'sanctum');

    $this->withHeader('Idempotency-Key', (string) Str::ulid())->postJson("/v1/services/archives/{$archive->id}/restore", ['service_id' => $new->id])->assertStatus(409)->assertJsonPath('error', 'operation_in_progress');
    expect(data_get($archive->fresh()->meta, 'download.waived'))->toBeNull(); // refused, so the archive still costs what it cost
});

it('copies the live target before the archive goes over it, and writes nothing when the copy fails', function () {
    $labels = array_map(fn ($step) => $step->label(), app(ServiceActionWorkflow::class)->steps(new Operation(['desired' => ['action' => 'archive.restore'], 'service_id' => null])));
    expect($labels[0])->toBe('Záloha před přepsáním');

    [$owner, $org] = $this->customerWithOrganization();
    $old = arsWebService($org, 'stary.cz');
    $archive = arsArchive($old);
    arsCancelled($old);
    $new = arsWebService($org, 'novy.cz');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $this->actingAs($owner, 'sanctum');

    // the copy cannot be made: the restore fails and the live site is not touched
    $log = [];
    arsPanel($new, $log, copyFails: true);
    $id = arsGeneric($this, $new, $archive)->assertStatus(202)->json('operation_id');
    expect(driveOperation(Operation::query()->findOrFail($id))->state)->toBe(Operation::FAILED)
        ->and($log)->not->toContain('upload')->not->toContain('extract');

    // the copy is made: first the live site is kept, then the archive goes over it
    $log = [];
    arsPanel($new, $log);
    $id = arsGeneric($this, $new, $archive)->assertStatus(202)->json('operation_id');
    expect(driveOperation(Operation::query()->findOrFail($id))->state)->toBe(Operation::SUCCEEDED)
        ->and($log)->toBe(['archive', 'upload', 'extract']);
    expect(Backup::query()->where('service_id', $new->id)->where('kind', 'pre_restore')->where('state', 'completed')->count())->toBe(1);
});

it('keeps a failed copy failed on the retry, so a restore in place writes nothing either', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $site = arsWebService($org, 'zivy.cz');
    $set = arsArchive($site); // a finished set of this very service, restored onto itself
    $set->forceFill(['kind' => 'manual', 'protected' => false, 'immutable_until' => null])->save();
    $log = [];
    arsPanel($site, $log, copyFails: true);
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $this->actingAs($owner, 'sanctum');

    $id = $this->withHeader('Idempotency-Key', (string) Str::ulid())->postJson("/v1/services/{$site->id}/actions", ['action' => 'restore', 'params' => ['backup_id' => $set->id]])->assertStatus(202)->json('operation_id');
    expect(driveOperation(Operation::query()->findOrFail($id))->state)->toBe(Operation::FAILED)
        ->and($log)->not->toContain('upload')->not->toContain('extract')
        ->and(Backup::query()->where('service_id', $site->id)->where('kind', 'pre_restore')->where('state', 'completed')->count())->toBe(0);
});

// TASK-0035 review round 1 (qa, HIGH): the two checks of assertMayRestore apart. This person MAY read the archive's source (an
// organization-wide viewer) — so no 404 from the source check hides the answer — and holds `backup.restore` only through a
// share of the target (`svc_restore`, the exploit of ruling #10). The bus lets the command through (it asks on the target
// service, where the share is); the refusal can only come from the target check, and it names the scope it wanted.
it('refuses a restore held only through a share of the target, even to a person who may read the source', function () {
    [, $org] = $this->customerWithOrganization();
    $alpha = Project::query()->create(['organization_id' => $org->id, 'slug' => 'alpha', 'name' => 'Alpha']);
    $old = arsWebService($org, 'stary.cz');
    $archive = arsArchive($old);
    arsCancelled($old);
    $mine = arsWebService($org, 'muj.cz');
    $inProject = arsWebService($org, 'projekt.cz', $alpha->id);
    $reader = arsPerson($org, 'viewer', 'organization', null); // backup.read on every service of the organization, no restore anywhere
    foreach ([$mine, $inProject] as $target) {
        PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $reader->id, 'role_key' => 'svc_restore', 'scope_type' => 'resource', 'scope_id' => $target->id, 'organization_id' => $org->id]);
    }
    $this->actingAs($reader, 'sanctum');

    arsGeneric($this, $mine, $archive)->assertForbidden()->assertJsonPath('error', 'forbidden')->assertJsonPath('permission', 'backup.restore')->assertJsonPath('scope', 'organization');
    arsGeneric($this, $inProject, $archive)->assertForbidden()->assertJsonPath('error', 'forbidden')->assertJsonPath('permission', 'backup.restore')->assertJsonPath('scope', 'project');
    expect(Operation::query()->whereIn('service_id', [$mine->id, $inProject->id])->count())->toBe(0)
        ->and(data_get($archive->fresh()->meta, 'download.waived'))->toBeNull();
});

/** A delivered VPS on the Proxmox lab cluster (the shape SafetyCopyTest uses), for the hypervisor side of the safety copy. */
function arsVps(Organization $org): Service
{
    $instance = pveLab();
    $node = Node::query()->where('name', 'prg1-n2')->firstOrFail();
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'hostname' => 'vm-ars.cust.onhost.cz', 'state' => ServiceStateMachine::ACTIVE,
        'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id, 'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'],
        'entitlements' => ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160, 'snapshots' => 5], 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => [],
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => 'prg1-n2', 'meta' => ['name' => 'vm-ars'], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "ars-vps:{$service->id}", 'adapter_version' => '1.0.0']);

    return $service;
}

// TASK-0035 review round 1 (security, MEDIUM): "fails closed" for the hypervisor too. The safety snapshot's task was never
// confirmed — its state stayed unknown, then the hypervisor reported it failed — and the operator's retry used to find the row
// still "running", call it completed and roll the server back with no copy. Now the retry asks the hypervisor about that very
// task, fails the row and takes a NEW snapshot; the rollback runs only after a snapshot the hypervisor confirmed.
it('rolls a server back only over a snapshot the hypervisor confirmed, also on the operator retry', function () {
    $calls = [];
    $firstPolls = 0;
    Http::fake(function (Request $request) use (&$calls, &$firstPolls) {
        $url = $request->url();
        if (! str_starts_with($url, 'https://pve.mgmt.test:8006')) {
            return null;
        }
        if (str_contains($url, '/snapshot/vcerejsi/rollback')) {
            $calls[] = 'rollback';

            return Http::response(['data' => 'UPID:prg1-n2:0000C0C0:00000001:66F0AA19:qmrollback:1042:onhost@pve!cp:']);
        }
        if (str_ends_with($url, '/qemu/1042/snapshot') && $request->method() === 'POST') {
            $calls[] = 'snapshot';

            return Http::response(['data' => count($calls) === 1
                ? 'UPID:prg1-n2:0000AAAA:00000001:66F0AA19:qmsnapshot:1042:onhost@pve!cp:'   // the first safety snapshot: never confirmed
                : 'UPID:prg1-n2:0000BBBB:00000002:66F0AA19:qmsnapshot:1042:onhost@pve!cp:']); // the one the retry takes
        }
        if (str_contains($url, '/tasks/') && str_contains($url, '0000AAAA')) {
            return $firstPolls++ === 0
                ? Http::response(['errors' => ['upid' => 'no such task']], 404) // AsyncStatus::UNKNOWN — the runner polls again
                : Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'snapshot feature is not available']]);
        }
        if (str_contains($url, '/tasks/')) {
            return Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]);
        }

        return Http::response(['data' => []]);
    });
    [$user, $org] = $this->customerWithOrganization();
    $service = arsVps($org);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'rollback_snapshot', $this->contextFor($user, $org, 'webauthn'), 'ars-roll-1', ['name' => 'vcerejsi']));
    expect($operation->state)->toBe(Operation::FAILED)->and($calls)->toBe(['snapshot']) // nothing rolled back
        ->and($firstPolls)->toBeGreaterThanOrEqual(2);                                   // UNKNOWN first, then the task's failure

    app(OperationService::class)->retry($operation, CommandContext::system('test'), 'the storage was fixed');
    $operation = driveOperation($operation);

    expect($calls)->toBe(['snapshot', 'snapshot', 'rollback']) // a new copy, confirmed, THEN the rollback — against the old code: ['snapshot', 'rollback']
        ->and($operation->state)->toBe(Operation::SUCCEEDED);
    $copies = Backup::query()->where('service_id', $service->id)->where('kind', 'pre_rollback')->orderBy('started_at')->get();
    expect($copies)->toHaveCount(2)
        ->and($copies->where('state', 'completed')->count())->toBe(1)
        ->and($copies->where('state', 'failed')->count())->toBe(1)
        ->and((string) data_get($copies->firstWhere('state', 'completed')?->meta, 'task.handle'))->toContain('0000BBBB');
});
