<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Web\DatabaseImport;
use Onhost\Domain\Services\Web\WebFileStore;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\ActualState;
use Onhost\Providers\Contracts\FileTransport;
use Onhost\Providers\Contracts\InfrastructureProvider;
use Onhost\Providers\Contracts\ProviderAdapter;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Contracts\WebToolsProvider;

/*
 * A SQL import is written OVER a live database — and it was the one destructive action with no copy of what it
 * replaced (the owner's rule, and Brain cards H456/H467). MySQL applies a dump statement by statement, so a file
 * that breaks half way leaves the database half old and half new while the site goes on serving from it.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** A dump waiting on the platform disk, as the upload endpoint would have left it. */
function stagedDump(Service $service, string $sql): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'dump');
    file_put_contents($tmp, $sql);

    return app(WebFileStore::class)->putUpload($service, $tmp, 'dump.sql');
}

/**
 * The panel behind the import: it can export the database, and its import either takes the dump or refuses it.
 *
 * @param  array{import?:string}  $options
 * @param  list<array{0:string,1:string}>  $log
 */
function importPanel(Service $service, array &$log, array $options = []): object
{
    $transport = Mockery::mock(FileTransport::class)->shouldIgnoreMissing();
    $adapter = Mockery::mock(ProviderAdapter::class, InfrastructureProvider::class, WebHostingProvider::class, WebToolsProvider::class)->shouldIgnoreMissing();
    $adapter->shouldReceive('siteFeatures')->andReturn(['backups' => true, 'restore' => true, 'databases' => true, 'db_export' => true, 'quotas' => true]);
    $adapter->shouldReceive('transport')->andReturn($transport);
    $adapter->shouldReceive('getActualState')->andReturn(new ActualState(true, ['domain' => 'shop.cz', 'name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz'], 'active', now()->toISOString()));
    $adapter->shouldReceive('listDatabases')->andReturn([['remote_id' => '4', 'name' => 'shop_db'], ['remote_id' => '5', 'name' => 'blog_db']]);
    $adapter->shouldReceive('exportDatabase')->andReturnUsing(function (ResourceRef $site, string $remoteId, string $localFile) use (&$log) {
        $log[] = ['export', $remoteId];
        file_put_contents($localFile, "-- the rows as they are now, database {$remoteId}\n");

        return ProviderResult::completed($site, ['exported' => true]);
    });
    $customersDump = true; // the panel refuses the customer's file; the copy we wrote ourselves it takes, as a real one would
    $adapter->shouldReceive('importDatabase')->andReturnUsing(function (ResourceRef $site, string $remoteId, string $localFile) use (&$log, $options, &$customersDump) {
        $log[] = ['import', $remoteId, (string) file_get_contents($localFile)];
        $mine = $customersDump;
        $customersDump = false;
        if ($mine && ($options['import'] ?? 'ok') === 'broken') { // the panel read the file and stopped on a statement it could not run
            throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'ERROR 1064 at line 812: You have an error in your SQL syntax');
        }
        if (($options['import'] ?? 'ok') === 'timeout') {
            throw new ProviderException('aapanel', ProviderErrorCode::TRANSIENT, 'the panel stopped answering');
        }

        return ProviderResult::completed($site, ['imported' => true]);
    });

    $registry = app(ProviderRegistry::class);
    $known = new ReflectionProperty($registry, 'instances');
    $known->setValue($registry, [(string) $service->provider_instance_id => $adapter] + (array) $known->getValue($registry));

    return $adapter;
}

it('reads how big a dump really is, and refuses one the plan cannot hold', function () {
    $plain = tempnam(sys_get_temp_dir(), 'dmp');
    file_put_contents($plain, str_repeat('INSERT INTO t VALUES (1);', 1000));
    expect(DatabaseImport::dumpBytes($plain))->toBe(filesize($plain));

    // a gzipped dump is not the size of what it becomes: the length gzip writes at the end of the file is the one that counts
    $gz = $plain.'.gz';
    file_put_contents($gz, (string) gzencode(str_repeat('INSERT INTO t VALUES (1);', 50000), 9));
    expect(DatabaseImport::dumpBytes($gz))->toBe(1250000)->and(filesize($gz))->toBeLessThan(100000);

    // data, indexes and what the engine needs while it loads: two times the dump
    $tight = ['disk_limit_bytes' => 2_000_000, 'disk_used_bytes' => 1_500_000];
    expect(fn () => DatabaseImport::assertRoom(1_250_000, $tight))->toThrow(DomainError::class);
    expect(DatabaseImport::assertRoom(100_000, $tight)['free'])->toBe(400_000);
    // a panel that does not measure disk is not guessed about: the check says so and lets it through
    expect(DatabaseImport::assertRoom(999_999_999, [])['checked'])->toBeFalse();
});

it('keeps a copy of exactly the database it is about to overwrite', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $log = [];
    importPanel($service, $log);
    $upload = stagedDump($service, "INSERT INTO orders VALUES (1);\n");
    $this->actingAs($user, 'sanctum');

    $response = $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'database.import', 'params' => ['remote_id' => '4', 'upload_id' => $upload]], ['Idempotency-Key' => 'imp-1'])->assertAccepted();
    $operation = driveOperation(Operation::query()->findOrFail($response->json('operation_id')));
    expect($operation->state)->toBe(Operation::SUCCEEDED, json_encode($operation->error));

    // the copy is taken BEFORE the import, and holds that one database — not the files, which nothing is touching
    expect($log)->toBe([['export', '4'], ['import', '4', "INSERT INTO orders VALUES (1);\n"]]);
    $copy = Backup::query()->where('service_id', $service->id)->where('kind', 'pre_import')->first();
    expect($copy)->not->toBeNull()->and($copy->state)->toBe('completed')->and($copy->protected)->toBeTrue()->and($copy->retention_until)->not->toBeNull();
    expect(array_keys((array) data_get($copy->meta, 'parts', [])))->not->toContain('site-files.tar.gz');
    expect($operation->result)->toHaveKey('dump_bytes'); // and the room was looked at before any of it
});

it('puts the database back when the panel refuses the dump, and leaves a timeout alone', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $log = [];
    importPanel($service, $log, ['import' => 'broken']);
    $upload = stagedDump($service, "DROP TABLE orders;\n");
    $this->actingAs($user, 'sanctum');

    $response = $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'database.import', 'params' => ['remote_id' => '4', 'upload_id' => $upload]], ['Idempotency-Key' => 'imp-2'])->assertAccepted();
    $operation = driveOperation(Operation::query()->findOrFail($response->json('operation_id')));
    expect($operation->state)->toBe(Operation::FAILED);

    // the panel said no for good: the copy taken seconds earlier goes back in, so nothing half-applied is left serving
    $record = (array) data_get($service->fresh()->tags, 'db_import');
    expect($record['database'])->toBe('4')->and($record['phase'])->toBe('rolled_back', (string) ($record['reason'] ?? ''))->and($record['copy_id'])->not->toBeNull();
    expect(collect($log)->last())->toBe(['import', '4', "-- the rows as they are now, database 4\n"]); // the rows from before, written back

    app(OutboxPublisher::class)->relayPending();
    $told = Notification::query()->where('event', 'service.database.import.failed')->get();
    expect($told->pluck('audience')->unique()->all())->toContain('customer')
        ->and($told->first()->body)->toContain('vrátili jsme');
});

it('does not race an import that may still be running on the node', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $log = [];
    importPanel($service, $log, ['import' => 'timeout']);
    $upload = stagedDump($service, "INSERT INTO orders VALUES (2);\n");
    $this->actingAs($user, 'sanctum');

    $response = $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'database.import', 'params' => ['remote_id' => '4', 'upload_id' => $upload]], ['Idempotency-Key' => 'imp-3'])->assertAccepted();
    driveOperation(Operation::query()->findOrFail($response->json('operation_id')));

    // a failure we may retry may still be running at the panel; writing the old rows over it would be the worse mistake
    $record = (array) data_get($service->fresh()->tags, 'db_import');
    expect($record['phase'])->toBe('left_as_is')->and($record['copy_id'])->not->toBeNull();
    expect(collect($log)->filter(fn (array $c) => $c[0] === 'import')->count())->toBeGreaterThanOrEqual(1)
        ->and(collect($log)->last()[2] ?? '')->not->toContain('the rows as they are now'); // nothing was written back
});
