<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\PlanPromises;
use Onhost\Domain\Catalog\PlanVersioning;
use Onhost\Domain\Services\UsageWatch;
use Onhost\Platform\Errors\DomainError;

/*
 * A plan promises only what the platform enforces, applies or measures (audit §5ad). A plan version carries two
 * machine-read bags — `entitlements` and `limits` — and five numbers in them were read by no code at all:
 * `cpu_seconds_per_day` (web hosting, Managed WordPress, e-shop, custom), `db_connections` and
 * `outbound_mail_per_hour` (web hosting), `pps_limit` (VPS). None of them was even shown to the customer: they only
 * looked like limits in the plan editor. `inodes` was sold, both panels count the files of a site, and nothing ever
 * compared the two — a site that had eaten its whole file allowance heard about it from the node, not from us. And
 * the transfer was sold as a sentence ("fair-use 500 GB/měs") while UsageWatch reads the number `traffic_gb`.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class]);
    Http::preventStrayRequests();
});

it('sells no number the platform neither reads nor declares as fair use', function () {
    $read = PlanPromises::readInSource();
    expect(PlanPromises::onSaleProblems($read))->toBe([]);

    // the five that were there, named: a plan on sale carries none of them any more
    foreach (Plan::query()->where('state', 'active')->get() as $plan) {
        $version = $plan->currentVersion();
        $keys = $version === null ? [] : array_keys(array_merge((array) $version->entitlements, (array) ($version->limits ?? [])));
        foreach (['cpu_seconds_per_day', 'db_connections', 'outbound_mail_per_hour', 'pps_limit', 'traffic'] as $gone) {
            expect($keys)->not->toContain($gone, "{$plan->key} still promises {$gone}");
        }
    }
    // what stays and is now real
    $start = Plan::query()->where('key', 'start')->firstOrFail()->currentVersion();
    expect($start?->entitlements['traffic_gb'] ?? null)->toBe(500)->and($start?->entitlements['inodes'] ?? null)->toBe(250000);
});

it('measures the transfer and the file count the plan sells, against what the panel reports', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $service->forceFill(['entitlements' => array_replace((array) $service->entitlements, ['traffic_gb' => 500, 'inodes' => 250000])])->save();
    Http::fake(function ($request) {
        if (! str_starts_with($request->url(), ISP)) {
            return null;
        }
        $answer = match ((string) parse_url($request->url(), PHP_URL_QUERY)) {
            'login' => 'sess-promises',
            'quota_get_by_user' => [['domain_id' => 7, 'used' => 1024, 'hard' => 0, 'soft' => 0, 'files' => 232_500]], // 93 % of the files the plan sells
            'trafficquota_get_by_user' => [['domain_id' => 7, 'this_month' => 460 * 1024 ** 3]], // 92 % of the transfer
            default => false,
        };

        return Http::response(['code' => 'ok', 'message' => '', 'response' => $answer]);
    });

    $metrics = app(UsageWatch::class)->measure($service->fresh());
    expect($metrics['inodes'] ?? null)->toMatchArray(['used' => 232_500, 'limit' => 250_000, 'pct' => 93]) // against the old code there is no such metric
        ->and($metrics['traffic'] ?? null)->toMatchArray(['used' => 460 * 1024 ** 3, 'limit' => 500 * 1024 ** 3, 'pct' => 92]) // and the limit was a sentence
        ->and(UsageWatch::top($metrics))->toMatchArray(['key' => 'inodes'])
        ->and(UsageWatch::level($metrics))->toBe('warn')
        ->and(UsageWatch::metricLabel('inodes'))->toBe('počet souborů');
});

it('lets a number leave a plan: the schema could only grow', function () {
    $staff = $this->staff('platform_owner');
    $context = $this->contextFor($staff, null, 'totp');
    $versions = app(PlanVersioning::class);
    $before = Plan::query()->where('key', 'start')->firstOrFail()->currentVersion();
    expect($before?->entitlements)->toHaveKey('inodes');

    // against the old code a null was "plan_value_invalid" and a removal counted as no change at all
    $published = $versions->publish('web-hosting', 'start', ['entitlements' => ['inodes' => null], 'reason' => 'nikdo to neuplatňuje'], $context);
    expect($published->entitlements)->not->toHaveKey('inodes')->and($published->entitlements['traffic_gb'])->toBe(500)
        ->and($published->version)->toBe(($before?->version ?? 0) + 1);

    // a key the plan never had is still refused — a version may change the plan, not invent its schema
    expect(fn () => $versions->publish('web-hosting', 'start', ['entitlements' => ['vymyslene' => 5], 'reason' => 'nový klíč'], $context))
        ->toThrow(DomainError::class, 'is not part of this plan');
});
