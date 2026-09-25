<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Metering\WebDiskTotal;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\ServiceUsageSample;
use Onhost\Domain\Services\Models\StagingLink;
use Onhost\Domain\Services\PlanFit;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\UsageGuard;
use Onhost\Domain\Services\UsageWatch;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Providers\AaPanel\AaPanelWebProvider;
use Onhost\Providers\Shell\ScriptedShell;

/*
 * A web plan sells its space as "files, databases and mail together" (web-custom configurator), and only the site's files
 * were ever measured (TASK-0023 web-disk-total, owner decision 10). The platform now adds the three up — for the paying
 * service and the further sites it carries, never its test copy — and shows the total straight away. It counts against
 * the plan only from a date the operator sets, and only for a service that was told in time (or was ordered after it).
 * Before that date nothing a customer sees in their limits changes.
 */

beforeEach(fn () => Http::preventStrayRequests());
afterEach(fn () => AaPanelWebProvider::$shellFactory = null);

defined('WDT_GB') || define('WDT_GB', 1024 ** 3);

/**
 * An ISPConfig lab for client 3: files per site (`quota`, GB by domain id), the databases ISPConfig lists under each site
 * (`dbs`), their sizes (`sizes`, or 'fault'), and the mailboxes of the client (`mail`, address => GB).
 *
 * @param  array<string, mixed>  $state
 */
function wdtIspFake(array &$state): void
{
    Http::fake(function ($request) use (&$state) {
        if (! str_starts_with($request->url(), ISP)) {
            return null;
        }
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $body = (array) $request->data();
        $state['calls'][] = $function;
        if ($function === 'databasequota_get_by_user' && ($state['sizes'] ?? []) === 'fault') {
            return Http::response(['code' => 'remote_fault', 'message' => 'You do not have the permissions to access this function.', 'response' => false]);
        }
        $answer = match ($function) {
            'login' => 'sess-wdt',
            'quota_get_by_user' => array_map(fn (int $id, int|float $gb) => ['domain_id' => $id, 'used' => (int) ($gb * WDT_GB / 1024), 'hard' => 0, 'soft' => 0, 'files' => 100], array_keys($state['quota']), $state['quota']),
            'trafficquota_get_by_user' => [],
            'sites_database_get' => array_map(fn (string $name) => ['database_id' => crc32($name), 'database_name' => $name, 'parent_domain_id' => (int) data_get($body, 'primary_id.parent_domain_id')],
                $state['dbs'][(int) data_get($body, 'primary_id.parent_domain_id')] ?? []),
            'databasequota_get_by_user' => array_map(fn (string $name, int|float $gb) => ['database_name' => $name, 'used_raw' => (int) ($gb * WDT_GB), 'used' => (int) ($gb * WDT_GB)], array_keys($state['sizes']), $state['sizes']),
            'mailquota_get_by_user' => array_map(fn (string $email, int|float $gb) => ['email' => $email, 'used' => (int) ($gb * WDT_GB), 'quota' => 10 * WDT_GB], array_keys($state['mail']), $state['mail']),
            default => false,
        };

        return Http::response(['code' => 'ok', 'message' => '', 'response' => $answer]);
    });
}

/** The web hosting's mail domain for a name, as `MailDomains` finds it. */
function wdtMailDomain(Service $service, string $domain, int $remoteId): void
{
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $service->provider_instance_id, 'remote_type' => 'mail_domain', 'remote_id' => (string) $remoteId, 'remote_node' => '1',
        'meta' => ['domain' => $domain, 'client_id' => 3], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "wdt-md:{$service->id}:{$domain}", 'adapter_version' => '1.0.0']);
}

/** A further site (or, with `$staging`, the test copy) the owner's plan carries on ISPConfig domain `$domainId`. */
function wdtIncludedSite(Service $owner, string $domain, int $domainId, bool $staging = false): Service
{
    $site = Service::query()->create([
        'organization_id' => $owner->organization_id, 'product_key' => $owner->product_key, 'family' => $owner->family, 'name' => $domain, 'state' => ServiceStateMachine::ACTIVE, 'region_code' => $owner->region_code,
        'provider_instance_id' => $owner->provider_instance_id, 'node_id' => $owner->node_id, 'entitlements' => array_replace((array) $owner->entitlements, ['sites' => 1, 'nvme_gb' => 10]), 'sla_class' => 'standard', 'activated_at' => now(),
        'tags' => ['parent_service_id' => $owner->id, 'billing' => 'included'] + ($staging ? ['staging_of' => $owner->id] : []),
        'desired_spec' => ['executor' => 'ispconfig', 'family' => 'web', 'domain' => $domain, 'php_version' => '8.3'], 'hostname' => $domain, 'health' => [],
    ]);
    ProviderBinding::query()->create(['service_id' => $site->id, 'provider_instance_id' => $owner->provider_instance_id, 'remote_type' => 'web_domain', 'remote_id' => (string) $domainId, 'remote_node' => '1',
        'meta' => ['client_id' => 3, 'system_user' => 'web'.$domainId], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => 'wdt-site:'.$site->id, 'adapter_version' => '1.0.0']);
    if ($staging) {
        StagingLink::query()->create(['service_id' => $owner->id, 'staging_service_id' => $site->id, 'organization_id' => $owner->organization_id, 'staging_domain' => $domain, 'state' => 'ready', 'databases' => [], 'meta' => []]);
    }

    return $site->refresh();
}

/** The standard lab: 50 GB plan, files 25 GB (50 %), one database of 20 GB, mail 5 + 5 GB in two domains → 55 GB, 110 %. @return array{0:Service, 1:array<string,mixed>} */
function wdtOwner(Organization $org): array
{
    $owner = featureWebService($org, 'ispconfig');
    wdtMailDomain($owner, 'shop.cz', 900);
    wdtMailDomain($owner, 'shop.sk', 901);
    $state = ['calls' => [], 'quota' => [7 => 25], 'dbs' => [7 => ['c3shop']], 'sizes' => ['c3shop' => 20, 'c3legacy' => 30],
        'mail' => ['info@shop.cz' => 5, 'eshop@shop.sk' => 5, 'boss@legacy-client.cz' => 9]];

    return [$owner, $state];
}

/** An aaPanel web hosting of its own (the lab helper gives every aaPanel site id 41). */
function wdtAaHosting(Organization $org): Service
{
    $service = featureWebService($org, 'aapanel');
    ProviderBinding::query()->where('service_id', $service->id)->update(['remote_id' => 'w'.$service->id]);

    return $service->refresh();
}

/** Sets the enforcement date and the notice the service got. */
function wdtEnforce(?string $from, ?Service $noticed = null, ?string $sentAt = null): void
{
    config(['onhost.metering.web_disk_total.enforce_from' => $from, 'onhost.metering.web_disk_total.notice_min_days' => 30]);
    if ($noticed !== null) {
        $noticed->forceFill(['tags' => array_merge((array) $noticed->tags, ['usage_notices' => ['disk_total' => ['sent_at' => $sentAt, 'effective' => $from]]])])->save();
    }
}

it('sums files, databases and mail into tags.usage.disk_total while the metrics and the level stay exactly as before', function () {
    [, $org] = $this->customerWithOrganization();
    [$owner, $state] = wdtOwner($org);
    wdtIspFake($state);

    app(UsageWatch::class)->run();

    $usage = (array) data_get($owner->fresh()->tags, 'usage');
    expect($usage['level'])->toBe('ok')->and(array_keys($usage['metrics']))->toBe(['disk'])->and($usage['metrics']['disk']['pct'])->toBe(50)
        ->and($usage['disk_total'])->toMatchArray(['files' => 25 * WDT_GB, 'databases' => 20 * WDT_GB, 'mail' => 10 * WDT_GB, 'total' => 55 * WDT_GB, 'limit' => 50 * WDT_GB, 'pct' => 110, 'quality' => 'measured', 'unavailable' => []])
        ->and(OutboxMessage::query()->where('name', 'service.usage.high')->count())->toBe(0);

    // the total and its parts are kept as samples too, and before the date the total limits nothing
    $total = ServiceUsageSample::query()->where('service_id', $owner->id)->where('metric', 'disk_total')->first();
    expect($total->value)->toBe(55 * WDT_GB)->and($total->limit_value)->toBe(50 * WDT_GB)->and($total->limit_kind)->toBe('none')
        ->and(ServiceUsageSample::query()->where('service_id', $owner->id)->where('metric', 'disk_databases')->value('value'))->toBe(20 * WDT_GB)
        ->and(ServiceUsageSample::query()->where('service_id', $owner->id)->where('metric', 'disk_mail')->value('value'))->toBe(10 * WDT_GB);
});

it('counts no database of another site of the same ISPConfig client', function () {
    [, $org] = $this->customerWithOrganization();
    [$owner, $state] = wdtOwner($org);
    wdtIspFake($state);

    $total = app(WebDiskTotal::class)->measure($owner);

    // c3legacy (30 GB) belongs to the same client but not to this site: ISPConfig does not list it under domain 7
    expect($total['databases'])->toBe(20 * WDT_GB)->and($total['parts'][0]['databases'])->toBe(20 * WDT_GB);
});

it('counts mail across every mail domain of the service, not only the first', function () {
    [, $org] = $this->customerWithOrganization();
    [$owner, $state] = wdtOwner($org);
    wdtIspFake($state);

    $total = app(WebDiskTotal::class)->measure($owner);

    // shop.cz and shop.sk together; the client's mailbox in a domain this service does not host is not counted
    expect($total['mail'])->toBe(10 * WDT_GB);
});

it('keeps a component the panel refused as null with a reason and calls the total partial, never 0', function () {
    [, $org] = $this->customerWithOrganization();
    [$owner, $state] = wdtOwner($org);
    $state['sizes'] = 'fault';
    wdtIspFake($state);

    app(UsageWatch::class)->run();

    $total = (array) data_get($owner->fresh()->tags, 'usage.disk_total');
    expect($total['databases'])->toBeNull()->and($total['unavailable'])->toHaveKey('databases')->and($total['unavailable']['databases'])->toStartWith('provider_')
        ->and($total['total'])->toBe(35 * WDT_GB)->and($total['quality'])->toBe('partial')
        ->and(ServiceUsageSample::query()->where('service_id', $owner->id)->where('metric', 'disk_databases')->value('quality'))->toBe('unavailable')
        ->and(ServiceUsageSample::query()->where('service_id', $owner->id)->where('metric', 'disk_databases')->value('value'))->toBeNull();
});

it('says an aaPanel web hosting reports no database size, and counts no mail it does not host', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    AaPanelWebProvider::$shellFactory = fn () => new ScriptedShell(['/du -sb/' => [0, (string) (12 * WDT_GB)."\n1200\n"]]);
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok', 'data' => [], 'page' => '']));

    $total = app(WebDiskTotal::class)->measure($service);

    expect($total)->toMatchArray(['files' => 12 * WDT_GB, 'databases' => null, 'mail' => 0, 'total' => 12 * WDT_GB, 'quality' => 'partial'])
        ->and($total['unavailable'])->toBe(['databases' => 'panel_reports_no_database_size']);
});

it('counts an included site toward its owner\'s total and shows a test copy apart without counting it', function () {
    [, $org] = $this->customerWithOrganization();
    [$owner, $state] = wdtOwner($org);
    $blog = wdtIncludedSite($owner, 'blog.shop.cz', 8);
    $staging = wdtIncludedSite($owner, 'shop-staging.web.onhost.cz', 9, true);
    $state['quota'] = [7 => 10, 8 => 5, 9 => 7];
    $state['dbs'] = [7 => ['c3shop'], 8 => ['c3blog'], 9 => ['c3stage']];
    $state['sizes'] = ['c3shop' => 1, 'c3blog' => 2, 'c3stage' => 3];
    $state['mail'] = [];
    wdtIspFake($state);

    app(UsageWatch::class)->run();

    $total = (array) data_get($owner->fresh()->tags, 'usage.disk_total');
    expect($total['files'])->toBe(15 * WDT_GB)->and($total['databases'])->toBe(3 * WDT_GB)->and($total['total'])->toBe(18 * WDT_GB)
        ->and(collect($total['parts'])->pluck('service_id')->all())->toBe([$owner->id, $blog->id])
        ->and($total['staging'])->toHaveCount(1)->and($total['staging'][0])->toMatchArray(['service_id' => $staging->id, 'files' => 7 * WDT_GB])
        // an included site has no total of its own: the plan's space is its owner's
        ->and(data_get($blog->fresh()->tags, 'usage.disk_total'))->toBeNull();
});

it('refuses nothing and tells nobody about the total while no enforcement date is set', function () {
    [, $org] = $this->customerWithOrganization();
    [$owner, $state] = wdtOwner($org);
    $state['sizes'] = ['c3shop' => 30]; // 25 + 30 + 10 = 65 GB of 50: 130 %
    wdtEnforce(null);
    wdtIspFake($state);

    app(UsageWatch::class)->run();
    app(UsageWatch::class)->run();

    $owner->refresh();
    expect(data_get($owner->tags, 'usage.disk_total.pct'))->toBe(130)->and(WebDiskTotal::enforcedFor($owner))->toBeFalse()
        ->and(UsageGuard::full($owner))->toBeNull()
        ->and(OutboxMessage::query()->where('name', 'service.usage.high')->count())->toBe(0);
    UsageGuard::assertRoomFor($owner, 'file.save'); // does not throw
});

it('refuses growth of a noticed service over its total after the date, and leaves the ways out open', function () {
    [, $org] = $this->customerWithOrganization();
    [$owner, $state] = wdtOwner($org);
    wdtEnforce(now()->subDay()->toDateString(), $owner, now()->subDays(40)->toIso8601String());
    wdtIspFake($state);

    app(UsageWatch::class)->run(); // the first reading of the total is only a baseline
    expect(UsageGuard::full($owner->fresh()))->toBeNull();
    app(UsageWatch::class)->run();

    $owner->refresh();
    expect(data_get($owner->tags, 'usage.metrics.disk_total.pct'))->toBe(110)->and(data_get($owner->tags, 'usage.level'))->toBe('full')
        ->and(UsageGuard::full($owner))->toMatchArray(['key' => 'disk_total', 'pct' => 110]);
    expect(fn () => UsageGuard::assertRoomFor($owner, 'file.save'))->toThrow(DomainError::class, 'soubory + databáze + pošta');
    foreach (['file.delete', 'database.delete', 'backup', 'restore', 'plan.change'] as $wayOut) {
        UsageGuard::assertRoomFor($owner, $wayOut); // making room and changing the plan are never refused
    }
    $high = OutboxMessage::query()->where('name', 'service.usage.high')->first();
    expect(data_get($high->payload, 'top.key'))->toBe('disk_total')
        ->and(UsageWatch::metricLabel('disk_total'))->toBe('prostor tarifu celkem (soubory, databáze, pošta)');
});

it('keeps files-only enforcement for a service that was never told, or told too late, and enforces one ordered after the date', function () {
    [, $org] = $this->customerWithOrganization();
    $from = now()->subDay()->toDateString();
    $old = wdtAaHosting($org);
    $old->forceFill(['created_at' => now()->subYear()])->save();
    $late = wdtAaHosting($org);
    $late->forceFill(['created_at' => now()->subYear()])->save();
    wdtEnforce($from, $late, now()->subDays(10)->toIso8601String()); // 9 days before the date: not in time
    $new = wdtAaHosting($org); // ordered today, after the date

    expect(WebDiskTotal::enforcedFor($old->fresh()))->toBeFalse()
        ->and(WebDiskTotal::enforcedFor($late->fresh()))->toBeFalse()
        ->and(WebDiskTotal::enforcedFor($new->fresh()))->toBeTrue()
        ->and(WebDiskTotal::enforcementDate($new->fresh()))->toBe($from);

    // a notice for another date does not count either
    wdtEnforce($from);
    $old->forceFill(['tags' => ['usage_notices' => ['disk_total' => ['sent_at' => now()->subDays(60)->toIso8601String(), 'effective' => now()->addMonth()->toDateString()]]]])->save();
    expect(WebDiskTotal::enforcedFor($old->fresh()))->toBeFalse();

    // before the date nothing is enforced even for a service that was told
    wdtEnforce(now()->addDays(5)->toDateString(), $old, now()->subDays(40)->toIso8601String());
    expect(WebDiskTotal::enforcedFor($old->fresh()))->toBeFalse()->and(WebDiskTotal::enforcementDate($old->fresh()))->toBe(now()->addDays(5)->toDateString());
});

it('enforces a partial total only when the measured part alone is over the limit', function () {
    $limit = 50 * WDT_GB;
    $partialUnder = ['total' => 40 * WDT_GB, 'limit' => $limit, 'pct' => 80, 'quality' => 'partial'];
    $partialOver = ['total' => 60 * WDT_GB, 'limit' => $limit, 'pct' => 120, 'quality' => 'partial'];
    $measured = ['total' => 40 * WDT_GB, 'limit' => $limit, 'pct' => 80, 'quality' => 'measured'];

    expect(WebDiskTotal::isFull($partialUnder))->toBeFalse()->and(WebDiskTotal::metricOf($partialUnder))->toBeNull()
        ->and(WebDiskTotal::isFull($partialOver))->toBeTrue()->and(WebDiskTotal::metricOf($partialOver))->toBe(['used' => 60 * WDT_GB, 'limit' => $limit, 'pct' => 120])
        ->and(WebDiskTotal::metricOf($measured))->toBe(['used' => 40 * WDT_GB, 'limit' => $limit, 'pct' => 80])
        ->and(WebDiskTotal::metricOf(['total' => null, 'limit' => $limit, 'pct' => null, 'quality' => 'unavailable']))->toBeNull();
});

it('refuses growth of an included site when its owner\'s enforced total is full, and not on a stale total', function () {
    [, $org] = $this->customerWithOrganization();
    [$owner] = wdtOwner($org);
    $blog = wdtIncludedSite($owner, 'blog.shop.cz', 8);
    wdtEnforce(now()->subDay()->toDateString(), $owner, now()->subDays(40)->toIso8601String());
    $full = ['files' => 40 * WDT_GB, 'databases' => 12 * WDT_GB, 'mail' => 0, 'total' => 52 * WDT_GB, 'limit' => 50 * WDT_GB, 'pct' => 104, 'quality' => 'measured', 'unavailable' => [], 'checked_at' => now()->toIso8601String()];
    $owner->forceFill(['tags' => array_merge((array) $owner->tags, ['usage' => ['level' => 'full', 'metrics' => [], 'checked_at' => now()->toIso8601String(), 'disk_total' => $full]])])->save();

    expect(WebDiskTotal::enforcedFor($blog->fresh()))->toBeTrue();
    expect(fn () => UsageGuard::assertRoomFor($blog->fresh(), 'database.create'))->toThrow(DomainError::class, 'soubory + databáze + pošta');
    UsageGuard::assertRoomFor($blog->fresh(), 'database.delete');

    $owner->forceFill(['tags' => array_merge((array) $owner->tags, ['usage' => ['disk_total' => array_merge($full, ['checked_at' => now()->subHours(72)->toIso8601String()])]])])->save();
    UsageGuard::assertRoomFor($blog->fresh(), 'database.create'); // three days old: not acted on
});

it('checks a smaller plan against the total only once the total is enforced for the service', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $service->forceFill(['created_at' => now()->subYear(), 'tags' => ['usage' => ['level' => 'ok', 'checked_at' => now()->toIso8601String(), 'metrics' => ['disk' => ['used' => 10 * WDT_GB, 'limit' => 50 * WDT_GB, 'pct' => 20]],
        'disk_total' => ['files' => 10 * WDT_GB, 'databases' => 20 * WDT_GB, 'mail' => 0, 'total' => 30 * WDT_GB, 'limit' => 50 * WDT_GB, 'pct' => 60, 'quality' => 'measured', 'unavailable' => [], 'checked_at' => now()->toIso8601String()]]]])->save();
    $fit = app(PlanFit::class);

    wdtEnforce(null);
    expect(collect($fit->shortfalls($service->fresh(), ['nvme_gb' => 20]))->pluck('key')->all())->not->toContain('disk_used');

    wdtEnforce(now()->subDay()->toDateString(), $service, now()->subDays(40)->toIso8601String());
    $shortfall = collect($fit->shortfalls($service->fresh(), ['nvme_gb' => 20]))->firstWhere('key', 'disk_used');
    expect($shortfall)->not->toBeNull()->and($shortfall['have'])->toBe(30);
});

it('shows the total in the summary and next to the quotas, where the used disk stays the files only', function () {
    [$user, $org] = $this->customerWithOrganization();
    [$owner, $state] = wdtOwner($org);
    wdtIspFake($state);
    app(UsageWatch::class)->run();
    $this->actingAs($user, 'sanctum');

    $summary = $this->getJson("/v1/services/{$owner->id}")->assertOk()->json('data.summary.usage');
    expect($summary['disk_total']['total'])->toBe(55 * WDT_GB);

    $quotas = app(ServiceFeatures::class)->resources($owner->fresh(), 'quotas', true);
    expect($quotas['disk_used_bytes'])->toBe(25 * WDT_GB)->and($quotas['total']['total'])->toBe(55 * WDT_GB)->and($quotas['total_enforced_from'])->toBeNull();
});

it('gives no whole-node figure of an aaPanel node as the customer\'s usage, and carries the plan total', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $service->forceFill(['tags' => ['usage' => ['disk_total' => ['total' => 3 * WDT_GB, 'limit' => 50 * WDT_GB, 'pct' => 6, 'quality' => 'partial']]]])->save();
    Http::fake([
        '*GetSystemTotal*' => Http::response(['cpuRealUsed' => 71.5, 'memRealUsed' => 7312, 'memTotal' => 15988]),
        '*GetDiskInfo*' => Http::response([['path' => '/', 'size' => ['100G', '93G', '7G', '93%']]]),
    ]);
    $this->actingAs($user, 'sanctum');

    $data = $this->getJson("/v1/services/{$service->id}/usage")->assertOk()->json('data');

    expect($data['metrics'])->toBe(['node_cpu_pct' => null, 'node_mem_pct' => null, 'node_disk_pct' => null])
        ->and($data['disk_total']['total'])->toBe(3 * WDT_GB);
});

it('shows the parts and the total in the panel quota tab through the surface seams, never 0 for a part nobody measured', function () {
    $tools = (string) file_get_contents(base_path('apps/surfaces/api/onhost-panel-tools.api.js'));
    $workbench = (string) file_get_contents(base_path('apps/surfaces/api/onhost-panel-workbench.api.js'));

    expect($tools)->toContain("var t = q.total, nm = function (v) { return v == null ? _('nezměřeno', 'not measured') : bytes(v); };")
        ->toContain("_('Celkem z tarifu', 'Plan total')")->toContain("_(' se limit tarifu počítá ze součtu', ' the plan limit counts the total')")
        ->and($workbench)->toContain("disk_total: _('prostor celkem', 'storage in total')");
});
