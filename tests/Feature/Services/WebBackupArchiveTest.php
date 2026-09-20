<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\RestoreJob;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceBackups;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\Web\BackupScheduler;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\ActualState;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\BackupCapable;
use Onhost\Providers\Contracts\FileTransport;
use Onhost\Providers\Contracts\InfrastructureProvider;
use Onhost\Providers\Contracts\Naming;
use Onhost\Providers\Contracts\ProviderAdapter;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Contracts\WebToolsProvider;

/*
 * A backup of a web service is the platform's own archive set — the site files and every database, off the node — and it
 * is fresh or it fails (docs/runbooks/backups.md). What the panels' own backups did instead: ISPConfig made none on request
 * and the step adopted last night's archive as "done"; aaPanel packed the files and left the databases out.
 */

/** A web service on a panel the test controls: the operation runner is handed this adapter instead of a real one. */
function backupPanel(Service $service, array &$log, array $options = [], array &$state = []): object
{
    $transport = Mockery::mock(FileTransport::class)->shouldIgnoreMissing();
    $transport->shouldReceive('archive')->andReturnUsing(function (array $paths, string $target) use (&$log, $options) {
        $log[] = ['archive', $paths, $target];
        if ($options['files_fail'] ?? false) {
            throw new RuntimeException('the agent user cannot log in');
        }
    });
    $transport->shouldReceive('download')->andReturnUsing(fn (string $path, string $localFile) => file_put_contents($localFile, gzencode(str_repeat('site files ', 300))));
    $transport->shouldReceive('upload')->andReturnUsing(function (string $path, string $localFile) use (&$log) {
        $log[] = ['upload', $path, filesize($localFile)];
    });
    $transport->shouldReceive('extract')->andReturnUsing(function (string $archive, string $target) use (&$log) {
        $log[] = ['extract', $archive, $target];
    });

    $adapter = Mockery::mock(ProviderAdapter::class, InfrastructureProvider::class, WebHostingProvider::class, WebToolsProvider::class, BackupCapable::class)->shouldIgnoreMissing();
    $adapter->shouldReceive('siteFeatures')->andReturn(['backups' => true, 'restore' => true, 'backup_download' => true, 'backup_delete' => true, 'backup_on_demand' => $options['on_demand'] ?? false]);
    $adapter->shouldReceive('listDatabases')->andReturnUsing(function () use (&$state) {
        return $state['databases'] ?? [['remote_id' => '7', 'name' => 'shop_db'], ['remote_id' => '8', 'name' => 'blog_db']];
    });
    $adapter->shouldReceive('exportDatabase')->andReturnUsing(function (ResourceRef $site, string $remoteId, string $localFile) use (&$log) {
        $log[] = ['export', $remoteId];
        file_put_contents($localFile, "-- dump of {$remoteId}\nCREATE TABLE t (id int);\n");

        return ProviderResult::completed($site, ['exported' => true]);
    });
    $adapter->shouldReceive('importDatabase')->andReturnUsing(function (ResourceRef $site, string $remoteId, string $localFile) use (&$log) {
        $log[] = ['import', $remoteId, (string) file_get_contents($localFile)];

        return ProviderResult::completed($site, ['imported' => true]);
    });
    $adapter->shouldReceive('transport')->andReturn($transport);
    $adapter->shouldReceive('getActualState')->andReturn(new ActualState(true, ['domain' => 'firma.cz', 'system_user' => 'web41'], 'active', now()->toISOString()));
    // the panel's own archives: one from last night, and nothing new however often it is asked
    $adapter->shouldReceive('backup')->andReturnUsing(function () use (&$log) {
        $log[] = ['panel-backup'];

        return ProviderResult::completed(null, ['requested' => true]);
    });
    $adapter->shouldReceive('listBackups')->andReturn([['remote_id' => '900', 'created_at' => now()->subHours(9)->toIso8601String(), 'size_bytes' => 4096, 'verified' => null, 'protected' => null, 'meta' => ['filename' => 'web41_last-night.tar.gz']]]);
    $adapter->shouldReceive('downloadBackup')->andReturnUsing(fn (ResourceRef $site, string $id, string $localFile) => file_put_contents($localFile, str_repeat('last night', 100)));

    $registry = app(ProviderRegistry::class);
    $known = new ReflectionProperty($registry, 'instances');
    $known->setValue($registry, [(string) $service->provider_instance_id => $adapter] + (array) $known->getValue($registry));

    return $adapter;
}

function backupWebService(string $organizationId, array $entitlements = []): Service
{
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'ispconfig-backup'], ['provider' => 'ispconfig', 'name' => 'ISPConfig backup', 'base_url' => 'https://node.test:8080', 'secret_ref' => 'env://ISPCONFIG_BACKUP', 'state' => 'active', 'options' => []]);
    $service = Service::query()->create(['organization_id' => $organizationId, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Webhosting', 'state' => ServiceStateMachine::ACTIVE,
        'region_code' => 'cz1', 'entitlements' => ['nvme_gb' => 50] + $entitlements, 'desired_spec' => ['domain' => 'firma.cz', 'executor' => 'ispconfig'], 'sla_class' => 'standard', 'provider_instance_id' => $instance->id]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'web_domain', 'remote_id' => '41', 'remote_node' => '1',
        'meta' => ['domain' => 'firma.cz', 'system_user' => 'web41'], 'idempotency_key' => 'web-backup-'.$service->id]);

    return $service->refresh();
}

it('never lets a backup, a restore, a snapshot or a power action of a mail service reach the adapter (H11: a mail domain\'s id among web sites is a stranger\'s site)', function () {
    Queue::fake();
    [$owner, $org] = $this->customerWithOrganization();
    $mail = featureMailService($org);
    $calls = [];
    Http::fake(function ($request) use (&$calls) {
        if (! str_starts_with($request->url(), ISP)) {
            return null;
        }
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $calls[] = [$function, $request->data()];

        return Http::response(['code' => 'ok', 'message' => '', 'response' => match ($function) {
            'login' => 'sess-b', 'monitor_jobqueue_count' => 0,
            'sites_web_domain_get' => ['domain_id' => 5, 'domain' => 'cizi-web.cz', 'server_id' => 1, 'sys_groupid' => 9, 'backup_interval' => 'weekly', 'backup_copies' => 3], // site #5 is somebody else's
            'sites_web_domain_backup_list' => [['backup_id' => 77, 'tstamp' => time() - 3600, 'filesize' => 1024, 'backup_type' => 'web', 'filename' => 'web5.tar.gz']],
            default => true,
        }]);
    });

    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1'); // a restore asks for a fresh step-up before anything else
    $this->actingAs($owner, 'sanctum')->withHeader('X-Organization', $org->id);
    foreach (['backup', 'restore', 'snapshot', 'rollback_snapshot', 'power'] as $i => $action) {
        $this->withHeader('Idempotency-Key', "mail-core-{$i}")->postJson("/v1/services/{$mail->id}/actions", ['action' => $action, 'params' => ['backup_id' => 'bkp_x', 'power_action' => 'reboot', 'name' => 'x']])
            ->assertStatus(422)->assertJsonPath('error', 'feature_unavailable');
    }
    expect(Operation::query()->where('service_id', $mail->id)->count())->toBe(0);

    // and the adapter itself refuses a mail domain where it means a web site — it rewrote the stranger's backup plan and listed their archives
    $features = app(ServiceFeatures::class);
    $adapter = $features->adapterFor($mail);
    $ref = $features->refFor($mail);
    foreach ([fn () => $adapter->backup($ref, []), fn () => $adapter->listBackups($ref), fn () => $adapter->restore($ref, '77')] as $call) {
        expect($call)->toThrow(fn (ProviderException $e) => expect($e->errorCode)->toBe(ProviderErrorCode::VALIDATION));
    }
    expect(collect($calls)->pluck(0)->intersect(['sites_web_domain_update', 'sites_web_domain_backup_list', 'sites_web_domain_backup'])->all())->toBe([]);
});

it('asks ISPConfig to restore THE BACKUP (its id, `backup_restore`) and only one that stands in the site\'s own list', function () {
    [, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'ispconfig');
    $calls = [];
    Http::fake(function ($request) use (&$calls) {
        if (! str_starts_with($request->url(), ISP)) {
            return null;
        }
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $calls[] = [$function, $request->data()];

        return Http::response(['code' => 'ok', 'message' => '', 'response' => match ($function) {
            'login' => 'sess-r', 'monitor_jobqueue_count' => 0,
            'sites_web_domain_backup_list' => [['backup_id' => 41, 'tstamp' => time() - 3600, 'filesize' => 2048, 'backup_type' => 'web', 'filename' => 'web7_2026-09-19.tar.gz']],
            default => true,
        }]);
    });
    $features = app(ServiceFeatures::class);
    $adapter = $features->adapterFor($site);
    $ref = $features->refFor($site);

    $adapter->restore($ref, '41');
    $sent = collect($calls)->last(fn ($c) => $c[0] === 'sites_web_domain_backup')[1];
    // the remote function is (session, primary_id, action_type): the primary id is the backup's, not the site's
    expect($sent)->toMatchArray(['primary_id' => 41, 'action_type' => 'backup_restore'])->not->toHaveKey('backup_id');

    // a number that is not in this site's list is somebody else's backup: the panel would not have asked whose it is
    $before = count($calls);
    expect(fn () => $adapter->restore($ref, '4242'))->toThrow(fn (ProviderException $e) => expect($e->errorCode)->toBe(ProviderErrorCode::NOT_FOUND));
    expect(collect(array_slice($calls, $before))->pluck(0)->all())->not->toContain('sites_web_domain_backup');

    // no backup on request: the adapter says so instead of rewriting the nightly plan and reporting success
    expect(fn () => $adapter->backup($ref, []))->toThrow(ProviderException::class);
    expect(collect($calls)->pluck(0)->all())->not->toContain('sites_web_domain_update');
    expect($adapter->siteFeatures()['backup_on_demand'])->toBeFalse();
});

it('backs a web service up as files and every database, off the node — and never adopts last night\'s panel archive', function () {
    Storage::fake('local');
    [$owner, $org] = $this->customerWithOrganization();
    $service = backupWebService($org->id);
    $log = [];
    backupPanel($service, $log);

    $this->actingAs($owner, 'sanctum');
    $id = $this->withHeader('Idempotency-Key', 'wb-1')->postJson("/v1/services/{$service->id}/actions", ['action' => 'backup', 'params' => ['kind' => 'final', 'protected' => true, 'retention_days' => 9999]])->assertStatus(202)->json('operation_id');
    expect(driveOperation(Operation::query()->findOrFail($id))->state)->toBe(Operation::SUCCEEDED);

    $backup = Backup::query()->where('operation_id', $id)->firstOrFail();
    // what the customer chose about the row is nothing: a manual backup, thirty days, not protected, not immutable
    expect($backup->kind)->toBe('manual')->and($backup->state)->toBe('completed')->and($backup->protected)->toBeFalse()->and($backup->immutable_until)->toBeNull()
        ->and((int) round(now()->diffInDays($backup->retention_until)))->toBe(30)
        ->and($backup->remote_id)->toBeNull() // the panel's archive from last night (#900) was what the old step reported as this backup
        ->and($backup->verify_status)->toBe('ok')->and($backup->size_bytes)->toBeGreaterThan(0);
    $set = (string) data_get($backup->meta, 'set');
    $names = array_map('basename', Storage::disk('local')->files($set));
    expect($names)->toContain('site-files.tar.gz', 'database-shop-db.sql', 'database-blog-db.sql', 'service.json', 'manifest.json');
    expect(collect($log)->where(0, 'export')->pluck(1)->all())->toBe(['7', '8'])->and(collect($log)->firstWhere(0, 'archive')[1])->toBe(['.'])->and(collect($log)->pluck(0)->all())->not->toContain('panel-backup');
    // the final archive's event says "the deletion may go on" — an ordinary backup does not publish it
    expect(DB::table('outbox_messages')->where('name', 'like', '%final_archive.created')->count())->toBe(0);

    // the list the panel shows is the platform's rows, and the set comes down as one zip with checksums and a readme
    $this->getJson("/v1/services/{$service->id}/backups")->assertOk()->assertJsonPath('data.0.id', $backup->id)->assertJsonPath('data.0.kind', 'manual');
    $download = $this->get("/v1/services/{$service->id}/backups/{$backup->id}/download")->assertOk();
    expect((string) $download->headers->get('content-disposition'))->toContain('.zip');
});

it('fails the backup when no fresh archive can be had — a panel that makes none on demand is not even asked, and nothing older is taken', function () {
    Storage::fake('local');
    config(['onhost.platform_backup.game_archive_timeout' => 0]); // do not sit out the panel's half hour in the test
    [, $org] = $this->customerWithOrganization();
    $service = backupWebService($org->id);
    $services = app(ServiceService::class);

    // ISPConfig: the agent user cannot log in, and the panel cannot be asked for an archive now
    $log = [];
    backupPanel($service, $log, ['files_fail' => true, 'on_demand' => false]);
    $failed = driveOperation($services->requestAction($service, 'backup', CommandContext::system('test')->withScope($org->id), 'wb-fail-1', ['kind' => 'scheduled']));
    expect($failed->state)->toBe(Operation::FAILED)->and(collect($log)->pluck(0)->all())->not->toContain('panel-backup');
    $row = Backup::query()->where('operation_id', $failed->id)->get();
    expect($row)->toHaveCount(1) // one row per operation, however often the step was tried
        ->and($row[0]->state)->toBe('failed')->and($row[0]->remote_id)->toBeNull()->and((string) data_get($row[0]->meta, 'attempts.panel_backup'))->toContain('no site backup on demand');

    // aaPanel: asked, answers "done", and its list holds nothing that was not there before — last night's #900 stays last night's
    $log = [];
    backupPanel($service, $log, ['files_fail' => true, 'on_demand' => true]);
    $again = driveOperation($services->requestAction($service->fresh(), 'backup', CommandContext::system('test')->withScope($org->id), 'wb-fail-2', ['kind' => 'scheduled']));
    expect($again->state)->toBe(Operation::FAILED)->and(collect($log)->pluck(0)->all())->toContain('panel-backup');
    expect(Backup::query()->where('service_id', $service->id)->where('state', 'completed')->count())->toBe(0)
        ->and(Backup::query()->where('service_id', $service->id)->whereNotNull('remote_id')->count())->toBe(0);
});

it('restores a set in place — databases first, then the files over the root — and touches nothing when a dump has no database to go into', function () {
    Storage::fake('local');
    [$owner, $org] = $this->customerWithOrganization();
    $service = backupWebService($org->id);
    $log = [];
    $state = ['databases' => [['remote_id' => '7', 'name' => 'shop_db'], ['remote_id' => '8', 'name' => 'blog_db']]];
    backupPanel($service, $log, [], $state);
    $backup = app(ServiceBackups::class)->take($service, app(ServiceFeatures::class)->adapterFor($service), app(ServiceFeatures::class)->refFor($service), CommandContext::system('test'), 'op-seed', 'manual', 30);

    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $this->actingAs($owner, 'sanctum');
    $log = [];
    $id = $this->withHeader('Idempotency-Key', 'wr-1')->postJson("/v1/services/{$service->id}/actions", ['action' => 'restore', 'params' => ['backup_id' => $backup->id]])->assertStatus(202)->json('operation_id');
    expect(driveOperation(Operation::query()->findOrFail($id))->state)->toBe(Operation::SUCCEEDED);
    expect(collect($log)->pluck(0)->all())->toBe(['import', 'import', 'upload', 'extract']);
    expect(collect($log)->where(0, 'import')->pluck(1)->sort()->values()->all())->toBe(['7', '8'])->and(collect($log)->firstWhere(0, 'import')[2])->toContain('CREATE TABLE')
        ->and(collect($log)->firstWhere(0, 'extract')[2])->toBe('.');
    $job = RestoreJob::query()->where('operation_id', $id)->firstOrFail();
    expect($job->state)->toBe('completed')->and($job->result['mode'])->toBe('overlay')->and($job->result['databases'])->toHaveCount(2);

    // the customer dropped a database since: the restore says which one and changes nothing — not the files, not the other database
    $state['databases'] = [['remote_id' => '7', 'name' => 'shop_db']];
    $log = [];
    $second = $this->withHeader('Idempotency-Key', 'wr-2')->postJson("/v1/services/{$service->id}/actions", ['action' => 'restore', 'params' => ['backup_id' => $backup->id]])->assertStatus(202)->json('operation_id');
    $failed = driveOperation(Operation::query()->findOrFail($second));
    expect($failed->state)->toBe(Operation::FAILED)->and((string) data_get($failed->error, 'message'))->toContain('blog-db')->and($log)->toBe([]);

    // the archive of a cancelled service does not go back this way (it is restored onto a new service, with its own rules)
    $final = Backup::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'kind' => 'final', 'state' => 'completed', 'protected' => true, 'meta' => ['set' => FinalArchive::PREFIX.'/x/y']]);
    $this->withHeader('Idempotency-Key', 'wr-3')->postJson("/v1/services/{$service->id}/actions", ['action' => 'restore', 'params' => ['backup_id' => $final->id]])->assertStatus(409)->assertJsonPath('error', 'backup_not_restorable');
    $this->get("/v1/services/{$service->id}/backups/{$final->id}/download")->assertNotFound();
});

it('keeps only so many manual backups, lets their owner delete one, and never a protected or a final one', function () {
    Storage::fake('local');
    config(['onhost.backups.manual_max' => 2]);
    [$owner, $org] = $this->customerWithOrganization();
    $service = backupWebService($org->id);
    $log = [];
    backupPanel($service, $log);
    $features = app(ServiceFeatures::class);
    $take = fn (string $op, string $kind = 'manual', bool $protected = false) => app(ServiceBackups::class)->take($service, $features->adapterFor($service), $features->refFor($service), CommandContext::system('test'), $op, $kind, 30, $protected);
    $first = $take('op-1');
    $kept = $take('op-2', 'manual', true);

    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $this->actingAs($owner, 'sanctum');
    $this->withHeader('Idempotency-Key', 'wl-1')->postJson("/v1/services/{$service->id}/actions", ['action' => 'backup'])->assertStatus(409)->assertJsonPath('error', 'backup_limit_reached')->assertJsonPath('limit', 2);
    // the nightly backup is the plan's, not the customer's pile: the ceiling does not stop it
    $nightly = app(ServiceService::class)->requestAction($service, 'backup', CommandContext::system('backup scheduler')->withScope($org->id), 'wl-night', ['kind' => 'scheduled', 'retention_days' => 7]);
    expect(driveOperation($nightly)->state)->toBe(Operation::SUCCEEDED);

    // a protected one stays, whoever asks
    $this->withHeader('Idempotency-Key', 'wl-2')->postJson("/v1/services/{$service->id}/actions", ['action' => 'backup.delete', 'params' => ['remote_id' => $kept->id]])->assertStatus(409)->assertJsonPath('error', 'backup_protected');
    $set = (string) data_get($first->meta, 'set');
    expect(Storage::disk('local')->exists($set.'/manifest.json'))->toBeTrue();
    $delete = $this->withHeader('Idempotency-Key', 'wl-3')->postJson("/v1/services/{$service->id}/actions", ['action' => 'backup.delete', 'params' => ['remote_id' => $first->id]])->assertStatus(202)->json('operation_id');
    expect(driveOperation(Operation::query()->findOrFail($delete))->state)->toBe(Operation::SUCCEEDED);
    expect($first->fresh()->state)->toBe('deleted')->and(Storage::disk('local')->exists($set.'/manifest.json'))->toBeFalse();
    // and there is room again
    $this->withHeader('Idempotency-Key', 'wl-4')->postJson("/v1/services/{$service->id}/actions", ['action' => 'backup'])->assertStatus(202);
});

it('prunes sets by retention and generations and copies the newest one off-site, part by part', function () {
    Storage::fake('local');
    Storage::fake('offsite');
    config(['onhost.backups.offsite_disk' => 'offsite', 'filesystems.disks.offsite' => ['driver' => 'local', 'root' => storage_path('framework/testing/disks/offsite')]]);
    [, $org] = $this->customerWithOrganization();
    $service = backupWebService($org->id, ['backup_generations' => 1, 'backup_offsite' => true]);
    $log = [];
    backupPanel($service, $log);
    $features = app(ServiceFeatures::class);
    $take = fn (string $op, string $kind) => app(ServiceBackups::class)->take($service, $features->adapterFor($service), $features->refFor($service), CommandContext::system('test'), $op, $kind, 7);
    $this->travelTo(now()->startOfDay()->addHours(1)); // before tonight's window: nothing new is due, only the housekeeping runs
    $older = $take('night-1', 'scheduled');
    $older->forceFill(['started_at' => now()->subDay()])->save();
    $newer = $take('night-2', 'scheduled');

    $stats = app(BackupScheduler::class)->tick();
    expect($stats['errors'])->toBe(0)->and($stats['offsite'])->toBe(1);
    // one generation: the older night goes — the row AND the files (marking the row alone left the archive on the disk for good)
    expect($older->fresh()->state)->toBe('deleted')->and(Storage::disk('local')->exists(data_get($older->meta, 'set').'/manifest.json'))->toBeFalse()
        ->and($newer->fresh()->state)->toBe('completed')->and($newer->fresh()->offsite)->toBeTrue();
    $path = (string) data_get($newer->fresh()->meta, 'offsite_path');
    expect(Storage::disk('offsite')->exists($path.'/manifest.json'))->toBeTrue()->and(Storage::disk('offsite')->exists($path.'/site-files.tar.gz'))->toBeTrue()->and(Storage::disk('offsite')->exists($path.'/database-shop-db.sql'))->toBeTrue();

    // a service without a schedule has nobody to prune it: retention is asked of every set, whatever its kind — never of a protected one
    $lonely = $take('hand-2', 'manual');
    $lonely->forceFill(['retention_until' => now()->subMinute()])->save();
    $protected = $take('hand-3', 'manual');
    $protected->forceFill(['retention_until' => now()->subMinute(), 'protected' => true])->save();
    expect(app(FinalArchive::class)->prune())->toBe(1);
    expect($lonely->fresh()->state)->toBe('deleted')->and(Storage::disk('local')->exists(data_get($lonely->meta, 'set').'/manifest.json'))->toBeFalse()
        ->and($protected->fresh()->state)->toBe('completed');
});

it('waits for the game panel to finish a restore: a server being restored is offline, and offline is not "done"', function () {
    [, $org] = $this->customerWithOrganization();
    $game = featureGameService($org);
    $status = 'restoring_backup';
    Http::fake(function ($request) use (&$status) {
        if (! str_starts_with($request->url(), PTERO)) {
            return null;
        }
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        return match (true) {
            str_ends_with($path, '/backups/bk-1/restore') => Http::response('', 204),
            $path === '/api/application/servers/77' => Http::response(['object' => 'server', 'attributes' => ['id' => 77, 'identifier' => 'e4c1abcd', 'uuid' => 'e4c1abcd-0000', 'status' => $status, 'suspended' => false]]),
            str_ends_with($path, '/resources') => Http::response(['object' => 'stats', 'attributes' => ['current_state' => 'offline', 'resources' => []]]),
            default => Http::response(['object' => 'backup', 'attributes' => ['uuid' => 'bk-1', 'is_successful' => true, 'completed_at' => now()->toIso8601String()]]),
        };
    });
    $features = app(ServiceFeatures::class);
    $adapter = $features->adapterFor($game);
    $handle = $adapter->restore($features->refFor($game), 'bk-1')->async;

    expect($adapter->awaitStatus($handle)->state)->toBe(AsyncStatus::RUNNING); // the power state reads `offline` the whole time — it used to pass for "finished"
    $status = null;
    expect($adapter->awaitStatus($handle)->state)->toBe(AsyncStatus::SUCCEEDED);
    $status = 'install_failed';
    expect($adapter->awaitStatus($handle)->state)->toBe(AsyncStatus::FAILED);
});

it('exports the database dump this call made — an older dump is neither handed over as today\'s data nor deleted', function () {
    [, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'aapanel');
    $name = Naming::prefix($site->id).'_shop';
    $calls = [];
    Http::fake(function ($request) use (&$calls, $name) {
        if (! str_starts_with($request->url(), AAP)) {
            return null;
        }
        $q = (string) parse_url($request->url(), PHP_URL_QUERY);
        $calls[] = $q;

        return Http::response(match (true) {
            str_contains($q, 'table=databases') => ['data' => [['id' => 12, 'name' => $name, 'username' => $name, 'pid' => 41, 'ps' => 'shop']], 'page' => ''],
            str_contains($q, 'table=backup') => ['data' => [['id' => 300, 'filename' => '/www/backup/database/'.$name.'_last-week.sql.gz', 'addtime' => '2026-09-13 02:30:00', 'size' => 2048]]], // the same one before and after: the panel made none
            default => ['status' => true, 'msg' => 'ok'],
        });
    });
    $features = app(ServiceFeatures::class);
    $local = (string) tempnam(sys_get_temp_dir(), 'ohdump');
    try {
        expect(fn () => $features->toolsFor($site)[0]->exportDatabase($features->refFor($site), '12', $local))->toThrow(ProviderException::class, 'did not produce a database dump');
    } finally {
        @unlink($local);
    }
    expect(collect($calls)->filter(fn ($q) => str_contains($q, 'DelBackup'))->all())->toBe([]);
});
