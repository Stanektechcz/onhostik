<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\StagingLink;
use Onhost\Domain\Services\Models\VerifiedRestoreRun;
use Onhost\Domain\Services\Web\RestoreTest;
use Onhost\Domain\Services\Web\StagingRestoreTarget;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\ActualState;
use Onhost\Providers\Contracts\FileTransport;
use Onhost\Providers\Contracts\InfrastructureProvider;
use Onhost\Providers\Contracts\NodeShell;
use Onhost\Providers\Contracts\ProviderAdapter;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\ShellResult;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Contracts\WebToolsProvider;

/*
 * W2-4: the copy of a verified restore is made by the staging facility on the production node, and everything the restore writes
 * goes into the copy: the panel is asked only about the copy's own site (remote id 42), never about production's (41). WordPress in
 * the copy is pointed at the copy's databases and kept from sending mail, calling other servers and running WP-Cron before
 * anything may request it; an application that is not WordPress but has databases is not requested at all.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake('local');
    Queue::fake(); // the copy's own provisioning run is not the subject here
});

/** A web set with one dump of three tables and a files archive. */
function srtSet(Service $service): Backup
{
    $set = FinalArchive::PREFIX.'/'.$service->organization_id.'/'.$service->id.'-srt';
    Storage::disk('local')->put($set.'/database-shop_db.sql', "CREATE TABLE `orders` (id int);\nCREATE TABLE `customers` (id int);\nCREATE TABLE `invoices` (id int);\n");
    Storage::disk('local')->put($set.'/site-files.tar.gz', str_repeat('x', 64));

    return Backup::query()->create(['service_id' => $service->id, 'organization_id' => $service->organization_id, 'provider_instance_id' => $service->provider_instance_id,
        'kind' => 'scheduled', 'state' => 'completed', 'started_at' => now()->subHours(3), 'finished_at' => now()->subHours(3), 'protected' => false,
        'retention_until' => now()->addDays(30), 'meta' => ['set' => $set, 'family' => 'web']]);
}

/**
 * The panel of the node, answering for every site on it, and a log of which site each call named.
 *
 * @param  list<array<int, mixed>>  $log
 */
function srtPanel(Service $production, array &$log, bool $wordpress = true): void
{
    $tables = [];
    $transport = Mockery::mock(FileTransport::class);
    $transport->shouldReceive('root')->andReturn('/www/wwwroot/copy');
    $transport->shouldReceive('exists')->andReturnUsing(fn (string $path) => $wordpress && $path === 'wp-config.php');
    $transport->shouldReceive('read')->andReturn("<?php\ndefine('DB_NAME', 'shop_db');\ndefine('DB_USER', 'shop');\n");
    foreach (['upload', 'extract', 'delete', 'mkdir', 'write'] as $verb) {
        $transport->shouldReceive($verb)->andReturnUsing(function (...$args) use (&$log, $verb) {
            $log[] = ['file.'.$verb, (string) $args[0], $verb === 'write' ? (string) $args[1] : ''];
        });
    }
    $shell = Mockery::mock(NodeShell::class);
    $shell->shouldReceive('run')->andReturnUsing(function (string $command) use (&$log) {
        $log[] = ['shell', $command];

        return new ShellResult(0, '0');
    });
    $adapter = Mockery::mock(ProviderAdapter::class, InfrastructureProvider::class, WebHostingProvider::class, WebToolsProvider::class)->shouldIgnoreMissing();
    $adapter->shouldReceive('transport')->andReturnUsing(function (ResourceRef $ref) use (&$log, $transport) {
        $log[] = ['site', $ref->remoteId];

        return $transport;
    });
    $adapter->shouldReceive('shell')->andReturnUsing(function (ResourceRef $ref) use (&$log, $shell) {
        $log[] = ['site', $ref->remoteId];

        return $shell;
    });
    $adapter->shouldReceive('shellAvailable')->andReturn(true);
    $adapter->shouldReceive('siteUser')->andReturn('web42');
    $adapter->shouldReceive('wpCommand')->andReturn('wp');
    $adapter->shouldReceive('createDatabase')->andReturnUsing(function (ResourceRef $ref, array $spec) use (&$log) {
        $log[] = ['site', $ref->remoteId];
        $log[] = ['db.create', $spec['name']];

        return ProviderResult::completed(new ResourceRef('database', '901', 'lab', ['name' => $spec['name']], $ref->serviceId), ['created' => true]);
    });
    $adapter->shouldReceive('importDatabase')->andReturnUsing(function (ResourceRef $ref, string $id, string $local) use (&$log, &$tables) {
        $log[] = ['site', $ref->remoteId];
        $tables[$id] = RestoreTest::tablesIn($local);

        return ProviderResult::completed($ref);
    });
    $adapter->shouldReceive('exportDatabase')->andReturnUsing(function (ResourceRef $ref, string $id, string $local) use (&$log, &$tables) {
        $log[] = ['site', $ref->remoteId];
        file_put_contents($local, implode('', array_map(fn ($t) => "CREATE TABLE `{$t}` (id int);\n", $tables[$id] ?? [])));

        return ProviderResult::completed($ref);
    });
    $adapter->shouldReceive('deleteDatabase')->andReturnUsing(function (ResourceRef $ref, string $id) use (&$log) {
        $log[] = ['site', $ref->remoteId];
        $log[] = ['db.delete', $id];

        return ProviderResult::completed(null);
    });
    $adapter->shouldReceive('terminate')->andReturnUsing(function (ResourceRef $ref) use (&$log) {
        $log[] = ['site', $ref->remoteId];
        $log[] = ['site.delete', $ref->remoteId];

        return ProviderResult::completed(null, []);
    });
    $adapter->shouldReceive('getActualState')->andReturn(ActualState::missing());
    $registry = app(ProviderRegistry::class);
    $known = new ReflectionProperty($registry, 'instances');
    $known->setValue($registry, [(string) $production->provider_instance_id => $adapter] + (array) $known->getValue($registry));
}

/** The copy as its provisioning leaves it: active, with its own site on the panel. */
function srtCopyReady(StagingRestoreTarget $target, Service $production, VerifiedRestoreRun $run): Service
{
    $made = $target->prepare($production, $run, CommandContext::system('test')->withScope($production->organization_id));
    $copy = Service::query()->findOrFail($made['service_id']);
    $copy->forceFill(['state' => ServiceStateMachine::ACTIVE])->save();
    Operation::query()->where('service_id', $copy->id)->update(['state' => Operation::SUCCEEDED]); // its provisioning run finished
    ProviderBinding::query()->create(['service_id' => $copy->id, 'provider_instance_id' => $copy->provider_instance_id, 'remote_type' => 'site', 'remote_id' => '42', 'remote_node' => 'aapanel-managed01',
        'meta' => ['name' => $copy->hostname], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "srt:{$copy->id}", 'adapter_version' => '1.0.0']);
    $run->mergeTarget(['service_id' => $copy->id, 'domain' => $made['domain'], 'databases' => []]);

    return $copy;
}

function srtRun(Service $production, Backup $backup): VerifiedRestoreRun
{
    return VerifiedRestoreRun::query()->create(['organization_id' => $production->organization_id, 'service_id' => $production->id, 'backup_id' => $backup->id, 'trigger' => 'staff',
        'state' => VerifiedRestoreRun::RUNNING, 'started_at' => now(), 'steps' => [], 'target' => []]);
}

it('makes the copy beside production, restores into it, keeps WordPress in it off the world and removes only the copy', function () {
    [, $org] = $this->customerWithOrganization();
    $production = featureWebService($org, 'aapanel');
    $production->forceFill(['entitlements' => array_replace((array) $production->entitlements, ['verified_restore' => 'monthly', 'staging' => true])])->save();
    $backup = srtSet($production);
    $run = srtRun($production, $backup);
    $target = app(StagingRestoreTarget::class);
    $log = [];
    srtPanel($production, $log);

    $copy = srtCopyReady($target, $production, $run);
    expect($copy->node_id)->toBe($production->node_id)->and($copy->provider_instance_id)->toBe($production->provider_instance_id)
        ->and($copy->hostname)->toStartWith('shop-cz-rv')->toEndWith('.'.config('onhost.web_tools.staging_suffix'))
        ->and(data_get($copy->tags, 'restore_target_of'))->toBe($production->id)->and(data_get($copy->tags, 'billing'))->toBe('included')
        ->and($copy->entitlements)->not->toHaveKey('verified_restore')->and($copy->entitlements['backups'])->toBeFalse()->and($copy->entitlements['staging'])->toBeFalse()
        ->and(StagingLink::query()->count())->toBe(0) // the customer's own staging is neither made nor reused
        ->and(Operation::query()->where('service_id', $copy->id)->exists())->toBeTrue(); // made through the ordinary provisioning run

    $result = $target->restore($run->fresh(), $production, $backup);
    expect($result['databases']['status'])->toBe('pass')->and($result['databases']['rows'][0])->toMatchArray(['database' => 'shop_db', 'tables' => 3, 'restored' => 3, 'verified' => true])
        ->and($result['files']['status'])->toBe('pass')
        ->and($result['probe_allowed'])->toBeTrue()->and($result['wordpress'])->toBeTrue()
        ->and(implode("\n", $result['suppression']['applied']))->toContain('DISABLE_WP_CRON')->toContain('WP_HTTP_BLOCK_EXTERNAL')->toContain('pre_wp_mail')
        ->and($result['suppression']['not_applied'])->not->toBeEmpty();
    // every call named the copy's own site — production's (41) was never asked for anything
    expect(collect($log)->where(0, 'site')->pluck(1)->unique()->values()->all())->toBe(['42']);
    $shell = collect($log)->where(0, 'shell')->pluck(1)->implode("\n");
    $copyDb = collect($log)->firstWhere(0, 'db.create')[1];
    expect($shell)->toContain("config set DB_NAME '{$copyDb}'")->toContain('config set DISABLE_WP_CRON true --raw')->toContain('config set WP_HTTP_BLOCK_EXTERNAL true --raw')
        ->toContain("search-replace 'https://shop.cz' 'https://{$copy->hostname}'")->toContain('--exclude=./.user.ini');
    expect(collect($log)->first(fn ($c) => $c[0] === 'file.write')[2])->toContain("add_filter('pre_wp_mail', '__return_false'");
    // the copy's database password went to the panel and wp-config only; the answer carries none
    expect(json_encode($result))->not->toContain('password');

    Http::fake(["https://{$copy->hostname}/" => Http::response('<html>ok</html>', 200)]);
    $run->mergeTarget(['databases' => $result['created'], 'wordpress' => true]);
    expect($target->probe($run->fresh()))->toMatchArray(['status' => 200]);

    $asked = $target->cleanup($run->fresh());
    expect($asked['detail'])->toBe('odstranění vyžádáno');
    expect($asked['requested'])->toBeTrue()
        ->and(collect($log)->where(0, 'db.delete')->pluck(1)->all())->toBe(['901'])
        ->and(collect($log)->where(0, 'site.delete')->pluck(1)->all())->toBe(['42'])
        ->and(collect($log)->where(0, 'site')->pluck(1)->unique()->values()->all())->toBe(['42'])
        ->and($copy->fresh()->state)->toBe(ServiceStateMachine::TERMINATED)
        ->and($production->fresh()->state)->toBe(ServiceStateMachine::ACTIVE);
    expect($target->cleanedUp($run->fresh()))->toBeTrue();

    // the copy's activation is no news for the customer (it is made and removed by the run, every month)
    app(OutboxPublisher::class)->publish(GenericEvent::of('service.activated', 'service', $copy->id, ['product_key' => 'web-hosting'], $org->id));
    app(OutboxPublisher::class)->publish(GenericEvent::of('service.activated', 'service', $production->id, ['product_key' => 'web-hosting'], $org->id));
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('event', 'service.activated')->where('organization_id', $org->id)->count())->toBe(1);
});

it('does not request an application that is not WordPress but has databases, and says why', function () {
    [, $org] = $this->customerWithOrganization();
    $production = featureWebService($org, 'aapanel');
    $backup = srtSet($production);
    $run = srtRun($production, $backup);
    $target = app(StagingRestoreTarget::class);
    $log = [];
    srtPanel($production, $log, wordpress: false);
    srtCopyReady($target, $production, $run);

    $result = $target->restore($run->fresh(), $production, $backup);
    expect($result['databases']['status'])->toBe('pass')
        ->and($result['probe_allowed'])->toBeFalse()->and($result['probe_refusal'])->toContain('databázím produkce')
        ->and(implode("\n", $result['suppression']['not_applied']))->toContain('není WordPress');
    expect(collect($log)->where(0, 'shell')->pluck(1)->implode("\n"))->not->toContain('wp ');
});
