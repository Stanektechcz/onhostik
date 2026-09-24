<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Web\ClientAllowance;

/*
 * ISPConfig keeps one client account per organization and enforces its limits, so those limits are the
 * ORGANIZATION's: the sum of what its services hold on that panel. Two rules make the sum right, and both of them
 * are easy to get wrong:
 *
 *  - a web hosting that sells „10 webů“ already says `sites = 10` and `nvme_gb = 50`; the sites it carries are those
 *    ten, each holding a share of that same space. Counting them again would count one plan twice.
 *  - a service on its way out still holds its site at the panel until it is purged, so it still counts. Only
 *    TERMINATED stops counting.
 */

beforeEach(fn () => Http::preventStrayRequests());

/**
 * Another service of the same customer on the same panel. `ClientAllowance` reads the services table and nothing
 * else, so these need no provider binding — and creating one would collide with the fixture's (the unique key is
 * instance + remote type + remote id).
 */
function allowanceService(Service $beside, array $entitlements, array $attributes = []): Service
{
    return Service::query()->create(array_merge([
        'organization_id' => $beside->organization_id, 'product_key' => $beside->product_key, 'family' => 'web',
        'name' => 'Another', 'hostname' => 'jiny-'.substr(md5(json_encode($entitlements).json_encode($attributes)), 0, 6).'.cz',
        'state' => ServiceStateMachine::ACTIVE, 'region_code' => $beside->region_code,
        'provider_instance_id' => $beside->provider_instance_id, 'node_id' => $beside->node_id,
        'desired_spec' => ['executor' => (string) $beside->spec('executor', 'ispconfig'), 'family' => 'web'],
        'entitlements' => $entitlements, 'sla_class' => 'standard', 'tags' => [], 'health' => [],
    ], $attributes));
}

it('adds up what the organization holds on one panel and counts a carried site only once', function () {
    [, $org] = $this->customerWithOrganization();
    $first = featureWebService($org, 'ispconfig');                    // sites 3, nvme 50, databases 2, mailboxes 10, ssh
    $second = allowanceService($first, ['sites' => 1, 'nvme_gb' => 10, 'databases' => 1, 'mailboxes' => 5]);
    // a site the first hosting carries: its space is a share of the first plan's 50 GB, not 20 GB on top
    allowanceService($first, ['sites' => 1, 'nvme_gb' => 20, 'databases' => 2], ['tags' => ['billing' => 'included', 'parent_service_id' => $first->id]]);

    $total = ClientAllowance::of($second);

    expect($total['sites'])->toBe(4)              // 3 + 1, the carried site is one of the three
        ->and($total['nvme_gb'])->toBe(60)        // 50 + 10
        ->and($total['databases'])->toBe(3)
        ->and($total['mailboxes'])->toBe(15)
        ->and($total['ssh'])->toBeTrue();         // one service with it is enough for the client
});

it('keeps counting a service that is on its way out, and stops at terminated', function () {
    [, $org] = $this->customerWithOrganization();
    $live = featureWebService($org, 'ispconfig');
    $ending = allowanceService($live, ['sites' => 1, 'nvme_gb' => 10], ['state' => ServiceStateMachine::SUSPENDED, 'terminate_at' => now()->addDays(30)]);

    expect(ClientAllowance::of($live)['sites'])->toBe(4); // its site is still on the node until it is purged

    $ending->forceFill(['state' => ServiceStateMachine::TERMINATED])->save();

    expect(ClientAllowance::of($live)['sites'])->toBe(3); // gone for good, so the limit may come down
});

it('counts the target of a resize, not what the row still says', function () {
    // a plan change asks the panel for the new limits before `finishResizeStep` stores them; sending this plan alone
    // rewrote the client's limits down and took the space from every other site of the same customer
    [, $org] = $this->customerWithOrganization();
    $growing = featureWebService($org, 'ispconfig');                                   // sites 3, nvme 50
    allowanceService($growing, ['sites' => 2, 'nvme_gb' => 30]);                        // a second hosting of the same customer

    $before = ClientAllowance::of($growing);
    $after = ClientAllowance::of($growing, ['sites' => 10, 'nvme_gb' => 200]);          // upgrading the first one

    expect($before['sites'])->toBe(5)->and($before['nvme_gb'])->toBe(80)
        ->and($after['sites'])->toBe(12)->and($after['nvme_gb'])->toBe(230);            // the neighbour's 2 and 30 stay
});

it('leaves a service of another organization or another panel out of the sum', function () {
    [, $org] = $this->customerWithOrganization();
    $mine = featureWebService($org, 'ispconfig');
    [, $other] = $this->customerWithOrganization();
    $theirs = Service::query()->create([
        'organization_id' => $other->id, 'product_key' => $mine->product_key, 'family' => 'web', 'name' => 'Theirs', 'hostname' => 'cizi.cz',
        'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'provider_instance_id' => $mine->provider_instance_id, 'node_id' => $mine->node_id,
        'desired_spec' => ['executor' => 'ispconfig', 'family' => 'web'], 'entitlements' => ['sites' => 9, 'nvme_gb' => 900], 'sla_class' => 'standard', 'tags' => [], 'health' => [],
    ]);
    $elsewhere = featureWebService($org, 'aapanel'); // the same customer, a different panel

    expect(ClientAllowance::of($mine)['sites'])->toBe(3)                 // the stranger's 9 are not ours
        ->and(ClientAllowance::of($elsewhere)['sites'])->toBe(3)         // and the other panel counts only itself
        ->and(ClientAllowance::of($theirs)['sites'])->toBe(9);
});
