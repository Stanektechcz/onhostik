<?php

declare(strict_types=1);

use App\Http\Support\CatalogPresentation;
use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Catalog\CatalogRevisions;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\PlanPromises;
use Onhost\Domain\Catalog\PlanVersioning;
use Onhost\Domain\Services\Metering\MetricRegistry;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\UsageWatch;
use Onhost\Platform\Commands\CommandContext;
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
    // the catalogue as production has it once the operator applied the code-defined revisions (onhost:catalog:revise --apply):
    // the seeder only writes version 1, the revisions publish the versions without the promises the platform does not keep
    app(CatalogRevisions::class)->apply(null, CommandContext::system('test:plan-promises'));
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

/*
 * The guard used to trust "the word appears somewhere under domains/providers/platform/app" as proof a key was
 * applied — and the price list itself (`CatalogPresentation`) names every key it sells, so it satisfied its own
 * guard. `products`, `connections` and `dedicated_outbound_ip` passed that way (audit §5ad, brain card H278).
 */
it('does not count a key the price list only names as read code', function () {
    $read = PlanPromises::readInSource();

    // "connections" (managed database) and "dedicated_outbound_ip" (mail) are named only by
    // app/Http/Support/CatalogPresentation.php — nothing else in the platform ever reads either literal
    expect($read)->not->toContain('connections')
        ->and($read)->not->toContain('dedicated_outbound_ip');
});

it('keeps KNOWN_GAPS equal to the gaps the platform actually has today', function () {
    $read = PlanPromises::readInSource();
    $actual = collect(PlanPromises::actualGaps($read))->sort()->values()->all();
    $known = collect(array_keys(PlanPromises::KNOWN_GAPS))->sort()->values()->all();

    // fails the moment a gap is fixed and the line is left behind (the ratchet may only shrink), and just the same
    // the moment a new sold number keeps no promise and is not yet named here
    expect($actual)->toBe($known);
});

it('names an enforcer for every hard-limit metric and a source for every measured one', function () {
    foreach (MetricRegistry::REGISTRY as $key => $entry) {
        if ($entry['status'] === MetricRegistry::ENFORCED_ONLY && $entry['limit_kind'] === MetricRegistry::HARD) {
            expect(array_filter($entry['sources']))->not->toBe([], "{$key}: enforced_only + hard names no enforcer in its sources");
        }
        if ($entry['status'] === MetricRegistry::MEASURED) {
            expect(array_filter($entry['sources']))->not->toBe([], "{$key}: measured names no source");
        }
        if ($entry['status'] === MetricRegistry::GAP) {
            expect((string) $entry['reason'])->not->toBe('', "{$key}: gap carries no reason");
        }
    }
});

/*
 * Every ratchet entry has to be backed by the registry it claims to come from — a key named in KNOWN_GAPS with no
 * matching MetricRegistry row, or a row that is not actually a GAP, would let the ratchet's own explanation drift
 * from what the table says (QA finding, audit §5ad). Confirmed failing-first by deleting the `aliases` row from
 * `MetricRegistry::REGISTRY` and re-running: "KNOWN_GAPS key 'aliases' has no MetricRegistry row at all" — restored
 * afterwards.
 */
it('names a GAP row in MetricRegistry, with a reason, for every KNOWN_GAPS key', function () {
    foreach (array_keys(PlanPromises::KNOWN_GAPS) as $key) {
        $entry = MetricRegistry::get($key);
        expect($entry)->not->toBeNull("KNOWN_GAPS key '{$key}' has no MetricRegistry row at all");
        expect($entry['status'])->toBe(MetricRegistry::GAP, "KNOWN_GAPS key '{$key}' is not recorded as a GAP in MetricRegistry")
            ->and((string) $entry['reason'])->not->toBe('', "KNOWN_GAPS key '{$key}' carries no MetricRegistry reason");
    }
});

/*
 * MetricRegistry::isKept() used to ignore a row's `families` entirely, so a key verified only for e.g. mail passed
 * for any plan that happened to sell the same key name (reviewer finding, audit §5ad). `quota_gb_per_mailbox` is
 * measured/enforced only for `families => ['mail']`; selling it on a web-hosting plan must not pass on the row's
 * own status alone.
 */
it('reports a registry key sold on a product family its row never verified', function () {
    $entry = MetricRegistry::get('quota_gb_per_mailbox');
    expect($entry)->not->toBeNull()->and($entry['families'])->toBe(['mail'])
        ->and(MetricRegistry::isKept('quota_gb_per_mailbox', 'mail'))->toBeTrue()
        ->and(MetricRegistry::isKept('quota_gb_per_mailbox', 'web'))->toBeFalse();

    $version = Plan::query()->where('key', 'start')->firstOrFail()->currentVersion(); // web-hosting/start, family "web"
    $version->entitlements = array_merge((array) $version->entitlements, ['quota_gb_per_mailbox' => 5]);
    expect(PlanPromises::rawUnkept($version, PlanPromises::readInSource()))->toContain('quota_gb_per_mailbox');
});

/*
 * Only is_int()/is_float() routed a numeric promise through the registry; a numeric *string* such as "500" fell
 * through to the generous text-scan rule meant for capability flags (QA finding, audit §5ad). "aliases" is a
 * MetricRegistry GAP (ISPConfig has no limit_mailalias), but the bare word "aliases" also appears elsewhere in the
 * codebase as an unrelated feature-flag key — exactly the false "read" the text scan used to produce.
 */
it('routes a numeric-string promise through the registry exactly like an int, not the text scan', function () {
    $read = PlanPromises::readInSource();
    expect($read)->toContain('aliases'); // proves the old text-scan rule would have waved this one through

    $version = Plan::query()->where('key', 'start')->firstOrFail()->currentVersion();
    $version->entitlements = array_merge((array) $version->entitlements, ['aliases' => '50']);
    expect(PlanPromises::rawUnkept($version, $read))->toContain('aliases');

    // and a kept key sold as a numeric string is not wrongly flagged either
    $version->entitlements = array_merge((array) $version->entitlements, ['aliases' => 0, 'mailboxes' => '5']);
    expect(PlanPromises::rawUnkept($version, $read))->not->toContain('mailboxes');
});

/*
 * Owner decision 5 (2026-09-25): the product count of an e-shop plan is a recommendation, not a limit — nothing reads a
 * store's product count back and nothing caps it. It is declared fair use and worded as one on the price list.
 */
it('treats the e-shop product count as fair use: never a gap, worded as a recommendation', function () {
    expect(PlanPromises::FAIR_USE)->toHaveKey('products')->and(PlanPromises::KNOWN_GAPS)->not->toHaveKey('products');
    $start = Plan::query()->where('key', 'shop-start')->firstOrFail()->currentVersion();
    expect($start?->entitlements['products'] ?? null)->toBe(1000)
        ->and(PlanPromises::rawUnkept($start, PlanPromises::readInSource()))->not->toContain('products')
        ->and(CatalogPresentation::bullets(['products' => 1000], 'managed', 'cs'))->toBe(['Doporučeno do 1 000 produktů'])
        ->and(CatalogPresentation::bullets(['products' => 1000], 'managed', 'en'))->toBe(['Recommended up to 1,000 products'])
        ->and(CatalogPresentation::bullets(['products' => 999999], 'managed', 'cs'))->toBe(['Bez limitu produktů']);
});

/*
 * A revision stops selling a promise; the customers who bought the old version still hold it (owner rule: existing versions
 * are never edited). Support has to see which versions in the hands of customers promise something the platform does not
 * provide, so it can answer them honestly — the doctor lists them.
 */
it('reports the promises a version customers still hold keeps no longer', function () {
    [, $org] = $this->customerWithOrganization();
    $plan = Plan::query()->where('key', 'db-s')->firstOrFail();
    $v1 = $plan->versions()->where('version', 1)->sole();
    expect($plan->current_version)->toBe(2);
    $read = PlanPromises::readInSource();
    expect(PlanPromises::grandfatheredGaps($read))->toBe([]); // nobody holds v1 yet

    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'database', 'family' => 'data', 'name' => 'DB S', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1',
        'plan_version_id' => $v1->id, 'entitlements' => (array) $v1->entitlements, 'desired_spec' => ['family' => 'data'], 'sla_class' => 'standard', 'tags' => []]);
    $gaps = PlanPromises::grandfatheredGaps($read);
    expect(array_keys($gaps))->toBe(['database/db-s@v1'])->and($gaps['database/db-s@v1'])->toEqualCanonicalizing(['pitr_days', 'connections']);

    $service->forceFill(['state' => ServiceStateMachine::TERMINATED])->save(); // an ended service holds nothing
    expect(PlanPromises::grandfatheredGaps($read))->toBe([]);
});
