<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\Models\ProductOption;
use Onhost\Domain\Orders\QuoteService;

/*
 * Phase Q, decision P-R76 (staging check 2026-10-13, docs/runbooks/staging-rehearsal-2026-10-15.md finding 2): the web plans kept
 * offering two priced options in the cart and the checkout that promise what was decided against — "Prioritní podpora — reakce do
 * 10 minut, 24/7" (support is Mon–Fri 8–17, P-R37) and "WAF + CDN — firewall aplikací a anycast cache" (no CDN: H-R2, M2b, P-R46).
 * Both are withdrawn from sale: the seeder no longer writes them, and a running catalogue loses them through migration 001180 — only
 * while an option still carries the seeded words (words staff wrote are theirs). Whoever bought one keeps it: the choice lives in
 * the service's entitlements and its price in the subscription, neither is touched.
 */

const Q2_WITHDRAWN = ['priority_support', 'waf_cdn'];
const Q2_WEB_PRODUCTS = ['web-hosting', 'wordpress', 'eshop', 'web-custom'];

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class, ContentSeeder::class]);
    Cache::flush();
});

/** @return array<string, mixed> the `cs`/`en` data of the generated `onhost-data.js` */
function q2SurfaceData(object $test): array
{
    $js = (string) $test->get('/surfaces/onhost-data.js')->assertOk()->getContent();
    preg_match('/var D = (\{.*\});\n  function L/s', $js, $m);

    return (array) json_decode($m[1] ?? '{}', true);
}

/** The option rows the seeder wrote before phase Q (a catalogue deployed before 001180). */
function q2SeedOldOptions(): void
{
    $rows = [
        'waf_cdn' => ['label' => ['cs' => 'WAF + CDN', 'en' => 'WAF + CDN'], 'desc' => ['cs' => 'firewall aplikací a anycast cache', 'en' => 'application firewall and anycast cache'],
            'price' => ['CZK' => 9900, 'EUR' => 399], 'entitlement' => ['key' => 'waf', 'value' => 'pro + CDN']],
        'priority_support' => ['label' => ['cs' => 'Prioritní podpora', 'en' => 'Priority support'], 'desc' => ['cs' => 'reakce do 10 minut, 24/7', 'en' => '10-minute response, 24/7'],
            'price' => ['CZK' => 14900, 'EUR' => 599], 'entitlement' => ['key' => 'support', 'value' => 'priority 10 min']],
    ];
    $sold = ['web-hosting' => ['waf_cdn' => 60, 'priority_support' => 80], 'wordpress' => ['waf_cdn' => 30, 'priority_support' => 40],
        'eshop' => ['priority_support' => 30], 'web-custom' => ['waf_cdn' => 80, 'priority_support' => 100]];
    foreach ($sold as $productKey => $options) {
        $product = Product::query()->where('key', $productKey)->firstOrFail();
        foreach ($options as $key => $sort) {
            $row = $rows[$key];
            ProductOption::query()->create(['product_id' => $product->id, 'key' => $key, 'kind' => 'addon', 'label' => $row['label'], 'price_per_unit_minor' => $row['price'],
                'sort' => $sort, 'meta' => ['cfg_key' => $key, 'desc' => $row['desc'], 'entitlement' => $row['entitlement']]]);
        }
    }
}

function q2Options(): array
{
    return ProductOption::query()->whereIn('key', Q2_WITHDRAWN)->orderBy('product_id')->orderBy('key')->get()
        ->map(fn (ProductOption $o) => Product::query()->find($o->product_id)?->key.'#'.$o->key)->all();
}

it('no longer seeds and no longer offers priority support or WAF + CDN in the cart and the checkout', function () {
    expect(q2Options())->toBe([]);

    $data = q2SurfaceData($this);
    foreach (['cs', 'en'] as $locale) {
        foreach (Q2_WEB_PRODUCTS as $productKey) {
            $keys = collect($data[$locale]['addons'][$productKey]['options'] ?? [])->pluck('key')->all();
            expect($keys)->not->toContain('priority_support')->not->toContain('waf_cdn');
        }
    }
    $js = (string) $this->get('/surfaces/onhost-data.js')->getContent();
    expect($js)->not->toContain('reakce do 10 minut')->not->toContain('anycast')->not->toContain('10-minute response');

    // a cart that still sends the old switches is quoted without them: no line, no price
    $quote = fn (array $options) => app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'standard', 'config' => ['options' => $options]]],
        'CZK', ['country' => 'CZ', 'customer_class' => 'b2c', 'vat_status' => 'unknown']);
    expect($quote(['priority_support' => true, 'waf_cdn' => true])->total_minor)->toBe($quote([])->total_minor);
});

it('withdraws the seeded options from a running catalogue and only while the seeded words stand', function () {
    q2SeedOldOptions();
    expect(q2Options())->toHaveCount(7);
    // staff rewrote one option: their words, their decision
    $product = Product::query()->where('key', 'wordpress')->firstOrFail();
    ProductOption::query()->where('product_id', $product->id)->where('key', 'priority_support')->firstOrFail()
        ->forceFill(['meta' => ['cfg_key' => 'priority_support', 'desc' => ['cs' => 'reakce v pracovní době do 2 hodin', 'en' => 'response within 2 business hours'], 'entitlement' => ['key' => 'support', 'value' => 'priority']]])->save();
    $others = ProductOption::query()->whereNotIn('key', Q2_WITHDRAWN)->orderBy('id')->get()->toArray();
    $services = DB::table('services')->count();
    $subscriptions = DB::table('subscriptions')->count();

    $migration = require base_path('database/migrations/0001_01_01_001180_web_options_without_unbacked_addons.php');
    $migration->up();
    expect(q2Options())->toBe(['wordpress#priority_support'])
        ->and(ProductOption::query()->whereNotIn('key', Q2_WITHDRAWN)->orderBy('id')->get()->toArray())->toBe($others) // every other option untouched
        ->and(DB::table('services')->count())->toBe($services)->and(DB::table('subscriptions')->count())->toBe($subscriptions);

    $migration->up(); // idempotent
    expect(q2Options())->toBe(['wordpress#priority_support']);

    $migration->down(); // back to the seeded rows, never duplicating the one staff kept
    expect(q2Options())->toHaveCount(7);
    $restored = ProductOption::query()->where('product_id', Product::query()->where('key', 'web-hosting')->value('id'))->where('key', 'priority_support')->firstOrFail();
    expect($restored->label)->toBe(['cs' => 'Prioritní podpora', 'en' => 'Priority support'])
        ->and($restored->price_per_unit_minor)->toBe(['CZK' => 14900, 'EUR' => 599])->and($restored->sort)->toBe(80)
        ->and(data_get($restored->meta, 'desc.cs'))->toBe('reakce do 10 minut, 24/7');
    expect(data_get(ProductOption::query()->where('product_id', $product->id)->where('key', 'priority_support')->firstOrFail()->meta, 'desc.cs'))->toBe('reakce v pracovní době do 2 hodin');
});
