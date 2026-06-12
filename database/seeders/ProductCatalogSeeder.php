<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Products\Enums\BillingCycle;
use App\Domains\Products\Enums\ProductType;
use App\Domains\Products\Models\Product;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use Illuminate\Database\Seeder;

/**
 * Seeds the OnHost product catalog.
 *
 * ALL PRICES ARE EDITABLE PLACEHOLDERS (minor units: haléře / cents).
 * Final pricing is a business decision made in the admin panel.
 *
 * Idempotent: existing plans are never touched. Run with --force to re-seed.
 */
class ProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedWebhosting();
        $this->seedVps();
        $this->seedGamehosting();
        $this->seedMailhosting();
        $this->seedManagedHosting();
    }

    private function seedWebhosting(): void
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

        if ($product->pricingPlans()->exists()) {
            return;
        }

        $plans = [
            [
                'name'        => ['cs' => 'Start', 'en' => 'Start'],
                'tagline'     => ['cs' => 'Pro první web', 'en' => 'For your first website'],
                'price_czk'   => 4_900,
                'price_eur'   => 199,
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

    private function seedVps(): void
    {
        $product = Product::updateOrCreate(
            ['slug' => 'vps'],
            [
                'type'                => ProductType::Vps,
                'name'                => ['cs' => 'Cloud VPS', 'en' => 'Cloud VPS'],
                'description'         => [
                    'cs' => 'KVM virtualizace, NVMe disky, root přístup, IPv4 a IPv6 v ceně.',
                    'en' => 'KVM virtualisation, NVMe storage, root access, IPv4 and IPv6 included.',
                ],
                'provisioning_driver' => ProvisioningDriver::Proxmox,
                'is_active'           => true,
                'sort_order'          => 2,
            ],
        );

        if ($product->pricingPlans()->exists()) {
            return;
        }

        $plans = [
            [
                'name'        => ['cs' => 'VPS Nano', 'en' => 'VPS Nano'],
                'tagline'     => ['cs' => 'Testovací projekty a CI', 'en' => 'Dev & CI workloads'],
                'price_czk'   => 14_900,
                'price_eur'   => 599,
                'resources'   => ['cpu' => 1, 'ram_mb' => 1_024, 'disk_mb' => 20_480, 'bandwidth_gb' => 500],
                'is_featured' => false,
            ],
            [
                'name'        => ['cs' => 'VPS Starter', 'en' => 'VPS Starter'],
                'tagline'     => ['cs' => 'Malé aplikace a weby', 'en' => 'Small apps & websites'],
                'price_czk'   => 29_900,
                'price_eur'   => 1_199,
                'resources'   => ['cpu' => 2, 'ram_mb' => 2_048, 'disk_mb' => 40_960, 'bandwidth_gb' => 1_000],
                'is_featured' => false,
            ],
            [
                'name'        => ['cs' => 'VPS Business', 'en' => 'VPS Business'],
                'tagline'     => ['cs' => 'Produkční projekty', 'en' => 'Production workloads'],
                'price_czk'   => 59_900,
                'price_eur'   => 2_399,
                'resources'   => ['cpu' => 4, 'ram_mb' => 4_096, 'disk_mb' => 81_920, 'bandwidth_gb' => 2_000],
                'is_featured' => true,
            ],
            [
                'name'        => ['cs' => 'VPS Pro', 'en' => 'VPS Pro'],
                'tagline'     => ['cs' => 'Náročné aplikace a e-shopy', 'en' => 'High-traffic apps & e-shops'],
                'price_czk'   => 99_900,
                'price_eur'   => 3_999,
                'resources'   => ['cpu' => 8, 'ram_mb' => 8_192, 'disk_mb' => 163_840, 'bandwidth_gb' => 4_000],
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

    private function seedGamehosting(): void
    {
        $product = Product::updateOrCreate(
            ['slug' => 'gamehosting'],
            [
                'type'                => ProductType::Gamehosting,
                'name'                => ['cs' => 'Gamehosting', 'en' => 'Game Hosting'],
                'description'         => [
                    'cs' => 'Herní servery s nízkou latencí, anti-DDoS ochranou a instalací na jedno kliknutí.',
                    'en' => 'Game servers with low latency, DDoS protection and one-click game installation.',
                ],
                'provisioning_driver' => ProvisioningDriver::Pterodactyl,
                'is_active'           => true,
                'sort_order'          => 3,
            ],
        );

        if ($product->pricingPlans()->exists()) {
            return;
        }

        $plans = [
            [
                'name'        => ['cs' => 'Game Starter', 'en' => 'Game Starter'],
                'tagline'     => ['cs' => 'Až 10 hráčů', 'en' => 'Up to 10 players'],
                'price_czk'   => 9_900,
                'price_eur'   => 399,
                'resources'   => ['cpu' => 2, 'ram_mb' => 2_048, 'disk_mb' => 20_480, 'slots' => 10],
                'is_featured' => false,
            ],
            [
                'name'        => ['cs' => 'Game Plus', 'en' => 'Game Plus'],
                'tagline'     => ['cs' => 'Až 25 hráčů', 'en' => 'Up to 25 players'],
                'price_czk'   => 19_900,
                'price_eur'   => 799,
                'resources'   => ['cpu' => 4, 'ram_mb' => 4_096, 'disk_mb' => 40_960, 'slots' => 25],
                'is_featured' => true,
            ],
            [
                'name'        => ['cs' => 'Game Pro', 'en' => 'Game Pro'],
                'tagline'     => ['cs' => 'Až 50 hráčů', 'en' => 'Up to 50 players'],
                'price_czk'   => 34_900,
                'price_eur'   => 1_399,
                'resources'   => ['cpu' => 6, 'ram_mb' => 8_192, 'disk_mb' => 81_920, 'slots' => 50],
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

    private function seedMailhosting(): void
    {
        $product = Product::updateOrCreate(
            ['slug' => 'mailhosting'],
            [
                'type'                => ProductType::Webhosting,
                'name'                => ['cs' => 'Mailhosting', 'en' => 'Mail Hosting'],
                'description'         => [
                    'cs' => 'Firemní e-mail na vlastní doméně s antispamem, IMAP/POP3 a webmailem.',
                    'en' => 'Business email on your own domain with anti-spam, IMAP/POP3 and webmail.',
                ],
                'provisioning_driver' => ProvisioningDriver::AAPanel,
                'is_active'           => true,
                'sort_order'          => 4,
            ],
        );

        if ($product->pricingPlans()->exists()) {
            return;
        }

        $plans = [
            [
                'name'        => ['cs' => 'Mail Start', 'en' => 'Mail Start'],
                'tagline'     => ['cs' => '5 schránek', 'en' => '5 mailboxes'],
                'price_czk'   => 4_900,
                'price_eur'   => 199,
                'resources'   => ['emails' => 5, 'disk_mb' => 10_240, 'bandwidth_gb' => 50],
                'is_featured' => false,
            ],
            [
                'name'        => ['cs' => 'Mail Business', 'en' => 'Mail Business'],
                'tagline'     => ['cs' => '25 schránek', 'en' => '25 mailboxes'],
                'price_czk'   => 12_900,
                'price_eur'   => 519,
                'resources'   => ['emails' => 25, 'disk_mb' => 51_200, 'bandwidth_gb' => 200],
                'is_featured' => true,
            ],
            [
                'name'        => ['cs' => 'Mail Enterprise', 'en' => 'Mail Enterprise'],
                'tagline'     => ['cs' => 'Neomezené schránky', 'en' => 'Unlimited mailboxes'],
                'price_czk'   => 24_900,
                'price_eur'   => 999,
                'resources'   => ['emails' => 250, 'disk_mb' => 204_800, 'bandwidth_gb' => 1_000],
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

    private function seedManagedHosting(): void
    {
        $product = Product::updateOrCreate(
            ['slug' => 'managed-hosting'],
            [
                'type'                => ProductType::Webhosting,
                'name'                => ['cs' => 'Managed hosting', 'en' => 'Managed Hosting'],
                'description'         => [
                    'cs' => 'Hosting, o který se kompletně staráme my — aktualizace, zálohy, bezpečnost.',
                    'en' => 'Fully managed hosting — we handle updates, backups and security for you.',
                ],
                'provisioning_driver' => ProvisioningDriver::AAPanel,
                'is_active'           => true,
                'sort_order'          => 5,
            ],
        );

        if ($product->pricingPlans()->exists()) {
            return;
        }

        $plans = [
            [
                'name'        => ['cs' => 'Managed Start', 'en' => 'Managed Start'],
                'tagline'     => ['cs' => 'Jeden web, veškerá péče', 'en' => 'One website, full care'],
                'price_czk'   => 24_900,
                'price_eur'   => 999,
                'resources'   => ['disk_mb' => 20_480, 'bandwidth_gb' => 100, 'databases' => 3, 'emails' => 10],
                'is_featured' => false,
            ],
            [
                'name'        => ['cs' => 'Managed Business', 'en' => 'Managed Business'],
                'tagline'     => ['cs' => 'Více webů, prioritní podpora', 'en' => 'Multiple sites, priority support'],
                'price_czk'   => 49_900,
                'price_eur'   => 1_999,
                'resources'   => ['disk_mb' => 51_200, 'bandwidth_gb' => 500, 'databases' => 10, 'emails' => 50],
                'is_featured' => true,
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
