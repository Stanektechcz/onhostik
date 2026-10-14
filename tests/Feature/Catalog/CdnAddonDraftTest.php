<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\PlanPromises;
use Onhost\Domain\Catalog\PricingRules;
use Onhost\Domain\Services\Metering\MetricRegistry;

/*
 * Phase U, U2 (CD-3, SEZNAM default "ano"): the add-on "CDN a WAF" (`cdn`) was an active product with no executor of its own. The
 * cart refused it without a Cloudflare account (K7-5), but the catalogue still had it on sale, so its plans kept two promises nothing
 * keeps on the platform — a monthly transfer (`traffic_tb`, measured for VPS/VDS only) and bot management (`bot_management`, nothing
 * at all). It leaves sale through the catalogue: the seeder writes it as a draft, a data migration takes a running catalogue's
 * product off sale while it still stands as the seeder wrote it, and the ratchet `PlanPromises::KNOWN_GAPS` drops both keys (5 → 3).
 * Whoever holds a CDN subscription keeps it: neither the subscription nor its plan version is touched.
 */

const U2_MIGRATION = 'database/migrations/0001_01_01_001210_cdn_addon_back_to_draft.php';

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class, ContentSeeder::class]);
    Cache::flush();
});

/** The cdn product as a database migrated before phase U holds it: on sale, as the seeder used to write it. */
function u2CdnOnSale(): Product
{
    $product = Product::query()->where('key', 'cdn')->firstOrFail();
    $product->forceFill(['state' => 'active'])->save();

    return $product->fresh();
}

it('seeds the CDN add-on as a draft: not offered, not quotable, even with a CDN account', function () {
    expect(Product::query()->where('key', 'cdn')->value('state'))->toBe('draft');

    config(['onhost.cdn.cloudflare.secret_ref' => 'env://CLOUDFLARE']);
    foreach (['web-hosting', 'web-custom', 'wordpress', 'eshop', 'vps'] as $parent) {
        expect(app(PricingRules::class)->addonProducts($parent))->not->toContain('cdn');
    }
    [$owner] = $this->customerWithOrganization();
    $this->actingAs($owner, 'sanctum');
    $this->putJson('/v1/cart', ['items' => [['product_key' => 'cdn', 'plan_key' => 'cdn-start', 'qty' => 1]]])->assertOk();
    $this->postJson('/v1/cart/quote')->assertStatus(409)->assertJsonPath('error', 'product_not_sellable');

    config(['onhost.cdn.cloudflare.secret_ref' => null]); // without an account the earlier refusal still answers first (K7-5)
    $this->postJson('/v1/cart/quote')->assertStatus(409)->assertJsonPath('error', 'cdn_unavailable');
});

it('takes a running catalogue\'s CDN add-on off sale only while it stands as the seeder wrote it, and down() puts it back', function () {
    $product = u2CdnOnSale();
    [, $org] = $this->customerWithOrganization();
    $version = Plan::query()->where('product_id', $product->id)->where('key', 'shield')->firstOrFail()->currentVersion();
    $held = Subscription::query()->create([
        'organization_id' => $org->id, 'service_id' => null, 'plan_version_id' => $version->id, 'price_id' => null, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => 89000,
        'state' => Subscription::ACTIVE, 'current_period_start' => now()->subDays(3), 'current_period_end' => now()->addDays(27), 'next_renewal_at' => now()->addDays(27), 'auto_renew' => true,
    ]);
    $plans = DB::table('plans')->where('product_id', $product->id)->orderBy('key')->get()->toArray();
    $versions = DB::table('plan_versions')->whereIn('plan_id', array_column($plans, 'id'))->orderBy('id')->get()->toArray();

    $migration = require base_path(U2_MIGRATION);
    $migration->up();
    expect($product->fresh()->state)->toBe('draft')
        ->and($held->fresh()->state)->toBe('active') // a customer who holds one keeps it
        ->and($held->fresh()->plan_version_id)->toBe($version->id)
        ->and(DB::table('plans')->where('product_id', $product->id)->orderBy('key')->get()->toArray())->toEqual($plans) // plans, versions and prices stay
        ->and(DB::table('plan_versions')->whereIn('plan_id', array_column($plans, 'id'))->orderBy('id')->get()->toArray())->toEqual($versions);

    $migration->down();
    expect($product->fresh()->state)->toBe('active');
});

it('leaves a CDN add-on staff rewrote or retired as it is', function () {
    $product = u2CdnOnSale();
    $product->forceFill(['description' => ['cs' => 'Náš vlastní popis', 'en' => 'Our own words']])->save();
    $migration = require base_path(U2_MIGRATION);

    $migration->up();
    expect($product->fresh()->state)->toBe('active'); // staff's words: their product, the doctor names its gaps instead

    $product->forceFill(['state' => 'retired', 'description' => Product::query()->where('key', 'web-hosting')->firstOrFail()->description])->save();
    $migration->down();
    expect($product->fresh()->state)->toBe('retired'); // down() restores only the draft it made
});

it('closes traffic_tb and bot_management on the ratchet: KNOWN_GAPS 5 → 3 and equal to the gaps on sale', function () {
    $read = PlanPromises::readInSource();
    $actual = PlanPromises::actualGaps($read);

    expect(array_key_exists('traffic_tb', PlanPromises::KNOWN_GAPS))->toBeFalse('traffic_tb is kept for every family that sells it on sale')
        ->and(array_key_exists('bot_management', PlanPromises::KNOWN_GAPS))->toBeFalse('nothing on sale sells bot_management')
        ->and(count(PlanPromises::KNOWN_GAPS))->toBeLessThanOrEqual(3)
        ->and($actual)->not->toContain('traffic_tb')
        ->and($actual)->not->toContain('bot_management');

    $traffic = MetricRegistry::get('traffic_tb');
    expect(array_key_exists('gap_families', (array) $traffic))->toBeFalse('traffic_tb no longer names a family it is a gap for on sale')
        ->and(MetricRegistry::isKept('traffic_tb', 'cloud'))->toBeTrue()
        ->and(MetricRegistry::get('bot_management')['status'] ?? null)->toBe(MetricRegistry::GAP); // the row stays: versions customers hold

    u2CdnOnSale(); // back on sale (e.g. staff publish it before a CDN account exists): both gaps come back and the guard names them
    expect(PlanPromises::actualGaps($read))->toContain('traffic_tb')->toContain('bot_management');
});
