<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Marketplace\Models\MarketplaceApp;
use Illuminate\Database\Seeder;

/**
 * Each app carries an install recipe: where the payload comes from, whether the
 * archive nests everything in a versioned folder, and whether it needs a
 * database. Without it the marketplace can list an app but not install it.
 *
 * URLs deliberately point at each project's "latest" endpoint rather than a
 * pinned version — pinning here means shipping a stale, unpatched CMS the day
 * after upstream releases a security fix.
 */
class MarketplaceAppSeeder extends Seeder
{
    public function run(): void
    {
        $apps = [
            [
                'slug' => 'wordpress', 'name' => 'WordPress', 'category' => 'cms', 'icon' => 'globe',
                'sort_order' => 1, 'description' => 'Nejpoužívanější CMS na světě.',
                'install_url' => 'https://wordpress.org/latest.tar.gz',
                'archive_subdir' => 'wordpress', 'requires_database' => true,
                'docs_url' => 'https://wordpress.org/documentation/',
            ],
            [
                'slug' => 'joomla', 'name' => 'Joomla', 'category' => 'cms', 'icon' => 'globe',
                'sort_order' => 2, 'description' => 'Výkonný open-source CMS.',
                'install_url' => 'https://downloads.joomla.org/cms/joomla5/5-3-2/Joomla_5-3-2-Stable-Full_Package.zip',
                'requires_database' => true,
                'docs_url' => 'https://docs.joomla.org/',
            ],
            [
                'slug' => 'drupal', 'name' => 'Drupal', 'category' => 'cms', 'icon' => 'globe',
                'sort_order' => 3, 'description' => 'Flexibilní CMS pro náročné projekty.',
                'install_url' => 'https://ftp.drupal.org/files/projects/drupal-11.1.5.tar.gz',
                'archive_subdir' => 'drupal-11.1.5', 'requires_database' => true,
                'docs_url' => 'https://www.drupal.org/docs',
            ],
            [
                'slug' => 'woocommerce', 'name' => 'WooCommerce', 'category' => 'ecommerce', 'icon' => 'shopping-cart',
                'sort_order' => 10, 'description' => 'E-shop plugin pro WordPress.', 'min_disk_gb' => 2,
                'install_url' => 'https://downloads.wordpress.org/plugin/woocommerce.zip',
                // A plugin, not a site: it belongs inside an existing WordPress.
                'install_path' => 'wp-content/plugins',
                'docs_url' => 'https://woocommerce.com/documentation/',
            ],
            [
                'slug' => 'prestashop', 'name' => 'PrestaShop', 'category' => 'ecommerce', 'icon' => 'shopping-cart',
                'sort_order' => 11, 'description' => 'Kompletní e-shop řešení.', 'min_disk_gb' => 2,
                'install_url' => 'https://assets.prestashop3.com/dst/edition/corporate/8.2.0/prestashop_8.2.0.zip',
                'requires_database' => true,
                'docs_url' => 'https://devdocs.prestashop-project.org/',
            ],
            [
                'slug' => 'phpmyadmin', 'name' => 'phpMyAdmin', 'category' => 'database', 'icon' => 'database',
                'sort_order' => 20, 'description' => 'Webová správa MySQL databází.',
                'install_url' => 'https://files.phpmyadmin.net/phpMyAdmin/5.2.2/phpMyAdmin-5.2.2-all-languages.zip',
                'archive_subdir' => 'phpMyAdmin-5.2.2-all-languages', 'install_path' => 'phpmyadmin',
                'docs_url' => 'https://docs.phpmyadmin.net/',
            ],
            [
                'slug' => 'roundcube', 'name' => 'Roundcube', 'category' => 'email', 'icon' => 'mail',
                'sort_order' => 30, 'description' => 'Webový e-mail klient.',
                'install_url' => 'https://github.com/roundcube/roundcubemail/releases/download/1.6.11/roundcubemail-1.6.11-complete.tar.gz',
                'archive_subdir' => 'roundcubemail-1.6.11', 'install_path' => 'webmail',
                'requires_database' => true,
                'docs_url' => 'https://roundcube.net/support/',
            ],
        ];

        foreach ($apps as $app) {
            // updateOrCreate, not firstOrCreate: existing installs seeded before
            // recipes existed would otherwise stay permanently uninstallable.
            MarketplaceApp::updateOrCreate(
                ['slug' => $app['slug']],
                array_merge(['min_disk_gb' => 1, 'is_active' => true, 'install_source' => 'archive'], $app),
            );
        }
    }
}
