<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Providers\Contracts\ActualState;
use Onhost\Providers\Contracts\BackupCapable;
use Onhost\Providers\Contracts\FileTransport;
use Onhost\Providers\Contracts\InfrastructureProvider;
use Onhost\Providers\Contracts\ProviderAdapter;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Contracts\WebToolsProvider;

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

/** A finished final archive on the backup disk of a cancelled web service. */
function arsArchive(Service $source): Backup
{
    $set = FinalArchive::PREFIX.'/'.$source->organization_id.'/'.$source->id.'-20260901-120000';
    Storage::disk('local')->put($set.'/service.json', json_encode(['service' => ['id' => $source->id]]));
    Storage::disk('local')->put($set.'/site-files.tar.gz', str_repeat('files', 200));
    Storage::disk('local')->put($set.'/manifest.json', json_encode(['service_id' => $source->id]));

    return Backup::query()->create([
        'service_id' => $source->id, 'organization_id' => $source->organization_id, 'kind' => 'final', 'state' => 'completed', 'protected' => true,
        'started_at' => now()->subDay(), 'finished_at' => now()->subDay(), 'verified_at' => now()->subDay(), 'verify_status' => 'ok', 'size_bytes' => 1024,
        'retention_until' => now()->addDays(60), 'immutable_until' => now()->addDays(60), 'meta' => ['set' => $set, 'family' => 'web', 'parts' => ['service.json', 'site-files.tar.gz'], 'gaps' => []],
    ]);
}

function arsWebService(Organization $org, string $domain, ?string $projectId = null): Service
{
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'ispconfig-ars'], ['provider' => 'ispconfig', 'name' => 'ISPConfig ars', 'base_url' => 'https://ars.test:8080', 'secret_ref' => 'env://ISPCONFIG_ARS', 'state' => 'active', 'options' => []]);
    $service = Service::query()->create(['organization_id' => $org->id, 'project_id' => $projectId, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Web '.$domain, 'hostname' => $domain, 'state' => ServiceStateMachine::ACTIVE,
        'region_code' => 'cz1', 'entitlements' => ['nvme_gb' => 50], 'desired_spec' => ['domain' => $domain, 'executor' => 'ispconfig'], 'sla_class' => 'standard', 'provider_instance_id' => $instance->id]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'web_domain', 'remote_id' => (string) random_int(100, 999), 'remote_node' => '1',
        'meta' => ['domain' => $domain, 'system_user' => 'web41'], 'idempotency_key' => 'ars-'.$service->id]);

    return $service->refresh();
}

function arsCancelled(Service $service): void
{
    $service->forceFill(['state' => ServiceStateMachine::TERMINATED, 'terminated_at' => now()])->save();
    $service->delete();
}

/** A person with one binding, the shape an invitation (organization/project) or a share (resource) writes. */
function arsPerson(Organization $org, string $role, string $scopeType, ?string $scopeId): User
{
    $user = User::query()->create(['email' => Str::lower(Str::random(8)).'@ars.test', 'name' => $role, 'password' => 'Correct-Horse-Battery-9', 'state' => 'active']);
    $member = $scopeType === 'organization' ? $role : 'guest';
    OrganizationMembership::query()->create(['organization_id' => $org->id, 'user_id' => $user->id, 'state' => 'active', 'role_key' => $member, 'joined_at' => now()]);
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => $member, 'scope_type' => 'organization', 'scope_id' => $org->id, 'organization_id' => $org->id]);
    if ($scopeType !== 'organization') {
        PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => $role, 'scope_type' => $scopeType, 'scope_id' => $scopeId, 'organization_id' => $org->id]);
    }
    app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1'); // a restore asks for a fresh step-up; the question here is the scope

    return $user;
}

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
