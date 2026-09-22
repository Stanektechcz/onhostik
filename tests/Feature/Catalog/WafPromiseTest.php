<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\WafLevels;
use Onhost\Domain\Services\ServiceFeatures;

/*
 * Every web hosting plan states a WAF level — „WAF základní“, „WAF standard“, „WAF + CDN“ — and the level was a label
 * and nothing else: rendered on the price list, handed to the panel as a string, never compared with what the site's
 * own server can do. The panels do not do the same things: aaPanel applies per-site request and connection limits,
 * ISPConfig cannot (nginx wants its `limit_req_zone` in the node's http block, which a customer's site does not own).
 * So a plan promising a rate limit delivered nothing of the sort on a shared node, and nobody was told.
 */

beforeEach(fn () => Http::preventStrayRequests());

it('tells the customer what their plan promised and what their own server does of it', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok', 'data' => [], 'page' => '']));
    [, $org] = $this->customerWithOrganization();

    $shared = featureWebService($org, 'ispconfig');
    $shared->forceFill(['entitlements' => array_replace((array) $shared->entitlements, ['waf' => 'advanced+cdn'])])->save();
    $security = app(ServiceFeatures::class)->features($shared->refresh())['security']['options'];

    expect($security['level'])->toBe('advanced')
        ->and($security['promised'])->toContain('rate')->toContain('bots')
        ->and($security['delivered'])->toContain('bots')->not->toContain('rate')
        ->and($security['missing'])->toBe(['rate']); // said out loud instead of quietly not happening
});

it('promises nothing it cannot keep on a panel that limits requests itself', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok', 'data' => [], 'page' => '']));
    [, $org] = $this->customerWithOrganization();

    $managed = featureWebService($org, 'aapanel');
    $managed->forceFill(['entitlements' => array_replace((array) $managed->entitlements, ['waf' => 'pro + CDN'])])->save();

    expect(app(ServiceFeatures::class)->features($managed->refresh())['security']['options']['missing'])->toBe([]);
});

it('reads the level out of the words the price list actually uses', function () {
    expect(WafLevels::levelOf('basic'))->toBe('basic')
        ->and(WafLevels::levelOf('standard'))->toBe('standard')
        ->and(WafLevels::levelOf('advanced+cdn'))->toBe('advanced')
        ->and(WafLevels::levelOf('pro + CDN + DDoS'))->toBe('pro')
        ->and(WafLevels::levelOf('custom rules'))->toBe('custom')
        ->and(WafLevels::levelOf('WAF nové generace'))->toBeNull()   // a word nobody defined is not quietly „basic“
        ->and(WafLevels::levelOf(null))->toBeNull()
        ->and(WafLevels::missing('pro', ServiceFeatures::securitySupports('ispconfig')))->toBe(['rate'])
        ->and(WafLevels::missing('standard', ServiceFeatures::securitySupports('ispconfig')))->toBe([]);
});

it('names every plan on sale whose panel cannot keep its WAF promise', function () {
    $this->seed(CatalogSeeder::class);
    $supports = ['ispconfig' => ServiceFeatures::securitySupports('ispconfig'), 'aapanel' => ServiceFeatures::securitySupports('aapanel')];

    $problems = WafLevels::onSaleProblems($supports);

    // the guard has to be able to find one: Profi sells „advanced+cdn“ and shared hosting runs on ISPConfig
    expect($problems)->toHaveKey('web-hosting/profi')->and($problems['web-hosting/profi'])->toBe(['rate'])
        ->and($problems)->not->toHaveKey('web-hosting/start');   // „basic“ promises nothing the panel lacks

    // and a level nobody defined is reported as such, not silently taken for the weakest one
    $plan = Plan::query()->where('key', 'start')->firstOrFail();
    $version = $plan->currentVersion();
    $version->forceFill(['entitlements' => array_replace((array) $version->entitlements, ['waf' => 'hyper'])])->save();
    expect(WafLevels::onSaleProblems($supports)['web-hosting/start'])->toBe(['?hyper']);
});
