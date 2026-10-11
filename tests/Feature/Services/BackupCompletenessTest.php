<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Services\BackupCompleteness;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceHealthCheck;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Providers\Contracts\FileTransport;
use Onhost\Providers\Contracts\ProviderAdapter;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Contracts\WebToolsProvider;

/*
 * A finished set is not a whole set: every database the service owned when the set was taken has to be in it, and every
 * archive has to read back. An incomplete set is marked and is not the "latest good backup" the health check reports.
 */

function completenessService(string $organizationId): Service
{
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'ispconfig-completeness'], ['provider' => 'ispconfig', 'name' => 'ISPConfig', 'base_url' => 'https://node.test:8080', 'secret_ref' => 'env://ISPCONFIG_C', 'state' => 'active', 'options' => []]);

    return Service::query()->create(['organization_id' => $organizationId, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Webhosting', 'state' => ServiceStateMachine::ACTIVE,
        'region_code' => 'cz1', 'entitlements' => ['nvme_gb' => 50], 'desired_spec' => ['domain' => 'firma.cz'], 'sla_class' => 'standard', 'provider_instance_id' => $instance->id])->refresh();
}

/** @param array<string,string> $dumps  remote id => dump content written by the fake panel */
function completenessTake(Service $service, array $dumps): Backup
{
    $transport = Mockery::mock(FileTransport::class)->shouldIgnoreMissing();
    $transport->shouldReceive('download')->andReturnUsing(fn (string $path, string $localFile) => file_put_contents($localFile, gzencode(str_repeat('site files ', 300))));
    $adapter = Mockery::mock(ProviderAdapter::class, WebHostingProvider::class, WebToolsProvider::class)->shouldIgnoreMissing();
    $adapter->shouldReceive('listDatabases')->andReturn(array_map(fn ($id) => ['remote_id' => (string) $id, 'name' => 'db_'.$id], array_keys($dumps)));
    $adapter->shouldReceive('exportDatabase')->andReturnUsing(function (ResourceRef $site, string $remoteId, string $localFile) use ($dumps) {
        file_put_contents($localFile, $dumps[$remoteId]);

        return ProviderResult::completed($site, ['exported' => true]);
    });
    $adapter->shouldReceive('transport')->andReturn($transport);

    return app(FinalArchive::class)->create($service, $adapter, new ResourceRef('web_domain', '41'), CommandContext::system('test'), 'op-'.bin2hex(random_bytes(4)), ['verified' => true], ['kind' => 'scheduled', 'protected' => false, 'fresh_only' => true, 'retention_days' => 7])['backup'];
}

beforeEach(function () {
    Storage::fake('local');
    [, $org] = $this->customerWithOrganization();
    $this->service = completenessService($org->id);
});

function healthBackup(Service $service): array
{
    return collect(app(ServiceHealthCheck::class)->run($service->refresh())['findings'])->firstWhere('key', 'backup');
}

it('leaves a set with files and every database unmarked and reports it as the last backup', function () {
    $backup = completenessTake($this->service, ['7' => "CREATE TABLE a (id int);\n", '8' => "CREATE TABLE b (id int);\n"]);

    expect($backup->state)->toBe('completed')->and(data_get($backup->meta, 'incomplete'))->toBeNull();
    expect(healthBackup($this->service))->toMatchArray(['level' => 'ok']);
});

it('marks a set incomplete when a database dump is empty and does not count it as the latest good backup', function () {
    $good = completenessTake($this->service, ['7' => "CREATE TABLE a (id int);\n", '8' => "CREATE TABLE b (id int);\n"]);
    $good->forceFill(['finished_at' => now()->subHours(5)])->save();

    $bad = completenessTake($this->service, ['7' => "CREATE TABLE a (id int);\n", '8' => '']);

    expect(data_get($bad->refresh()->meta, 'incomplete.missing'))->toBe(['db-8'])
        ->and(BackupCompleteness::missingOf($bad))->toBe(['db-8'])
        ->and(data_get($good->refresh()->meta, 'incomplete'))->toBeNull();
    $finding = healthBackup($this->service);
    expect($finding['level'])->toBe('warn')->and($finding['cs'])->toContain('neúplná')->and($finding['cs'])->toContain('chybí DB db-8')
        ->and($finding['cs'])->toContain($good->finished_at->format('j. n. Y H:i'));
});

it('finds a database the manifest knew about but the disk lost, when the nightly verify looks again', function () {
    $backup = completenessTake($this->service, ['7' => "CREATE TABLE a (id int);\n", '8' => "CREATE TABLE b (id int);\n"]);
    expect(data_get($backup->meta, 'incomplete'))->toBeNull();

    Storage::disk('local')->delete(data_get($backup->meta, 'set').'/database-db-8.sql');
    $backup->forceFill(['verified_at' => now()->subDays(40)])->save();
    $stats = app(FinalArchive::class)->verifyStored();

    // the checksum pass already refuses the set; completeness is the second, independent look and names the database
    expect($stats['failed'])->toBe(1);
    $verdict = app(BackupCompleteness::class)->inspect($backup->refresh());
    expect($verdict['missing'])->toBe(['db-8']);
    expect(healthBackup($this->service)['cs'])->toContain('chybí DB db-8');
});

it('marks a set incomplete when an archive is a truncated gzip', function () {
    $backup = completenessTake($this->service, ['7' => "CREATE TABLE a (id int);\n"]);
    $set = (string) data_get($backup->meta, 'set');
    $disk = Storage::disk('local');
    $full = gzencode(str_repeat('site files ', 3000));
    $disk->put($set.'/site-files.tar.gz', substr($full, 0, (int) (strlen($full) / 2)));
    $manifest = json_decode($disk->get($set.'/manifest.json'), true);
    $manifest['parts']['site-files.tar.gz']['bytes'] = strlen($disk->get($set.'/site-files.tar.gz'));
    $disk->put($set.'/manifest.json', json_encode($manifest));

    $verdict = app(BackupCompleteness::class)->inspect($backup->refresh());

    expect($verdict['problems'])->not->toBe([])->and(implode(' ', $verdict['problems']))->toContain('truncated gzip');
    expect(data_get($backup->refresh()->meta, 'incomplete.problems'))->not->toBeEmpty();
    expect(healthBackup($this->service)['level'])->toBe('warn');
});

it('marks a set incomplete when the site-files archive is empty', function () {
    $backup = completenessTake($this->service, ['7' => "CREATE TABLE a (id int);\n"]);
    $set = (string) data_get($backup->meta, 'set');
    $disk = Storage::disk('local');
    $disk->put($set.'/site-files.tar.gz', '');
    $manifest = json_decode($disk->get($set.'/manifest.json'), true);
    $manifest['parts']['site-files.tar.gz']['bytes'] = 0;
    $disk->put($set.'/manifest.json', json_encode($manifest));

    $verdict = app(BackupCompleteness::class)->inspect($backup->refresh());

    expect(implode(' ', $verdict['problems']))->toContain('site-files.tar.gz is empty');
    expect(data_get($backup->refresh()->meta, 'incomplete'))->not->toBeNull();
});

it('clears the mark again when a later look finds the set whole', function () {
    $backup = completenessTake($this->service, ['7' => "CREATE TABLE a (id int);\n"]);
    $backup->forceFill(['meta' => array_merge((array) $backup->meta, ['incomplete' => ['missing' => ['x'], 'problems' => []]])])->save();

    app(BackupCompleteness::class)->inspect($backup->refresh());

    expect(data_get($backup->refresh()->meta, 'incomplete'))->toBeNull();
});
