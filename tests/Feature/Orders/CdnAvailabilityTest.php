<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Cache;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\PricingRules;

/*
 * K7-5 (M2): the cart offered "CDN a WAF · CDN Start" next to web hosting. The owner's "bez CDN" (H-R2, 2026-10-06) is about the
 * platform's own origin, not this add-on; but the add-on is delivered by the Cloudflare connection (Addons `cdn`, ServiceFeatures
 * `cdn`), and without a configured account it delivered nothing while it was sold and billed. Owner decision R4: a plan offers only
 * what its server delivers — so without a CDN account the add-on is not offered, the cart refuses it before anybody pays, and its
 * page says "Připravujeme".
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class, ContentSeeder::class]);
    Cache::flush();
});

function cdnData(object $test): array
{
    $js = (string) $test->get('/surfaces/onhost-data.js')->assertOk()->getContent();
    preg_match('/var D = (\{.*\});\n  function L/s', $js, $m);

    return (array) json_decode($m[1] ?? '{}', true);
}

it('does not offer or sell the CDN add-on without a CDN account', function () {
    config(['onhost.cdn.cloudflare.secret_ref' => null]);

    expect(app(PricingRules::class)->addonProducts('web-hosting'))->not->toContain('cdn')->toContain('mail-hosting');
    [$owner] = $this->customerWithOrganization();
    $this->actingAs($owner, 'sanctum');
    $this->putJson('/v1/cart', ['items' => [['product_key' => 'cdn', 'plan_key' => 'cdn-start', 'qty' => 1]]])->assertOk();
    $this->postJson('/v1/cart/quote')->assertStatus(409)->assertJsonPath('error', 'cdn_unavailable');

    $page = cdnData($this)['cs']['pages']['cdn'] ?? null;
    expect($page)->not->toBeNull()->and(json_encode($page, JSON_UNESCAPED_UNICODE))->toContain('Připravujeme');
});

it('offers and quotes the CDN add-on once a CDN account is configured', function () {
    config(['onhost.cdn.cloudflare.secret_ref' => 'env://CLOUDFLARE']);
    Product::query()->where('key', 'cdn')->update(['state' => 'active']); // off sale since phase U (CD-3, TASK-0218): the account gate still holds once it is back

    expect(app(PricingRules::class)->addonProducts('web-hosting'))->toContain('cdn');
    [$owner] = $this->customerWithOrganization();
    $this->actingAs($owner, 'sanctum');
    $this->putJson('/v1/cart', ['items' => [['product_key' => 'cdn', 'plan_key' => 'cdn-start', 'qty' => 1]]])->assertOk();
    $this->postJson('/v1/cart/quote')->assertOk()->assertJsonPath('data.lines.0.product_key', 'cdn');
});
