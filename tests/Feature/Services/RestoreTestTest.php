<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\BackupPolicy;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Web\RestoreTest;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\ActualState;
use Onhost\Providers\Contracts\InfrastructureProvider;
use Onhost\Providers\Contracts\ProviderAdapter;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Contracts\WebToolsProvider;

/*
 * `backup-7` and `backup-30` are sold with `restore_test: monthly`, the price list says "Test obnovy měsíčně" and the
 * plan writes it into `backup_policies.restore_test` — and no code ever tested a restore. The promise was sold and
 * kept by nobody; the only other mention in the platform is a loyalty mission asking the CUSTOMER to try one.
 *
 * A test may not answer the question by overwriting the live database (H458), so the dump goes into a database of its
 * own and the result is judged by a round trip: what is exported back must carry the tables the dump carried.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake('local');
});

/**
 * A panel that can make, fill, read back and drop a database — and a record of what was asked of it.
 *
 * @param  list<array<int, mixed>>  $log
 * @param  array{lose?:string}  $options  `lose`: a table the panel drops on the way in
 */
function restorePanel(Service $service, array &$log, array &$live, array $options = []): object
{
    $adapter = Mockery::mock(ProviderAdapter::class, InfrastructureProvider::class, WebHostingProvider::class, WebToolsProvider::class)->shouldIgnoreMissing();
    $adapter->shouldReceive('siteFeatures')->andReturn(['databases' => true, 'backups' => true, 'restore' => true, 'db_export' => true]);
    $adapter->shouldReceive('getActualState')->andReturn(new ActualState(true, ['domain' => 'shop.cz', 'name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz'], 'active', now()->toISOString()));
    $adapter->shouldReceive('listDatabases')->andReturnUsing(fn () => array_values($live));
    $adapter->shouldReceive('createDatabase')->andReturnUsing(function (ResourceRef $site, array $spec) use (&$log, &$live) {
        $id = (string) (900 + count($live));
        $live[$id] = ['remote_id' => $id, 'name' => $spec['name'], 'user' => $spec['user'], 'charset' => 'utf8mb4', 'size_bytes' => null, 'tables' => []];
        $log[] = ['create', $spec['name']];

        return ProviderResult::completed(new ResourceRef('database', $id, 'lab', ['name' => $spec['name']], $site->serviceId), ['created' => true]);
    });
    $adapter->shouldReceive('importDatabase')->andReturnUsing(function (ResourceRef $site, string $remoteId, string $localFile) use (&$log, &$live, $options) {
        $tables = RestoreTest::tablesIn($localFile);
        if (isset($options['lose'])) { // a node that ran out of disk half way through the dump
            $tables = array_values(array_diff($tables, [$options['lose']]));
        }
        $live[$remoteId]['tables'] = $tables;
        $log[] = ['import', $remoteId, count($tables)];

        return ProviderResult::completed($site, ['imported' => true]);
    });
    $adapter->shouldReceive('exportDatabase')->andReturnUsing(function (ResourceRef $site, string $remoteId, string $localFile) use (&$log, &$live) {
        $sql = '';
        foreach ((array) ($live[$remoteId]['tables'] ?? []) as $table) {
            $sql .= "CREATE TABLE `{$table}` (id int);\n";
        }
        file_put_contents($localFile, $sql);
        $log[] = ['export', $remoteId];

        return ProviderResult::completed($site, ['exported' => true]);
    });
    $adapter->shouldReceive('deleteDatabase')->andReturnUsing(function (ResourceRef $site, string $remoteId) use (&$log, &$live) {
        unset($live[$remoteId]);
        $log[] = ['delete', $remoteId];

        return ProviderResult::completed(null, ['deleted' => true]);
    });

    $registry = app(ProviderRegistry::class);
    $known = new ReflectionProperty($registry, 'instances');
    $known->setValue($registry, [(string) $service->provider_instance_id => $adapter] + (array) $known->getValue($registry));

    return $adapter;
}

/** A finished set on the backup disk holding one database dump with three tables. */
function testableSet(Service $service, string $sql): Backup
{
    $set = FinalArchive::PREFIX.'/'.$service->organization_id.'/'.$service->id.'-'.now()->format('Ymd-His').'-abcd';
    Storage::disk('local')->put($set.'/database-shop_db.sql', $sql);
    Storage::disk('local')->put($set.'/manifest.json', (string) json_encode(['parts' => ['database-shop_db.sql' => ['bytes' => strlen($sql)]]]));

    return Backup::query()->create(['service_id' => $service->id, 'organization_id' => $service->organization_id, 'provider_instance_id' => $service->provider_instance_id,
        'kind' => 'scheduled', 'state' => 'completed', 'started_at' => now()->subHour(), 'finished_at' => now()->subHour(), 'protected' => false,
        'retention_until' => now()->addDays(30), 'meta' => ['set' => $set]]);
}

const RT_DUMP = "-- dump\nCREATE TABLE `orders` (id int);\nINSERT INTO `orders` VALUES (1);\nCREATE TABLE IF NOT EXISTS `customers` (id int);\nCREATE TABLE `invoices` (id int);\n";

it('reads the tables of a dump, gzipped or not', function () {
    $plain = tempnam(sys_get_temp_dir(), 'rt');
    file_put_contents($plain, RT_DUMP);
    expect(RestoreTest::tablesIn($plain))->toBe(['orders', 'customers', 'invoices']);

    // a set part is named `.sql` whatever it holds: aaPanel hands out `.sql.gz` and the name it was written under stays
    $gz = $plain.'.sql';
    file_put_contents($gz, (string) gzencode(RT_DUMP));
    expect(RestoreTest::tablesIn($gz))->toBe(['orders', 'customers', 'invoices']);
});

it('restores a backup into a database of its own and never touches the live one', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    BackupPolicy::query()->create(['service_id' => $service->id, 'product_key' => 'backup-7', 'schedule' => ['frequency' => 'daily'], 'retention' => ['days' => 7, 'generations' => 7], 'offsite' => true, 'restore_test' => ['cadence' => 'monthly'], 'state' => 'active']);
    $backup = testableSet($service, RT_DUMP);
    $log = [];
    $live = ['4' => ['remote_id' => '4', 'name' => 'shop_db', 'user' => 'shop', 'charset' => 'utf8mb4', 'size_bytes' => null, 'tables' => ['orders', 'customers', 'invoices']]];
    restorePanel($service, $log, $live);

    $stats = app(RestoreTest::class)->tick();
    expect($stats['started'])->toBe(1);
    $operation = Operation::query()->where('service_id', $service->id)->orderByDesc('created_at')->orderByDesc('id')->first();
    expect(driveOperation($operation)->state)->toBe(Operation::SUCCEEDED, json_encode($operation->error));

    // a database of its own was made, filled, read back and removed — the live one (4) was never written to
    expect(collect($log)->map(fn (array $c) => $c[0])->all())->toBe(['create', 'import', 'export', 'delete']);
    expect(collect($log)->firstWhere(0, 'import')[1])->not->toBe('4');
    expect(array_map('strval', array_keys($live)))->toBe(['4'])->and($live['4']['tables'])->toBe(['orders', 'customers', 'invoices']);

    $health = RestoreTest::health($service->fresh());
    expect($health['outcome'])->toBe('ok')->and($health['databases'][0])->toMatchArray(['database' => 'shop_db', 'tables' => 3, 'restored' => 3, 'verified' => true])
        ->and($health['backup_id'])->toBe($backup->id);

    // and it is not asked for again until the cadence is up
    expect(app(RestoreTest::class)->tick()['started'])->toBe(0);
});

it('says so when the archive does not come back whole', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    BackupPolicy::query()->create(['service_id' => $service->id, 'product_key' => 'backup-7', 'schedule' => ['frequency' => 'daily'], 'retention' => ['days' => 7, 'generations' => 7], 'offsite' => true, 'restore_test' => ['cadence' => 'monthly'], 'state' => 'active']);
    testableSet($service, RT_DUMP);
    $log = [];
    $live = [];
    restorePanel($service, $log, $live, ['lose' => 'invoices']);

    app(RestoreTest::class)->tick();
    $operation = Operation::query()->where('service_id', $service->id)->orderByDesc('created_at')->orderByDesc('id')->first();
    driveOperation($operation);

    $health = RestoreTest::health($service->fresh());
    expect($health['outcome'])->toBe('failed')->and($health['databases'][0]['verified'])->toBeFalse()
        ->and($health['problems'][0])->toContain('invoices');
    expect($live)->toBe([]); // the test database is gone even though the test failed

    app(OutboxPublisher::class)->relayPending();
    $told = Notification::query()->where('event', 'service.restore_test.failed')->get();
    expect($told->pluck('audience')->unique()->all())->toContain('customer')
        ->and($told->first()->body)->toContain('nezměnili');
});

it('asks for nothing when the plan does not promise a test', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    testableSet($service, RT_DUMP); // a backup, but no policy that promises a test
    expect(app(RestoreTest::class)->tick())->toMatchArray(['started' => 0, 'skipped' => 1]);

    BackupPolicy::query()->create(['service_id' => $service->id, 'product_key' => 'backup-plus', 'schedule' => ['frequency' => 'daily'], 'retention' => ['days' => 7, 'generations' => 7], 'offsite' => false, 'restore_test' => [], 'state' => 'active']);
    expect(app(RestoreTest::class)->tick()['started'])->toBe(0); // a policy with no cadence promises nothing either
});
