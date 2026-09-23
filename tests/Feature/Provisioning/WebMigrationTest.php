<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Provisioning\ServiceMigrationService;
use Onhost\Domain\Provisioning\Workflows\WebMigrationWorkflow;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\Website;
use Onhost\Domain\Services\Web\DatabaseCredentials;
use Onhost\Providers\Contracts\ActualState;
use Onhost\Providers\Contracts\FileTransport;
use Onhost\Providers\Contracts\InfrastructureProvider;
use Onhost\Providers\Contracts\ProviderAdapter;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Contracts\WebToolsProvider;

/*
 * A web hosting could not be moved at all: only game servers and virtual machines had a migration saga, so an
 * evacuation walked past every site on a shared node and the only way out was a backup, a new order and a customer
 * copying their own site across. A shared web node therefore could not be drained — the one thing an operator has to
 * be able to do before they touch the hardware.
 *
 * The move is only safe if the databases arrive as themselves: a site opens them by name, user and password out of
 * its own `wp-config.php`, which the platform cannot rewrite. So a site whose database password the platform never
 * saw is refused by name before anything is made, and a site that can move keeps every database's identity.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** The node the site is to move to, on a panel of its own. */
function migrationTarget(): Node
{
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'aapanel-managed02'], ['provider' => 'aapanel', 'name' => 'aaPanel managed02', 'region_code' => 'cz1',
        'base_url' => 'https://managed02.mgmt.test:8888', 'secret_ref' => 'env://AAPANEL_MANAGED02', 'state' => 'active', 'capabilities' => ['web' => true], 'adapter_version' => '1.0.0']);

    return Node::query()->firstOrCreate(['provider_instance_id' => $instance->id, 'name' => 'aapanel-web02'], ['region_code' => 'cz1', 'role' => 'managed', 'state' => 'active',
        'capacity' => ['cpu_cores' => 16, 'ram_mb' => 65536, 'disk_gb' => 2000], 'tags' => ['public_ipv4' => '203.0.113.77']]);
}

/** Both panels as the platform sees them: what the source hands over, and what the target is asked to make. */
function migrationPanels(Service $service, Node $target, array &$log, array $options = []): void
{
    $sourceTransport = Mockery::mock(FileTransport::class)->shouldIgnoreMissing();
    $sourceTransport->shouldReceive('archive')->andReturnUsing(function (array $paths, string $file) use (&$log) {
        $log[] = ['archive', $file];
    });
    $sourceTransport->shouldReceive('download')->andReturnUsing(fn (string $path, string $local) => file_put_contents($local, gzencode(str_repeat('site files ', 400))));
    $source = Mockery::mock(ProviderAdapter::class, InfrastructureProvider::class, WebHostingProvider::class, WebToolsProvider::class)->shouldIgnoreMissing();
    $source->shouldReceive('transport')->andReturn($sourceTransport);
    $source->shouldReceive('shellAvailable')->andReturn(true);
    // the panel answers with the owner our binding records (the site folder for aaPanel), or the identity check
    // refuses to archive — nothing of a customer's is ever copied out of a resource we cannot recognise
    $source->shouldReceive('getActualState')->andReturn(new ActualState(true, ['domain' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz'], 'active', now()->toISOString()));
    $source->shouldReceive('listDatabases')->andReturn([['remote_id' => '5', 'name' => 'shop_db', 'user' => 'shop_user', 'charset' => 'utf8mb4', 'size_bytes' => null]]);
    $source->shouldReceive('exportDatabase')->andReturnUsing(function (ResourceRef $ref, string $remoteId, string $local) use (&$log) {
        $log[] = ['export', $remoteId];
        file_put_contents($local, "-- dump of shop_db\nCREATE TABLE wp_posts (id int);\n");

        return ProviderResult::completed(null, ['exported' => true]);
    });
    $source->shouldReceive('suspend')->andReturnUsing(function (ResourceRef $ref) use (&$log) {
        $log[] = ['suspend', $ref->remoteId];

        return ProviderResult::completed(null, ['suspended' => true]);
    });
    $source->shouldReceive('resume')->andReturnUsing(function (ResourceRef $ref) use (&$log) {
        $log[] = ['resume', $ref->remoteId];

        return ProviderResult::completed(null, ['resumed' => true]);
    });
    $source->shouldReceive('terminate')->andReturnUsing(function (ResourceRef $ref) use (&$log) {
        $log[] = ['terminate', $ref->remoteId];

        return ProviderResult::completed(null, ['removed' => true]);
    });

    $targetTransport = Mockery::mock(FileTransport::class)->shouldIgnoreMissing();
    $targetTransport->shouldReceive('upload')->andReturnUsing(function (string $path, string $local) use (&$log) {
        $log[] = ['upload', $path, filesize($local) > 0];
    });
    $targetTransport->shouldReceive('extract')->andReturnUsing(function (string $archive, string $to) use (&$log) {
        $log[] = ['extract', $archive];
    });
    $panel = Mockery::mock(ProviderAdapter::class, InfrastructureProvider::class, WebHostingProvider::class, WebToolsProvider::class)->shouldIgnoreMissing();
    $panel->shouldReceive('transport')->andReturn($targetTransport);
    $panel->shouldReceive('provision')->andReturnUsing(function ($spec) use (&$log) {
        $log[] = ['provision', (string) ($spec->attributes['domain'] ?? '')];

        return ProviderResult::completed(new ResourceRef('site', '77', 'aapanel-managed02', ['name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz', 'system_user' => 'web77'], null), ['created' => true]);
    });
    $panel->shouldReceive('createDatabase')->andReturnUsing(function (ResourceRef $ref, array $spec) use (&$log) {
        $log[] = ['database', (string) ($spec['name'] ?? ''), (string) ($spec['user'] ?? ''), (string) ($spec['password'] ?? '')];

        return ProviderResult::completed(new ResourceRef('database', '9', 'aapanel-managed02', ['name' => (string) ($spec['name'] ?? '')], null), ['created' => true]);
    });
    $panel->shouldReceive('importDatabase')->andReturnUsing(function (ResourceRef $ref, string $remoteId, string $local) use (&$log, $options) {
        if ($options['import_fails'] ?? false) {
            throw new RuntimeException('the target panel refused the dump');
        }
        $log[] = ['import', $remoteId];

        return ProviderResult::completed(null, ['imported' => true]);
    });

    $registry = app(ProviderRegistry::class);
    $registry->useAdapter((string) $service->provider_instance_id, $source);
    $registry->useAdapter((string) $target->provider_instance_id, $panel);
}

/** A site with one database the platform made itself, so it holds its password. */
function migrationService(object $org, bool $withCredentials = true): Service
{
    $service = featureWebService($org, 'aapanel');
    Node::query()->where('id', $service->node_id)->update(['tags' => json_encode(['public_ipv4' => '203.0.113.10'])]);
    Website::query()->create(['service_id' => $service->id, 'domain' => 'shop.cz', 'aliases' => [], 'executor' => 'aapanel', 'php_version' => '8.3',
        'remote_site_id' => 41, 'remote_node' => 'aapanel-managed01', 'system_user' => 'web41', 'state' => 'active']);
    if ($withCredentials) {
        app(DatabaseCredentials::class)->remember($service, '5', ['name' => 'shop_db', 'user' => 'shop_user', 'password' => 'Correct-Horse-Battery-9']);
    }

    return $service->refresh();
}

it('refuses to move a site whose database password the platform does not hold', function () {
    Storage::fake('local');
    $log = [];
    [$user, $org] = $this->customerWithOrganization();
    $service = migrationService($org, withCredentials: false);
    $target = migrationTarget();
    migrationPanels($service, $target, $log);

    $operation = driveOperation(app(ServiceMigrationService::class)->start($service, $target->id, 'hardware swap', $this->contextFor($user, $org)));

    expect($operation->state)->toBe(Operation::FAILED)
        ->and((string) data_get($operation->error, 'message', ''))->toContain('shop_db')
        ->and(array_column($log, 0))->not->toContain('provision')   // nothing was made on the other node
        ->and(array_column($log, 0))->not->toContain('terminate');  // and nothing of the customer's was touched
});

it('carries the site to the other node, every database keeping its name, user and password', function () {
    Storage::fake('local');
    $log = [];
    [$user, $org] = $this->customerWithOrganization();
    $service = migrationService($org);
    $target = migrationTarget();
    migrationPanels($service, $target, $log);

    $operation = driveOperation(app(ServiceMigrationService::class)->start($service, $target->id, 'hardware swap', $this->contextFor($user, $org)));

    expect($operation->state)->toBe(Operation::SUCCEEDED, $operation->step_label.': '.(string) data_get($operation->error, 'message', ''));
    $kinds = array_column($log, 0);
    expect(array_search('suspend', $kinds, true))->toBeLessThan(array_search('export', $kinds, true)) // nothing may be written between the copy and the switch
        ->and($kinds)->not->toContain('resume')                                             // the old site is not started again; it is removed
        ->and(array_search('provision', $kinds, true))->toBeLessThan(array_search('database', $kinds, true))
        ->and(array_search('import', $kinds, true))->toBeLessThan(array_search('terminate', $kinds, true)) // the old site goes last
        ->and(collect($log)->firstWhere(0, 'database'))->toBe(['database', 'shop_db', 'shop_user', 'Correct-Horse-Battery-9'])
        ->and(collect($log)->firstWhere(0, 'extract'))->not->toBeNull();

    // the platform now serves the customer from the new node
    $service->refresh();
    $binding = ProviderBinding::query()->where('service_id', $service->id)->where('remote_type', 'site')->firstOrFail();
    expect($service->node_id)->toBe($target->id)
        ->and($service->provider_instance_id)->toBe($target->provider_instance_id)
        ->and($binding->remote_id)->toBe('77')
        ->and(WebMigrationWorkflow::targetBinding($service->id))->toBeNull()   // the temporary binding is gone
        ->and((int) Website::query()->where('service_id', $service->id)->value('remote_site_id'))->toBe(77)
        ->and(data_get($service->tags, 'migration.state'))->toBe('finished');
});

it('leaves the customer on the old node when the copy fails', function () {
    Storage::fake('local');
    $log = [];
    [$user, $org] = $this->customerWithOrganization();
    $service = migrationService($org);
    $target = migrationTarget();
    migrationPanels($service, $target, $log, ['import_fails' => true]);
    $before = ProviderBinding::query()->where('service_id', $service->id)->where('remote_type', 'site')->firstOrFail();

    $operation = driveOperation(app(ServiceMigrationService::class)->start($service, $target->id, 'hardware swap', $this->contextFor($user, $org)));

    expect($operation->state)->toBe(Operation::FAILED)
        ->and(array_column($log, 0))->toContain('resume')                     // the site the customer stays on serves again
        ->and(array_column($log, 0))->not->toContain('terminate')            // the site the customer is served from stays
        ->and($service->refresh()->node_id)->not->toBe($target->id)
        ->and($before->refresh()->remote_id)->toBe('41');
});

it('offers the move for web hostings, so a shared node can be drained', function () {
    [, $org] = $this->customerWithOrganization();
    $service = migrationService($org);

    expect(app(ServiceMigrationService::class)->supports($service))->toBeTrue()
        ->and(array_keys(ServiceMigrationService::WORKFLOWS))->toContain('web')->toContain('managed');
});
