<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Products\Enums\BillingCycle;
use App\Domains\Products\Enums\ProductType;
use App\Domains\Products\Models\Product;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use Illuminate\Database\Seeder;

/**
 * Seeds the OnHost webhosting catalog with the five blueprint plans.
 *
 * ALL PRICES ARE EDITABLE PLACEHOLDERS (minor units: haléře / cents).
 * Final pricing is a business decision made in the admin panel.
 */
class ProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $product = Product::updateOrCreate(
            ['slug' => 'webhosting'],
            [
                'type'                => ProductType::Webhosting,
                'name'                => ['cs' => 'Webhosting', 'en' => 'Web Hosting'],
                'description'         => [
                    'cs' => 'Rychlý NVMe webhosting s PHP, MySQL a SSL zdarma.',
                    'en' => 'Fast NVMe web hosting with PHP, MySQL and free SSL.',
                ],
                'provisioning_driver' => ProvisioningDriver::AAPanel,
                'is_active'           => true,
                'sort_order'          => 1,
            ],
        );

        // Idempotent: only seed plans when the catalog is empty.
        if ($product->pricingPlans()->exists()) {
            return;
        }

        $plans = [
            [
                'name'        => ['cs' => 'Start', 'en' => 'Start'],
                'tagline'     => ['cs' => 'Pro první web', 'en' => 'For your first website'],
                'price_czk'   => 4_900,   // 49 Kč/mo — PLACEHOLDER
                'price_eur'   => 199,     // 1.99 €/mo — PLACEHOLDER
                'resources'   => ['disk_mb' => 5_120, 'bandwidth_gb' => 50, 'databases' => 1, 'emails' => 5],
                'is_featured' => false,
            ],
            [
                'name'        => ['cs' => 'Business', 'en' => 'Business'],
                'tagline'     => ['cs' => 'Pro rostoucí projekty', 'en' => 'For growing projects'],
                'price_czk'   => 9_900,
                'price_eur'   => 399,
                'resources'   => ['disk_mb' => 20_480, 'bandwidth_gb' => 200, 'databases' => 5, 'emails' => 25],
                'is_featured' => true,
            ],
            [
                'name'        => ['cs' => 'Pro', 'en' => 'Pro'],
                'tagline'     => ['cs' => 'Pro náročné weby', 'en' => 'For demanding websites'],
                'price_czk'   => 19_900,
                'price_eur'   => 799,
                'resources'   => ['disk_mb' => 51_200, 'bandwidth_gb' => 500, 'databases' => 20, 'emails' => 100],
                'is_featured' => false,
            ],
            [
                'name'        => ['cs' => 'AI Hosting', 'en' => 'AI Hosting'],
                'tagline'     => ['cs' => 'S AI asistentem a audity', 'en' => 'With AI assistant and audits'],
                'price_czk'   => 29_900,
                'price_eur'   => 1_199,
                'resources'   => ['disk_mb' => 51_200, 'bandwidth_gb' => 500, 'databases' => 20, 'emails' => 100],
                'is_featured' => false,
            ],
            [
                'name'        => ['cs' => 'Managed WordPress', 'en' => 'Managed WordPress'],
                'tagline'     => ['cs' => 'WordPress bez starostí', 'en' => 'WordPress without worries'],
                'price_czk'   => 39_900,
                'price_eur'   => 1_599,
                'resources'   => ['disk_mb' => 102_400, 'bandwidth_gb' => 1_000, 'databases' => 50, 'emails' => 250],
                'is_featured' => false,
            ],
        ];

        foreach ($plans as $index => $plan) {
            $product->pricingPlans()->create([
                ...$plan,
                'billing_cycle' => BillingCycle::Monthly,
                'is_active'     => true,
                'sort_order'    => $index + 1,
            ]);
        }
    }
}
