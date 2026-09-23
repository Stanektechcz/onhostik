<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\Web\PlanAllowance;
use Onhost\Domain\Services\Web\ServiceSites;
use Onhost\Platform\Errors\DomainError;

/*
 * A plan that sells „10 webů a 2 databáze“ sells two databases — for the plan, not for each of its sites. Every
 * carried site is a service of its own and was given a copy of the plan's numbers, while the limit was counted at
 * one site's panel against that site's copy: ten sites meant ten times the databases, mailboxes, FTP accounts and
 * cron jobs the customer paid for, each of them real space on a shared node. The numbers of a plan are counted
 * across every site of the plan now, and a plan change reaches the sites that plan pays for.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** ISPConfig where the databases of the plan all sit on the service's own site (7), and the carried site (8) has none. */
function groupPanel(int $ownSite = 2): void
{
    Http::fake(function (Request $request) use ($ownSite) {
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $parent = (int) data_get($request->data(), 'primary_id.parent_domain_id', 0);
        $databases = array_map(fn (int $i) => ['database_id' => $i, 'database_name' => 'c3_web'.$i, 'database_charset' => 'utf8mb4'], range(1, max(1, $ownSite)));

        return Http::response(match (true) {
            $function === 'login' => ['code' => 'ok', 'message' => '', 'response' => 'sess-group'],
            $function === 'sites_database_get' => ['code' => 'ok', 'message' => '', 'response' => $parent === 7 && $ownSite > 0 ? $databases : []],
            $function === 'monitor_jobqueue_count' => ['code' => 'ok', 'message' => '', 'response' => 0],
            default => ['code' => 'ok', 'message' => '', 'response' => []],
        });
    });
}

/** A further site of the same plan: a service of its own, paid for inside the plan of the one carrying it. */
function carriedSite(Service $owner, string $domain = 'druhy-web.cz', string $remoteId = '8', int $shareGb = 20): Service
{
    $site = Service::query()->create([
        'organization_id' => $owner->organization_id, 'project_id' => $owner->project_id, 'product_key' => $owner->product_key, 'plan_version_id' => $owner->plan_version_id,
        'family' => $owner->family, 'name' => $domain, 'hostname' => $domain, 'state' => ServiceStateMachine::ACTIVE, 'region_code' => $owner->region_code,
        'entitlements' => array_replace((array) $owner->entitlements, ['sites' => 1, 'nvme_gb' => $shareGb]),
        'sla_class' => $owner->sla_class, 'tags' => ['parent_service_id' => $owner->id, 'billing' => 'included', 'sites' => ['quota_gb' => $shareGb]],
        'desired_spec' => array_replace((array) $owner->desired_spec, ['domain' => $domain]), 'node_id' => $owner->node_id, 'provider_instance_id' => $owner->provider_instance_id,
        'activated_at' => now(), 'health' => [],
    ]);
    ProviderBinding::query()->create(['service_id' => $site->id, 'provider_instance_id' => $owner->provider_instance_id, 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "carried:{$site->id}",
        'adapter_version' => '1.0.0', 'remote_type' => 'web_domain', 'remote_id' => $remoteId, 'remote_node' => '1', 'meta' => ['client_id' => 3, 'system_user' => 'web'.$remoteId, 'document_root' => '/var/www/clients/client3/web'.$remoteId]]);

    return $site;
}

it('counts a number of the plan across every site of the plan, not once for each of them', function () {
    groupPanel(2); // the plan sells two databases and both of them are on the service's own site
    [$user, $org] = $this->customerWithOrganization();
    $owner = featureWebService($org, 'ispconfig');
    $site = carriedSite($owner);

    $add = fn (Service $on, string $key) => app(ServiceService::class)->requestAction($on, 'database.create', $this->contextFor($user, $org), $key, ['name' => 'novy', 'password' => 'Correct-Horse-Battery-9']);

    expect(fn () => $add($site, 'group-1'))->toThrow(DomainError::class)   // the carried site has none of its own — and none of the plan is left
        ->and(fn () => $add($owner, 'group-2'))->toThrow(DomainError::class);
});

it('leaves the plan the room it still has', function () {
    groupPanel(1); // one database of the two is used
    [$user, $org] = $this->customerWithOrganization();
    $owner = featureWebService($org, 'ispconfig');
    $site = carriedSite($owner);

    $operation = app(ServiceService::class)->requestAction($site, 'database.create', $this->contextFor($user, $org), 'group-room', ['name' => 'novy', 'password' => 'Correct-Horse-Battery-9']);

    expect($operation)->toBeInstanceOf(Operation::class);
});

it('says which numbers of a plan belong to the plan and not to one of its sites', function () {
    expect(array_keys(PlanAllowance::GROUP_WIDE))->toContain('databases')->toContain('mailboxes')->toContain('ftp')->toContain('cron');
});

it('carries a plan change to the sites the plan pays for, and leaves each of them its own share', function () {
    [, $org] = $this->customerWithOrganization();
    $owner = featureWebService($org, 'ispconfig');
    $site = carriedSite($owner);

    // the customer upgraded: more databases, more mailboxes, more space
    $owner->forceFill(['entitlements' => array_replace((array) $owner->entitlements, ['databases' => 10, 'mailboxes' => 50, 'nvme_gb' => 200])])->save();
    $carried = ServiceSites::spread($owner->refresh());

    expect($carried)->toBe(1)
        ->and((int) data_get($site->refresh()->entitlements, 'databases'))->toBe(10)
        ->and((int) data_get($site->entitlements, 'mailboxes'))->toBe(50)
        ->and((int) data_get($site->entitlements, 'nvme_gb'))->toBe(20)   // its share of the plan's space is its own
        ->and((int) data_get($site->entitlements, 'sites'))->toBe(1);
});
