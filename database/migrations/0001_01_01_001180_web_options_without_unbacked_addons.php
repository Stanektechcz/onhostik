<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\Models\ProductOption;

/**
 * Phase Q, decision P-R76 (staging check 2026-10-13, docs/runbooks/staging-rehearsal-2026-10-15.md finding 2): the web plans offered
 * two priced options in the cart and the checkout that promise what was decided against — "Prioritní podpora — reakce do 10 minut,
 * 24/7" (support is Mon–Fri 8–17, P-R37) and "WAF + CDN — firewall aplikací a anycast cache" (no CDN: H-R2, M2b, P-R46). A deploy
 * runs migrations, not seeders: the option rows the seeder wrote are removed here — only while they still carry the seeded label and
 * description (an option staff rewrote is theirs). Whoever bought one keeps it: the choice is in the service's entitlements and its
 * price in the subscription, neither is touched here. Every other option, price, plan and plan version stays.
 */
return new class extends Migration
{
    /** option key => what the seeder wrote */
    private const SEEDED = [
        'waf_cdn' => [
            'label' => ['cs' => 'WAF + CDN', 'en' => 'WAF + CDN'],
            'desc' => ['cs' => 'firewall aplikací a anycast cache', 'en' => 'application firewall and anycast cache'],
            'price' => ['CZK' => 9900, 'EUR' => 399],
            'entitlement' => ['key' => 'waf', 'value' => 'pro + CDN'],
        ],
        'priority_support' => [
            'label' => ['cs' => 'Prioritní podpora', 'en' => 'Priority support'],
            'desc' => ['cs' => 'reakce do 10 minut, 24/7', 'en' => '10-minute response, 24/7'],
            'price' => ['CZK' => 14900, 'EUR' => 599],
            'entitlement' => ['key' => 'support', 'value' => 'priority 10 min'],
        ],
    ];

    /** product key => option key => the sort the seeder gave it */
    private const SOLD_WITH = [
        'web-hosting' => ['waf_cdn' => 60, 'priority_support' => 80],
        'wordpress' => ['waf_cdn' => 30, 'priority_support' => 40],
        'eshop' => ['priority_support' => 30],
        'web-custom' => ['waf_cdn' => 80, 'priority_support' => 100],
    ];

    public function up(): void
    {
        foreach ($this->products() as [$product, $key]) {
            $option = ProductOption::query()->where('product_id', $product->id)->where('key', $key)->first();
            if ($option !== null && $this->seeded($option, $key)) {
                $option->delete();
            }
        }
    }

    public function down(): void
    {
        foreach ($this->products() as [$product, $key, $sort]) {
            if (ProductOption::query()->where('product_id', $product->id)->where('key', $key)->exists()) {
                continue; // an option staff kept (or created again) stays as it is
            }
            $seeded = self::SEEDED[$key];
            ProductOption::query()->create([
                'product_id' => $product->id, 'key' => $key, 'kind' => 'addon', 'label' => $seeded['label'], 'unit' => null, 'min' => null,
                'max' => null, 'step' => null, 'default_value' => null, 'price_per_unit_minor' => $seeded['price'], 'sort' => $sort, 'choices' => null,
                'meta' => ['cfg_key' => $key, 'desc' => $seeded['desc'], 'entitlement' => $seeded['entitlement']],
            ]);
        }
    }

    private function seeded(ProductOption $option, string $key): bool
    {
        $seeded = self::SEEDED[$key];

        return (array) $option->label == $seeded['label'] && (array) data_get($option->meta, 'desc') == $seeded['desc'];
    }

    /** @return list<array{0: Product, 1: string, 2: int}> */
    private function products(): array
    {
        $out = [];
        foreach (self::SOLD_WITH as $productKey => $options) {
            $product = Product::query()->where('key', $productKey)->first();
            if ($product === null) {
                continue; // a fresh database: the seeder writes the web products without these options
            }
            foreach ($options as $key => $sort) {
                $out[] = [$product, $key, $sort];
            }
        }

        return $out;
    }
};
