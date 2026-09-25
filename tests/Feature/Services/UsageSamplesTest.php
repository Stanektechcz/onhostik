<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Billing\Models\UsageEvent;
use Onhost\Domain\Billing\RatingService;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Metering\UsageMetrics;
use Onhost\Domain\Services\Metering\UsageReading;
use Onhost\Domain\Services\Metering\UsageRecorder;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\ServiceUsageSample;
use Onhost\Domain\Services\PlanFit;
use Onhost\Domain\Services\ServiceHealthCheck;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\UsageGuard;
use Onhost\Domain\Services\UsageWatch;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Providers\AaPanel\AaPanelWebProvider;
use Onhost\Providers\Shell\ScriptedShell;

/*
 * Metering phase 2 (owner decisions 9 and 12, TASK-0023 metering-core): every reading is kept as a sample, a number the
 * panel could not give is stored as nothing — never as 0 — and the watch can rotate over every service instead of the
 * first 200. A first reading is only a baseline: nothing is blocked or ordered on it. New metrics are observed until the
 * operator turns them on, soft limits only ever tell the customer, and no sample is ever billed.
 */

beforeEach(fn () => Http::preventStrayRequests());
afterEach(function () {
    AaPanelWebProvider::$shellFactory = null;
    UsageMetrics::$kindOverrides = [];
});

/**
 * ISPConfig lab answering the quota functions from a variable the test moves (fakes stack, so one fake per test).
 *
 * @param  array{quota: array{used:int, hard:int, files:int}|string}  $state  `quota` = 'fault' makes the panel refuse
 */
function usageSamplesIspFake(array &$state): void
{
    Http::fake(function ($request) use (&$state) {
        if (! str_starts_with($request->url(), ISP)) {
            return null;
        }
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        if (in_array($function, ['quota_get_by_user', 'trafficquota_get_by_user'], true) && $state['quota'] === 'fault') {
            return Http::response(['code' => 'remote_fault', 'message' => 'You do not have the permissions to access this function.', 'response' => false]);
        }
        $quota = is_array($state['quota']) ? $state['quota'] : ['used' => 0, 'hard' => 0, 'files' => 0];
        $answer = match ($function) {
            'login' => 'sess-samples',
            // domain 7 is the lab site; 100+ are the further services usageSamplesWebSites() creates
            'quota_get_by_user' => array_map(fn (int $id) => ['domain_id' => $id, 'domain' => 'shop.cz', 'used' => $quota['used'], 'hard' => $quota['hard'], 'soft' => $quota['hard'], 'files' => $quota['files']], [7, 100, 101, 102]),
            'server_get_php_versions' => ['8.3'],
            default => false,
        };

        return Http::response(['code' => 'ok', 'message' => '', 'response' => $answer]);
    });
}

/** Several ISPConfig web services of one organization, each with its own site id (the lab helper gives every one site 7). @return list<Service> */
function usageSamplesWebSites(Organization $org, int $count): array
{
    $out = [];
    for ($i = 0; $i < $count; $i++) {
        $service = featureWebService($org, 'ispconfig');
        ProviderBinding::query()->where('service_id', $service->id)->update(['remote_id' => (string) (100 + $i)]);
        $out[] = $service;
    }

    return $out;
}

/** aaPanel lab where the node says the site stores `$gb` GB (du) and 1200 files. */
function usageSamplesAaSite(int $gb): void
{
    AaPanelWebProvider::$shellFactory = fn () => new ScriptedShell(['/du -sb/' => [0, (string) ($gb * 1024 ** 3)."\n1200\n"]]);
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok', 'data' => [], 'page' => '']));
}

/** A VPS on the Proxmox lab; Proxmox reports memory but never a used disk. */
function usageSamplesVps(Organization $org): Service
{
    $instance = pveLab();
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'VPS', 'hostname' => 'vps.test', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id,
        'entitlements' => ['nvme_gb' => 40, 'ram_mb' => 4096, 'vcpu' => 2], 'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'], 'sla_class' => 'standard', 'activated_at' => now()->subMonth(), 'tags' => []]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => 'prg1-n2', 'meta' => [], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => 'samples-vps-'.$service->id, 'adapter_version' => '1.0.0']);

    return $service;
}

/** The game panel's live resources for server e4c1abcd. */
function usageSamplesPteroFake(int $memBytes, int $diskBytes): void
{
    Http::fake(function ($request) use ($memBytes, $diskBytes) {
        if (! str_starts_with($request->url(), PTERO)) {
            return null;
        }

        return str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/servers/e4c1abcd/resources')
            ? Http::response(['object' => 'stats', 'attributes' => ['current_state' => 'running', 'is_suspended' => false, 'resources' => ['memory_bytes' => $memBytes, 'cpu_absolute' => 5.0, 'disk_bytes' => $diskBytes, 'network_rx_bytes' => 0, 'network_tx_bytes' => 0, 'uptime' => 1000]]])
            : Http::response(['errors' => [['code' => 'NotFound']]], 404);
    });
}

function usageSamplesOf(Service $service, string $metric): ?ServiceUsageSample
{
    return ServiceUsageSample::query()->where('service_id', $service->id)->where('metric', $metric)->where('granularity', ServiceUsageSample::GRANULARITY_SAMPLE)->orderByDesc('window_start')->first();
}

it('records null, never 0, when the panel cannot say', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $state = ['quota' => 'fault'];
    usageSamplesIspFake($state);

    app(UsageWatch::class)->run();

    $disk = usageSamplesOf($service, 'disk');
    expect($disk)->not->toBeNull()->and($disk->value)->toBeNull()->and($disk->quality)->toBe(UsageReading::UNAVAILABLE)
        ->and($disk->limit_value)->toBe(50 * 1024 ** 3)->and($disk->reason)->toBe('not_reported');
    $usage = (array) data_get($service->fresh()->tags, 'usage');
    expect($usage['level'])->toBe('unknown')->and($usage['metrics'])->toBe([])->and(array_keys($usage['unavailable']))->toBe(['disk'])
        ->and(OutboxMessage::query()->where('name', 'service.usage.high')->count())->toBe(0)
        ->and(collect(app(ServiceHealthCheck::class)->run($service->fresh())['findings'])->pluck('key')->all())->not->toContain('usage');
});

it('the disk a VPS never reports is unavailable, not 0 %', function () {
    [, $org] = $this->customerWithOrganization();
    $service = usageSamplesVps($org);
    Http::fake([PVE.'/nodes/prg1-n2/qemu/1042/status/current' => Http::response(['data' => ['status' => 'running', 'mem' => 2 * 1024 ** 3, 'maxmem' => 4 * 1024 ** 3, 'maxdisk' => 40 * 1024 ** 3, 'cpu' => 0.1]])]);

    app(UsageWatch::class)->run();

    expect(usageSamplesOf($service, 'disk')->value)->toBeNull()
        ->and(usageSamplesOf($service, 'memory')->value)->toBe(2 * 1024 ** 3)->and(usageSamplesOf($service, 'memory')->quality)->toBe(UsageReading::MEASURED);
    $usage = (array) data_get($service->fresh()->tags, 'usage');
    expect(array_keys($usage['metrics']))->toBe(['memory'])->and($usage['metrics']['memory']['pct'])->toBe(50)->and(array_keys($usage['unavailable']))->toBe(['disk']);
});

it('keeps what has no limit as an observed sample', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $service->forceFill(['entitlements' => array_replace((array) $service->entitlements, ['inodes' => 0])])->save();
    $state = ['quota' => ['used' => 5 * 1024 * 1024, 'hard' => 50 * 1024 * 1024, 'files' => 1200]];
    usageSamplesIspFake($state);

    app(UsageWatch::class)->run();

    $inodes = usageSamplesOf($service, 'inodes');
    expect($inodes->value)->toBe(1200)->and($inodes->limit_value)->toBeNull()->and($inodes->limit_kind)->toBe('hard');
    $usage = (array) data_get($service->fresh()->tags, 'usage');
    expect($usage['metrics'])->toHaveKey('disk')->not->toHaveKey('inodes')->and($usage['observed'])->toBe(['inodes' => 1200]);
});

it('with rotation on, visits every service, not the first N', function () {
    [, $org] = $this->customerWithOrganization();
    $services = usageSamplesWebSites($org, 3);
    $state = ['quota' => ['used' => 1024, 'hard' => 50 * 1024 * 1024, 'files' => 10]];
    usageSamplesIspFake($state);
    app(AutomationLedger::class)->setEnabled('usage.rotation', true, 'test');
    $watch = app(UsageWatch::class);

    $watch->run(2);
    $checked = fn () => Service::query()->whereIn('id', array_map(fn (Service $s) => $s->id, $services))->whereNotNull('usage_checked_at')->count();
    expect($checked())->toBe(2)->and(Service::query()->findOrFail($services[2]->id)->usage_checked_at)->toBeNull();

    $this->travel(1)->hours();
    $second = $watch->run(2);
    expect($checked())->toBe(3)->and($second['checked'])->toBe(2)
        ->and(Service::query()->findOrFail($services[2]->id)->usage_checked_at)->not->toBeNull(); // the one never visited went first
});

it('with rotation on, a small limit is no cap: every service is visited within a day of hourly runs', function () {
    [, $org] = $this->customerWithOrganization();
    usageSamplesWebSites($org, 25);
    $state = ['quota' => ['used' => 1024, 'hard' => 50 * 1024 * 1024, 'files' => 10]];
    usageSamplesIspFake($state);
    app(AutomationLedger::class)->setEnabled('usage.rotation', true, 'test');

    // 25 services over UsageWatch::ROTATION_HOURS runs: at least two a run, whatever the limit says
    expect(app(UsageWatch::class)->run(1))->toMatchArray(['checked' => 2, 'rotation' => 1]);
    for ($run = 1; $run < 13; $run++) {
        $this->travel(1)->hours();
        app(UsageWatch::class)->run(1);
    }
    expect(Service::query()->whereNull('usage_checked_at')->count())->toBe(0)
        ->and(app(UsageWatch::class)->run(1)['lag_hours'])->toBeLessThanOrEqual(UsageWatch::ROTATION_HOURS);
});

it('with rotation off (the default), visits exactly the services it visited before', function () {
    [, $org] = $this->customerWithOrganization();
    $database = featureGameService($org);
    $database->forceFill(['family' => 'data'])->save(); // a managed database: never visited while rotation is off
    $services = usageSamplesWebSites($org, 3);
    $state = ['quota' => ['used' => 1024, 'hard' => 50 * 1024 * 1024, 'files' => 10]];
    usageSamplesIspFake($state);
    expect(app(AutomationLedger::class)->enabled('usage.rotation'))->toBeFalse();

    $watch = app(UsageWatch::class);
    $watch->run(2);
    $this->travel(1)->hours();
    $watch->run(2);

    $measured = ServiceUsageSample::query()->distinct()->pluck('service_id')->sort()->values()->all();
    expect($measured)->toBe(collect([$services[0]->id, $services[1]->id])->sort()->values()->all())
        ->and(Service::query()->findOrFail($services[2]->id)->usage_checked_at)->toBeNull()
        ->and(Service::query()->findOrFail($database->id)->usage_checked_at)->toBeNull();
});

it('a first reading is only a baseline: no automatic upgrade until a second critical reading', function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    [$owner, $org] = $this->customerWithOrganization([], ['street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000', 'billing_email' => 'billing@example.cz']);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'card', 'seed', $this->contextFor($owner, $org), bankProvider: 'comgate');
    $service = featureWebService($org, 'ispconfig');
    $start = app(CatalogService::class)->resolve('web-hosting', 'start', 'CZK', 'month');
    $service->forceFill(['plan_version_id' => $start['version']->id, 'entitlements' => $start['version']->entitlements, 'tags' => ['policy' => ['auto_upgrade' => true]]])->save();
    $subscription = Subscription::query()->create([
        'organization_id' => $org->id, 'service_id' => $service->id, 'plan_version_id' => $start['version']->id, 'price_id' => $start['price']->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => $start['price']->renewalAmount()->minor,
        'state' => Subscription::ACTIVE, 'current_period_start' => now()->subDays(15), 'current_period_end' => now()->addDays(15), 'next_renewal_at' => now()->addDays(8), 'auto_renew' => true, 'renewal_priority' => 'normal',
    ]);
    $service->forceFill(['subscription_id' => $subscription->id])->save();
    $limitKb = 10 * 1024 * 1024;
    $state = ['quota' => ['used' => (int) ($limitKb * 0.96), 'hard' => $limitKb, 'files' => 1200]];
    usageSamplesIspFake($state);
    $watch = app(UsageWatch::class);

    // the first reading ever says 96 %: the customer hears it, nothing is ordered on one number
    expect($watch->run())->toMatchArray(['critical' => 1, 'upgraded' => 0]);
    expect(Order::query()->where('organization_id', $org->id)->count())->toBe(0)
        ->and(data_get($service->fresh()->tags, 'usage.baseline'))->toContain('disk');

    // the next day it is still 96 %: two readings in a row, the upgrade the customer allowed is ordered
    $this->travel(1)->days();
    expect($watch->run())->toMatchArray(['upgraded' => 1]);
    expect(Order::query()->where('organization_id', $org->id)->where('source', 'auto')->count())->toBe(1);
});

it('the first full reading does not block growth; the second does', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    usageSamplesAaSite(52); // of the plan's 50 GB
    $watch = app(UsageWatch::class);
    $ctx = $this->contextFor($user, $org);

    $watch->run();
    $service->refresh();
    expect(data_get($service->tags, 'usage.level'))->toBe('full')->and(data_get($service->tags, 'usage.baseline'))->toContain('disk')
        ->and(UsageGuard::full($service))->toBeNull();
    expect(fn () => app(ServiceService::class)->requestAction($service, 'file.save', $ctx, 'samples-full-1', ['path' => 'a.txt', 'content' => 'x']))
        ->not->toThrow(DomainError::class, 'zaplněno');

    $this->travel(1)->hours();
    $watch->run();
    $service->refresh();
    expect(data_get($service->tags, 'usage.baseline'))->toBe([])->and(UsageGuard::full($service))->not->toBeNull();
    expect(fn () => app(ServiceService::class)->requestAction($service, 'file.save', $ctx, 'samples-full-2', ['path' => 'b.txt', 'content' => 'x']))
        ->toThrow(DomainError::class, 'zaplněno');
});

it('a new metric (managed database) is observed and neither notifies nor blocks until enforce_new_metrics', function () {
    [, $org] = $this->customerWithOrganization();
    $database = featureGameService($org);
    $database->forceFill(['family' => 'data'])->save();
    usageSamplesPteroFake((int) (8192 * 1024 ** 2 * 0.99), 5 * 1024 ** 3);
    app(AutomationLedger::class)->setEnabled('usage.rotation', true, 'test');
    $watch = app(UsageWatch::class);

    $watch->run();
    expect(usageSamplesOf($database, 'memory')->value)->toBe((int) (8192 * 1024 ** 2 * 0.99))
        ->and(data_get($database->fresh()->tags, 'usage.metrics.memory'))->toBeNull()
        ->and(OutboxMessage::query()->where('name', 'service.usage.high')->count())->toBe(0);

    config(['onhost.metering.enforce_new_metrics' => true]);
    $this->travel(1)->hours();
    $watch->run();
    expect(data_get($database->fresh()->tags, 'usage.metrics.memory.pct'))->toBe(99)
        ->and(OutboxMessage::query()->where('name', 'service.usage.high')->count())->toBe(1);
});

it('soft limits only ever tell the customer: they never block or order a plan', function () {
    foreach (UsageGuard::STORAGE_METRICS as $key) {
        expect(UsageMetrics::limitKind($key))->toBe('hard'); // what blocks today is hard, straight from the MetricRegistry
    }
    UsageMetrics::$kindOverrides = ['disk' => 'soft'];
    expect(UsageMetrics::notifies('disk', 'web'))->toBeTrue()->and(UsageMetrics::drivesConsequences('disk', 'web'))->toBeFalse()->and(UsageMetrics::isSoft('disk'))->toBeTrue();

    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $limit = 50 * 1024 ** 3;
    $service->forceFill(['tags' => ['usage' => ['level' => 'full', 'checked_at' => now()->toIso8601String(), 'metrics' => ['disk' => ['used' => (int) ($limit * 1.1), 'limit' => $limit, 'pct' => 110]]]]])->save();
    expect(UsageGuard::full($service->refresh()))->toBeNull();

    UsageMetrics::$kindOverrides = [];
    expect(UsageGuard::full($service))->not->toBeNull(); // the same numbers under a hard limit (no baseline list: written before this change) block
});

it('samples never reach billing', function () {
    [, $org] = $this->customerWithOrganization();
    featureWebService($org, 'aapanel');
    usageSamplesAaSite(20);

    app(UsageWatch::class)->run();

    expect(ServiceUsageSample::query()->count())->toBeGreaterThan(0)
        ->and(UsageEvent::query()->count())->toBe(0)
        ->and(app(RatingService::class)->rate()['rated'])->toBe(0);
});

it('the preview writes nothing and says what switching rotation on would do', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $limit = 50 * 1024 ** 3;
    $service->forceFill(['tags' => ['usage' => ['level' => 'full', 'checked_at' => now()->subHour()->toIso8601String(), 'metrics' => ['disk' => ['used' => (int) ($limit * 1.04), 'limit' => $limit, 'pct' => 104]]]]])->save();
    $tags = $service->refresh()->tags;
    usageSamplesAaSite(52);
    $events = OutboxMessage::query()->count(); // signing up the customer published its own

    expect(Artisan::call('onhost:metering:preview', ['--json' => true]))->toBe(0);
    $out = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($out)->toMatchArray(['checked' => 1, 'never_measured' => 1, 'full' => 1, 'would_block' => 1, 'would_auto_upgrade' => 0, 'errors' => 0])
        ->and(ServiceUsageSample::query()->count())->toBe(0)
        ->and($service->fresh()->usage_checked_at)->toBeNull()
        ->and($service->fresh()->tags)->toBe($tags)
        ->and(OutboxMessage::query()->count())->toBe($events)
        ->and(app(AutomationLedger::class)->last('usage.watch'))->toBeNull();
});

it('usage.rotation is off by default and switchable by staff; the rollup and prune rules are listed', function () {
    $ledger = app(AutomationLedger::class);
    expect($ledger->enabled('usage.rotation'))->toBeFalse()
        ->and($ledger->rule('metering.rollup')['command'])->toBe('onhost:metering:rollup')
        ->and($ledger->rule('metering.prune')['command'])->toBe('onhost:metering:prune');

    $this->actingAs($this->staff('platform_owner'), 'sanctum');
    $this->putJson('/v1/staff/automation/usage.rotation', ['enabled' => true, 'reason' => 'preview reviewed'])->assertOk()->assertJsonPath('enabled', true);
    expect(app(AutomationLedger::class)->enabled('usage.rotation'))->toBeTrue();
});

it('does not read a never-measured disk as zero when a smaller plan is asked for', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel'); // sells 50 GB, never measured
    $smaller = ['sites' => 3, 'nvme_gb' => 10];
    $keys = fn () => array_column(app(PlanFit::class)->shortfalls($service->fresh(), $smaller), 'key');

    expect($keys())->not->toContain('disk_used')->not->toContain('disk_unmeasured');
    config(['onhost.metering.enforce_new_metrics' => true]);
    expect($keys())->toContain('disk_unmeasured')
        ->and(array_column(app(PlanFit::class)->shortfalls($service->fresh(), ['sites' => 3, 'nvme_gb' => 100]), 'key'))->not->toContain('disk_unmeasured'); // a bigger plan never waits for a number
    config(['onhost.metering.enforce_new_metrics' => false]);

    // known only from a sample (the tags carry nothing): it counts
    app(UsageRecorder::class)->record($service, [UsageReading::measured('disk', 30 * 1024 ** 3, 50 * 1024 ** 3, 'test')], now());
    expect($keys())->toContain('disk_used');
});

it('calls a service that used up its plan bad, not fine', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $limit = 50 * 1024 ** 3;
    $service->forceFill(['tags' => ['usage' => ['level' => 'full', 'checked_at' => now()->toIso8601String(), 'metrics' => ['disk' => ['used' => (int) ($limit * 1.02), 'limit' => $limit, 'pct' => 102]]]]])->save();

    $finding = collect(app(ServiceHealthCheck::class)->run($service->fresh())['findings'])->firstWhere('key', 'usage');
    expect($finding['level'])->toBe('bad')->and($finding['cs'])->toContain('vyčerpala')->and($finding['en'])->toContain('used up');
});
