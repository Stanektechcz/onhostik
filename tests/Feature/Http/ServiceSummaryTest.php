<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\UptimeMonitor;

/*
 * The service at a glance: the API detail carries a summary (plan and billing, project, location, certificate, last
 * backup, monitor, operations) that the workbench's first tab shows, list rows carry the billing part, and the panel's
 * service rows name the plan, the period and the renewal date — the node and the panel behind it stay hidden.
 */

it('summarises plan, billing, project, backup, monitor and operations of a service for the detail, the list and the panel rows', function () {
    $this->seed([CatalogSeeder::class]);
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $standard = app(CatalogService::class)->resolve('web-hosting', 'standard', 'CZK', 'year');
    $project = Project::query()->create(['organization_id' => $org->id, 'name' => 'E-shop', 'slug' => 'eshop', 'tags' => []]);
    $subscription = Subscription::query()->create([
        'organization_id' => $org->id, 'service_id' => $service->id, 'plan_version_id' => $standard['version']->id, 'price_id' => $standard['price']->id, 'currency' => 'CZK', 'period' => 'year', 'amount_minor' => 189000,
        'state' => Subscription::ACTIVE, 'current_period_start' => now()->subDays(30), 'current_period_end' => now()->addDays(335), 'next_renewal_at' => now()->addDays(328), 'auto_renew' => true, 'renewal_priority' => 'normal',
    ]);
    $service->forceFill(['subscription_id' => $subscription->id, 'plan_version_id' => $standard['version']->id, 'project_id' => $project->id, 'tags' => array_merge((array) $service->tags, ['access' => ['domain' => 'shop.cz', 'certificate' => 'issued']])])->save();
    Backup::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'provider_instance_id' => $service->provider_instance_id, 'kind' => 'scheduled', 'state' => 'available', 'size_bytes' => 1024, 'started_at' => now()->subHours(7), 'finished_at' => now()->subHours(6), 'verified_at' => now()->subHours(5), 'offsite' => true, 'protected' => false]);
    Backup::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'provider_instance_id' => $service->provider_instance_id, 'kind' => 'scheduled', 'state' => 'running', 'size_bytes' => 0, 'started_at' => now(), 'protected' => false]);
    UptimeMonitor::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'url' => 'https://shop.cz/', 'interval_seconds' => 60, 'expected_status' => 200, 'timeout_seconds' => 10, 'enabled' => true, 'notify' => true, 'state' => 'up', 'consecutive_failures' => 0, 'last_checked_at' => now()->subMinute(), 'last_status' => 200, 'last_ms' => 123]);
    Operation::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'kind' => 'service.action', 'workflow' => 'service.action', 'state' => Operation::FAILED, 'queue' => 'default', 'idempotency_key' => 'sum-failed', 'desired' => ['action' => 'ssl.issue'], 'queued_at' => now()->subDay(), 'finished_at' => now()->subDay(), 'attempts' => 1, 'error' => ['message' => 'nope', 'retryable' => false]]);
    Operation::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'kind' => 'service.action', 'workflow' => 'service.action', 'state' => Operation::RUNNING, 'queue' => 'default', 'idempotency_key' => 'sum-running', 'desired' => ['action' => 'backup'], 'queued_at' => now(), 'attempts' => 1]);
    $this->actingAs($user, 'sanctum');

    $summary = $this->getJson("/v1/services/{$service->id}")->assertOk()->json('data.summary');
    expect($summary['plan'])->toMatchArray(['key' => 'standard', 'name' => 'Standard'])
        ->and($summary['billing'])->toMatchArray(['period' => 'year', 'auto_renew' => true, 'cancel_at_period_end' => false, 'state' => Subscription::ACTIVE])->and($summary['billing']['amount']['minor'])->toBe(189000)
        ->and(substr((string) $summary['billing']['next_renewal_at'], 0, 10))->toBe(now()->addDays(328)->toDateString())
        ->and($summary['project'])->toBe(['id' => $project->id, 'name' => 'E-shop'])->and($summary['location']['region'])->toBe('cz1')->and($summary)->not->toHaveKey('node')
        ->and($summary['certificate'])->toBe('issued')->and($summary['access_domain'])->toBe('shop.cz')
        ->and($summary['backup'])->toMatchArray(['kind' => 'scheduled', 'size_bytes' => 1024, 'offsite' => true, 'verified' => true])->and($summary['backups_count'])->toBe(1)
        ->and($summary['monitor'])->toMatchArray(['state' => 'up', 'enabled' => true, 'last_status' => 200, 'last_ms' => 123])
        ->and($summary['operations']['active'])->toBe(1)->and($summary['operations']['last_failed']['kind'])->toBe('service.action');
    expect(json_encode($summary))->not->toMatch('/aapanel|ispconfig|managed01/i');
    // the onboarding checklist: own domain (shop.cz) and the certificate done, backup verified, monitor on — nothing left
    expect(collect($summary['checklist'])->pluck('done', 'key')->all())->toBe(['domain' => true, 'certificate' => true, 'backup' => true, 'monitor' => true]);
    $service->forceFill(['tags' => array_merge((array) $service->tags, ['access' => ['domain' => 'x1y2z3.web.onhost.cz', 'certificate' => 'pending_dns']])])->save();
    UptimeMonitor::query()->where('service_id', $service->id)->update(['enabled' => false]);
    $again = collect($this->getJson("/v1/services/{$service->id}")->assertOk()->json('data.summary.checklist'));
    expect($again->pluck('done', 'key')->all())->toBe(['domain' => false, 'certificate' => false, 'backup' => true, 'monitor' => false])
        ->and($again->firstWhere('key', 'certificate')['hint'])->toContain('Čeká, až doména ukáže na web')->and($again->firstWhere('key', 'domain')['tab'])->toBe('cfg')->and($again->firstWhere('key', 'monitor')['action'])->toBe('monitoring.set');
    $service->forceFill(['tags' => array_merge((array) $service->tags, ['access' => ['domain' => 'shop.cz', 'certificate' => 'issued']])])->save();
    UptimeMonitor::query()->where('service_id', $service->id)->update(['enabled' => true]);

    // list rows carry the billing part
    $row = collect($this->getJson('/v1/services')->assertOk()->json('data'))->firstWhere('id', $service->id);
    expect($row['plan']['name'])->toBe('Standard')->and($row['billing']['period'])->toBe('year')->and($row['billing']['amount']['minor'])->toBe(189000);

    // the panel's service rows name the plan, the period and the renewal date
    $js = $this->actingAs($user)->get('/surfaces/onhost-panel.js')->assertOk()->getContent();
    expect($js)->toContain('Standard · ročně · obnova '.now()->addDays(328)->format('j. n. Y').' · 1 890 Kč / rok')->toContain('"plan":"Standard"')->toContain('"period":"year"')->toContain('"renewal":"1 890 Kč / rok"');

    // the workbench shows the summary on the first tab with the one-click period switch; the panel keeps haléře on amounts
    $module = (string) file_get_contents((string) $this->get('/surfaces/api/onhost-panel-workbench.api.js')->assertOk()->baseResponse->getFile());
    expect($module)->toContain('function summaryPairs(')->toContain("_('Platba a obnova', 'Billing and renewal')")->toContain('pairs.concat(sm === null')->toContain('window.OnhostPanelTools.switchPeriod(cmp, sel, _, helpers(), other)');
    $tools = (string) file_get_contents((string) $this->get('/surfaces/api/onhost-panel-tools.api.js')->assertOk()->baseResponse->getFile());
    expect($tools)->toContain('switchPeriod: switchPeriod')->toContain("_('Platit ročně', 'Bill yearly')");
    $panel = $this->get('/panel')->assertOk()->getContent();
    expect($panel)->toContain('const __x = Math.round(n * c.rate * 100) / 100, __d = Number.isInteger(__x) ? c.dec : Math.max(c.dec, 2);')->not->toContain('{ minimumFractionDigits: c.dec, maximumFractionDigits: c.dec }).format(n * c.rate);');
    // deep links on boot: the URL is written back only once the shell is mounted, so /panel/fakturace (#/fakturace) opens Fakturace
    expect($panel)->toContain("componentDidMount() {\n    this.__onhostMounted = true;")->toContain('if (location.hash !== next && this.__onhostMounted) {')->not->toContain("    if (location.hash !== next) {\n      try { history.replaceState(null, '', next); }");
});
