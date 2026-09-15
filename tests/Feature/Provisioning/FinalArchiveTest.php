<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Providers\Contracts\ActualState;
use Onhost\Providers\Contracts\FileTransport;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Contracts\WebToolsProvider;

/** A web panel that hands over one database dump and the site files (the archive uses only these four calls). */
function archiveWebAdapter(bool $filesFail = false): object
{
    $transport = Mockery::mock(FileTransport::class)->shouldIgnoreMissing();
    $transport->shouldReceive('archive')->andReturnUsing(function () use ($filesFail) {
        if ($filesFail) {
            throw new RuntimeException('shell not available on this node');
        }
    });
    $transport->shouldReceive('download')->andReturnUsing(fn (string $path, string $localFile) => file_put_contents($localFile, gzencode(str_repeat('site files', 200))));

    $adapter = Mockery::mock(WebHostingProvider::class, WebToolsProvider::class)->shouldIgnoreMissing();
    $adapter->shouldReceive('listDatabases')->andReturn([['remote_id' => '7', 'name' => 'shop_db']]);
    $adapter->shouldReceive('exportDatabase')->andReturnUsing(function (ResourceRef $site, string $remoteId, string $localFile) {
        file_put_contents($localFile, "-- dump of {$remoteId}\nCREATE TABLE orders (id int);\n");

        return ProviderResult::completed($site, ['exported' => true]);
    });
    $adapter->shouldReceive('transport')->andReturn($transport);
    $adapter->shouldReceive('getActualState')->andReturn(new ActualState(true, ['domain' => 'firma.cz'], 'active', now()->toISOString()));

    return $adapter;
}

it('archives files, databases and metadata before a termination, keeps the set for 60 days and prunes it afterwards (audit §5aa)', function () {
    Storage::fake('local');
    [, $org] = $this->customerWithOrganization();
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Webhosting', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'entitlements' => ['nvme_gb' => 50], 'desired_spec' => ['domain' => 'firma.cz'], 'sla_class' => 'standard']);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => 'pvi_test', 'remote_type' => 'web_domain', 'remote_id' => '41', 'remote_node' => '1', 'meta' => ['domain' => 'firma.cz'], 'idempotency_key' => 'final-archive-test']);

    $archives = app(FinalArchive::class);
    $result = $archives->create($service, archiveWebAdapter(), new ResourceRef('web_domain', '41', '1', ['domain' => 'firma.cz']), CommandContext::system('test'));
    $set = $result['set'];
    expect(array_keys($result['parts']))->toContain('service.json')->toContain('database-shop-db.sql')->toContain('site-files.tar.gz');
    expect(Storage::disk('local')->exists($set.'/manifest.json'))->toBeTrue()->and(Storage::disk('local')->exists($set.'/site-files.tar.gz'))->toBeTrue();
    $metadata = json_decode((string) Storage::disk('local')->get($set.'/service.json'), true);
    expect($metadata['service']['id'])->toBe($service->id)->and($metadata['bindings'][0]['remote_id'])->toBe('41')->and($metadata['actual_state']['status'])->toBe('active');

    $backup = $result['backup'];
    expect($backup->kind)->toBe('final')->and($backup->state)->toBe('completed')->and($backup->protected)->toBeTrue()->and($backup->verify_status)->toBe('ok')
        ->and((int) now()->diffInDays($backup->retention_until))->toBeGreaterThanOrEqual(59)
        ->and($archives->existing($service))->not->toBeNull();

    // the retention holds: pruning before day 60 keeps the set, afterwards it goes
    expect($archives->prune())->toBe(0)->and(Storage::disk('local')->exists($set.'/manifest.json'))->toBeTrue();
    $backup->forceFill(['retention_until' => now()->subDay()])->save();
    expect($archives->prune())->toBe(1)->and(Storage::disk('local')->exists($set.'/manifest.json'))->toBeFalse()
        ->and(Backup::query()->whereKey($backup->id)->value('state'))->toBe('expired');
});

it('refuses to finish the archive when a component the panel offers cannot be stored (audit §5aa)', function () {
    Storage::fake('local');
    [, $org] = $this->customerWithOrganization();
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Webhosting', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'entitlements' => [], 'desired_spec' => [], 'sla_class' => 'standard']);
    $archives = app(FinalArchive::class);
    expect(fn () => $archives->create($service, archiveWebAdapter(filesFail: true), new ResourceRef('web_domain', '41', '1'), CommandContext::system('test')))->toThrow(RuntimeException::class);
    expect(Backup::query()->where('service_id', $service->id)->value('state'))->toBe('failed')->and($archives->existing($service))->toBeNull();
});
