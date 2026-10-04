<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\Web\PlanAllowance;

/*
 * The number of subdomains a web plan sells is its `subdomains` entitlement. The feature list read the limit from
 * `aliases` — the mail aliases of a mail plan — so a web service that carried both offered as many subdomains as it had
 * mail aliases, and a plan selling subdomains alone (no aliases) had no subdomain limit at all.
 */

beforeEach(fn () => Http::preventStrayRequests());

it('limits subdomains by the subdomains a plan sells, never by its mail aliases', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $features = app(ServiceFeatures::class);

    $service->forceFill(['entitlements' => array_replace((array) $service->entitlements, ['subdomains' => 5, 'aliases' => 50])])->save();
    expect($features->features($service->fresh())['subdomains'])->toBe(['enabled' => true, 'limit' => 5])
        ->and(app(PlanAllowance::class)->limit($service->fresh(), 'subdomains'))->toBe(5); // the limit subdomain.add is counted against

    // aliases alone are no subdomain limit: the plan did not sell a number of subdomains
    $service->forceFill(['entitlements' => array_diff_key((array) $service->entitlements, ['subdomains' => true]) + ['aliases' => 50]])->save();
    expect($features->features($service->fresh())['subdomains'])->toBe(['enabled' => true]);
});
