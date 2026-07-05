<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Marketplace\Models\MarketplaceApp;
use Illuminate\Database\Seeder;

class MarketplaceAppSeeder extends Seeder
{
    public function run(): void
    {
        $apps = [
            ['slug' => 'wordpress',   'name' => 'WordPress',   'category' => 'cms',       'icon' => 'globe',      'sort_order' => 1,  'description' => 'Nejpoužívanější CMS na světě.'],
            ['slug' => 'joomla',      'name' => 'Joomla',      'category' => 'cms',       'icon' => 'globe',      'sort_order' => 2,  'description' => 'Výkonný open-source CMS.'],
            ['slug' => 'drupal',      'name' => 'Drupal',      'category' => 'cms',       'icon' => 'globe',      'sort_order' => 3,  'description' => 'Flexibilní CMS pro náročné projekty.'],
            ['slug' => 'woocommerce', 'name' => 'WooCommerce', 'category' => 'ecommerce', 'icon' => 'shopping-cart', 'sort_order' => 10, 'description' => 'E-shop plugin pro WordPress.', 'min_disk_gb' => 2],
            ['slug' => 'prestashop',  'name' => 'PrestaShop',  'category' => 'ecommerce', 'icon' => 'shopping-cart', 'sort_order' => 11, 'description' => 'Kompletní e-shop řešení.', 'min_disk_gb' => 2],
            ['slug' => 'phpmyadmin',  'name' => 'phpMyAdmin',  'category' => 'database',  'icon' => 'database',   'sort_order' => 20, 'description' => 'Webová správa MySQL databází.'],
            ['slug' => 'roundcube',   'name' => 'Roundcube',   'category' => 'email',     'icon' => 'mail',       'sort_order' => 30, 'description' => 'Webový e-mail klient.'],
        ];

        foreach ($apps as $app) {
            MarketplaceApp::firstOrCreate(
                ['slug' => $app['slug']],
                array_merge(['min_disk_gb' => 1, 'is_active' => true], $app),
            );
        }
    }
}
