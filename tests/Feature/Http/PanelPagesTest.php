<?php

declare(strict_types=1);

use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\UptimeMonitor;
use Onhost\Platform\Commands\CommandContext;

/*
 * Seam #37: the panel areas the prototype only narrated — notifications and audit, maintenance windows, costs, personal
 * data, monitoring, backups — read the organization's real data. The renderer swaps the six narrated sets for the
 * module's views, the sidebar and quick select offer them, and two organization-wide endpoints feed monitoring and backups.
 */

function pageService(string $organizationId, string $label): Service
{
    return Service::query()->create(['organization_id' => $organizationId, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Web', 'label' => $label, 'hostname' => $label.'.example.test', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'entitlements' => [], 'desired_spec' => [], 'sla_class' => 'standard', 'activated_at' => now()]);
}

it('renders the remaining panel areas from real data and lists them in the sidebar and quick select', function () {
    [$customer] = $this->customerWithOrganization();
    $this->actingAs($customer);
    $panel = $this->get('/panel/audit')->assertOk()->getContent();
    expect($panel)->toContain('src="/surfaces/api/onhost-panel-pages.api.js?v=')->toContain('"hash":"#/audit"');
    foreach (['audit', 'windows', 'costs', 'privacy', 'monitoring', 'backups'] as $page) {
        expect($panel)->toContain("if (T('{$page}')) sets.{$page} = (window.OnhostPanelPages ? window.OnhostPanelPages.{$page}(this, _, { stat, pill, bar, dot, rowStyle, match }) : null) || {");
    }
    $nav = (string) file_get_contents(base_path('apps/surfaces/api/onhost-panel-nav.api.js'));
    expect($nav)->toContain("['audit', 'windows', 'costs', 'privacy', 'monitoring', 'backups'].forEach(function (k) { if (lk[k] !== false) open.push(k); });")
        ->toContain("infra.push(['monitoring', _('Monitoring', 'Monitoring'), ''])")->toContain("account.push(['privacy', _('Osobní údaje', 'Personal data'), ''])")
        ->toContain("go({ tab: 'costs' })")->toContain("go({ tab: 'backups' })");
    $pages = (string) file_get_contents(base_path('apps/surfaces/api/onhost-panel-pages.api.js'));
    expect($pages)->toContain('window.OnhostPanelPages = { audit: audit, windows: windows, costs: costs, privacy: privacy, monitoring: monitoring, backups: backups')
        ->toContain("'/organizations/' + encodeURIComponent(orgId()) + '/audit?limit=60'")->toContain("'/monitors?limit=100'")->toContain("'/backups?limit=100'")->toContain("'/data-requests?limit=30'")->toContain("'/calendar?days=120'");
    $this->get('/surfaces/api/onhost-panel-pages.api.js')->assertOk();
});

it('lists the organization\'s uptime monitors and backups across services, never another customer\'s', function () {
    [$user, $org] = $this->customerWithOrganization();
    [, $other] = $this->customerWithOrganization();
    $shop = pageService($org->id, 'shop');
    $blog = pageService($org->id, 'blog');
    $foreign = pageService($other->id, 'foreign');
    UptimeMonitor::query()->create(['service_id' => $shop->id, 'organization_id' => $org->id, 'url' => 'https://shop.example.test/', 'interval_seconds' => 300, 'expected_status' => 200, 'timeout_seconds' => 10, 'enabled' => true, 'notify' => true, 'state' => 'down', 'consecutive_failures' => 3, 'last_checked_at' => now()->subMinutes(2), 'last_status' => 503, 'last_ms' => 1200, 'last_error' => 'HTTP 503']);
    UptimeMonitor::query()->create(['service_id' => $blog->id, 'organization_id' => $org->id, 'url' => 'https://blog.example.test/', 'interval_seconds' => 300, 'expected_status' => 200, 'timeout_seconds' => 10, 'enabled' => true, 'notify' => true, 'state' => 'up', 'consecutive_failures' => 0, 'last_checked_at' => now()->subMinute(), 'last_status' => 200, 'last_ms' => 180]);
    UptimeMonitor::query()->create(['service_id' => $foreign->id, 'organization_id' => $other->id, 'url' => 'https://foreign.example.test/', 'interval_seconds' => 300, 'expected_status' => 200, 'timeout_seconds' => 10, 'enabled' => true, 'notify' => true, 'state' => 'up', 'consecutive_failures' => 0]);
    Backup::query()->create(['service_id' => $shop->id, 'organization_id' => $org->id, 'kind' => 'site', 'state' => 'completed', 'size_bytes' => 52428800, 'started_at' => now()->subHours(6), 'finished_at' => now()->subHours(6)->addMinutes(3), 'verify_status' => 'ok', 'protected' => false]);
    Backup::query()->create(['service_id' => $blog->id, 'organization_id' => $org->id, 'kind' => 'site', 'state' => 'failed', 'size_bytes' => null, 'started_at' => now()->subHours(5), 'finished_at' => now()->subHours(5)->addMinute(), 'protected' => false]);
    Backup::query()->create(['service_id' => $foreign->id, 'organization_id' => $other->id, 'kind' => 'site', 'state' => 'completed', 'size_bytes' => 1024, 'started_at' => now(), 'finished_at' => now(), 'protected' => false]);

    $this->actingAs($user, 'sanctum');
    $monitors = $this->getJson('/v1/monitors')->assertOk()->assertJsonCount(2, 'data')->json('data');
    $byUrl = collect($monitors)->keyBy('url');
    expect($byUrl['https://shop.example.test/'])->toMatchArray(['state' => 'down', 'consecutive_failures' => 3, 'last_status' => 503, 'last_ms' => 1200, 'last_error' => 'HTTP 503'])
        ->and($byUrl['https://shop.example.test/']['service']['label'])->toBe('shop')->and($byUrl['https://shop.example.test/']['last_checked_at'])->not->toBeNull()
        ->and($byUrl->has('https://foreign.example.test/'))->toBeFalse();
    $this->getJson('/v1/monitors?state=down')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.url', 'https://shop.example.test/');

    $backups = $this->getJson('/v1/backups')->assertOk()->assertJsonCount(2, 'data')->json('data');
    $byService = collect($backups)->keyBy('service_id');
    expect($byService[$shop->id])->toMatchArray(['kind' => 'site', 'state' => 'completed', 'size_bytes' => 52428800, 'verify_status' => 'ok'])->and($byService[$shop->id]['service']['label'])->toBe('shop')
        ->and($byService[$blog->id]['state'])->toBe('failed')->and($byService->has($foreign->id))->toBeFalse();
    $this->getJson('/v1/backups?state=failed')->assertOk()->assertJsonCount(1, 'data');

    // a member without backup.read (support contact) reads monitors but not backups
    $contact = $this->customer();
    app(OrganizationService::class)->attachMember($org, $contact, 'support_contact', CommandContext::system('test'), true);
    $this->actingAs($contact, 'sanctum');
    $this->getJson('/v1/monitors')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/v1/backups')->assertForbidden();
});

it('shows the customers\' connected registrar accounts on the staff settings page', function () {
    $this->actingAs($this->staff('platform_owner'));
    expect($this->get('/sprava/nastaveni/integrace')->assertOk()->getContent())->toContain('Zákaznická připojení registrátorů')->toContain("api('GET', '/staff/registrar-connections?limit=100')")->toContain('data-op="disable"');
});
