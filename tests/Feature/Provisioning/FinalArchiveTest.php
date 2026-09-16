<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Providers\Contracts\ActualState;
use Onhost\Providers\Contracts\BackupCapable;
use Onhost\Providers\Contracts\FileTransport;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Contracts\WebToolsProvider;

/** A web panel that hands over one database dump and the site files (the archive uses only these four calls). */
function archiveWebAdapter(bool $filesFail = false, bool $panelBackup = false): object
{
    $transport = Mockery::mock(FileTransport::class)->shouldIgnoreMissing();
    $transport->shouldReceive('archive')->andReturnUsing(function () use ($filesFail) {
        if ($filesFail) {
            throw new RuntimeException('shell not available on this node');
        }
    });
    $transport->shouldReceive('download')->andReturnUsing(fn (string $path, string $localFile) => file_put_contents($localFile, gzencode(str_repeat('site files', 200))));

    $adapter = Mockery::mock(WebHostingProvider::class, WebToolsProvider::class, BackupCapable::class)->shouldIgnoreMissing();
    $adapter->shouldReceive('listDatabases')->andReturn([['remote_id' => '7', 'name' => 'shop_db']]);
    $adapter->shouldReceive('exportDatabase')->andReturnUsing(function (ResourceRef $site, string $remoteId, string $localFile) {
        file_put_contents($localFile, "-- dump of {$remoteId}\nCREATE TABLE orders (id int);\n");

        return ProviderResult::completed($site, ['exported' => true]);
    });
    $adapter->shouldReceive('transport')->andReturn($transport);
    $adapter->shouldReceive('getActualState')->andReturn(new ActualState(true, ['domain' => 'firma.cz', 'system_user' => 'web41'], 'active', now()->toISOString()));

    // the panel's own site backup: empty until `backup()` is called, then one archive the adapter can download
    $made = new stdClass;
    $made->done = false;
    $adapter->shouldReceive('backup')->andReturnUsing(function () use ($made, $panelBackup) {
        $made->done = $panelBackup;

        return ProviderResult::completed(null, ['requested' => true]);
    });
    $adapter->shouldReceive('listBackups')->andReturnUsing(fn () => $made->done
        ? [['remote_id' => '9', 'created_at' => now()->toIso8601String(), 'size_bytes' => 2048, 'verified' => null, 'protected' => null, 'meta' => ['filename' => '/www/backup/site/firma.cz_2026.zip']]]
        : []);
    $adapter->shouldReceive('downloadBackup')->andReturnUsing(fn (ResourceRef $site, string $id, string $localFile) => file_put_contents($localFile, str_repeat('panel backup', 100)));

    return $adapter;
}

/** A service with a binding whose provider instance really exists — the identity check insists on all of it. */
function archivableWebService(string $organizationId): Service
{
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'aapanel-archive'], ['provider' => 'aapanel', 'name' => 'aaPanel archive', 'base_url' => 'https://node.test:8888', 'secret_ref' => 'env://AAPANEL_ARCHIVE', 'state' => 'active', 'options' => []]);
    $service = Service::query()->create(['organization_id' => $organizationId, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Webhosting', 'state' => ServiceStateMachine::ACTIVE,
        'region_code' => 'cz1', 'entitlements' => ['nvme_gb' => 50], 'desired_spec' => ['domain' => 'firma.cz'], 'sla_class' => 'standard', 'provider_instance_id' => $instance->id]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'web_domain', 'remote_id' => '41', 'remote_node' => '1',
        'meta' => ['domain' => 'firma.cz', 'system_user' => 'web41'], 'idempotency_key' => 'final-archive-'.$service->id]);

    return $service->refresh();
}

it('archives files, databases and metadata before a termination, keeps the set for 60 days and prunes it afterwards (audit §5aa)', function () {
    Storage::fake('local');
    [, $org] = $this->customerWithOrganization();
    $service = archivableWebService($org->id);

    $archives = app(FinalArchive::class);
    $result = $archives->create($service, archiveWebAdapter(), new ResourceRef('web_domain', '41', '1', ['domain' => 'firma.cz', 'system_user' => 'web41']), CommandContext::system('test'));
    $set = $result['set'];
    expect(array_keys($result['parts']))->toContain('service.json')->toContain('database-shop-db.sql')->toContain('site-files.tar.gz');
    expect(Storage::disk('local')->exists($set.'/manifest.json'))->toBeTrue()->and(Storage::disk('local')->exists($set.'/site-files.tar.gz'))->toBeTrue();
    $metadata = json_decode((string) Storage::disk('local')->get($set.'/service.json'), true);
    expect($metadata['service']['id'])->toBe($service->id)->and($metadata['bindings'][0]['remote_id'])->toBe('41')->and($metadata['actual_state']['status'])->toBe('active');
    expect($metadata['identity']['ok'])->toBeTrue()->and($metadata['identity']['matched'])->toBeGreaterThanOrEqual(5); // §5ab: the archive records which identifiers matched

    $backup = $result['backup'];
    expect($backup->kind)->toBe('final')->and($backup->state)->toBe('completed')->and($backup->protected)->toBeTrue()->and($backup->verify_status)->toBe('ok')
        ->and((int) now()->diffInDays($backup->retention_until))->toBeGreaterThanOrEqual(59)
        ->and($archives->existing($service))->not->toBeNull();

    // the whole set can be handed over as one compressed file (the paid download and the free restore use it)
    $package = $archives->package($backup);
    expect(Storage::disk('local')->exists($package['path']))->toBeTrue()->and($package['bytes'])->toBeGreaterThan(0)->and($package['sha256'])->not->toBe('');

    // the retention holds: pruning before day 60 keeps the set, afterwards it goes
    expect($archives->prune())->toBe(0)->and(Storage::disk('local')->exists($set.'/manifest.json'))->toBeTrue();
    $backup->forceFill(['retention_until' => now()->subDay()])->save();
    expect($archives->prune())->toBe(1)->and(Storage::disk('local')->exists($set.'/manifest.json'))->toBeFalse()
        ->and(Backup::query()->whereKey($backup->id)->value('state'))->toBe('expired');
});

it('falls back to the panel’s own site backup when the node offers no file transport (aaPanel without a shell)', function () {
    Storage::fake('local');
    [, $org] = $this->customerWithOrganization();
    $service = archivableWebService($org->id);

    $result = app(FinalArchive::class)->create($service, archiveWebAdapter(filesFail: true, panelBackup: true), new ResourceRef('web_domain', '41', '1', ['domain' => 'firma.cz', 'system_user' => 'web41']), CommandContext::system('test'));
    expect(array_keys($result['parts']))->toContain('site-files.zip')->toContain('database-shop-db.sql');
    expect($result['backup']->state)->toBe('completed')->and(data_get($result['backup']->meta, 'attempts.panel_backup'))->toBe('fresh');
    expect(implode(' ', $result['gaps']))->toContain('panel');
});

it('refuses to finish the archive when no path can store the files, and keeps what it already had (audit §5aa)', function () {
    Storage::fake('local');
    config(['onhost.platform_backup.game_archive_timeout' => 0]); // do not sit out the panel's half hour in the test
    [, $org] = $this->customerWithOrganization();
    $service = archivableWebService($org->id);

    $archives = app(FinalArchive::class);
    expect(fn () => $archives->create($service, archiveWebAdapter(filesFail: true), new ResourceRef('web_domain', '41', '1', ['domain' => 'firma.cz', 'system_user' => 'web41']), CommandContext::system('test')))
        ->toThrow(DomainError::class, 'Soubory webu se nepodařilo zazálohovat');
    $backup = Backup::query()->where('service_id', $service->id)->firstOrFail();
    expect($backup->state)->toBe('failed')->and($archives->existing($service))->toBeNull()
        ->and((array) data_get($backup->meta, 'parts'))->toContain('service.json') // the metadata is kept even from a failed run
        ->and((string) data_get($backup->meta, 'attempts.transport'))->toContain('shell not available');
});

it('never archives (and so never deletes) a resource whose identity does not match (audit §5ab)', function () {
    Storage::fake('local');
    [, $org] = $this->customerWithOrganization();
    $service = archivableWebService($org->id);

    // the panel answers with a different domain than our records carry: the service is not the one we hold
    $foreign = Mockery::mock(WebHostingProvider::class, WebToolsProvider::class)->shouldIgnoreMissing();
    $foreign->shouldReceive('getActualState')->andReturn(new ActualState(true, ['domain' => 'cizi-web.cz', 'system_user' => 'web99'], 'active', now()->toISOString()));
    expect(fn () => app(FinalArchive::class)->create($service, $foreign, new ResourceRef('web_domain', '41', '1', ['domain' => 'firma.cz', 'system_user' => 'web41']), CommandContext::system('test')))
        ->toThrow(DomainError::class, 'jednoznačně ověřit');
    expect(Backup::query()->where('service_id', $service->id)->exists())->toBeFalse();

    // a second service pointing at the same remote resource (the s4s.electree.cz shape) cannot even be recorded
    expect(fn () => ProviderBinding::query()->create(['service_id' => archivableWebService($org->id)->id, 'provider_instance_id' => $service->provider_instance_id,
        'remote_type' => 'web_domain', 'remote_id' => '41', 'remote_node' => '1', 'meta' => [], 'idempotency_key' => 'twin']))->toThrow(QueryException::class);
});
