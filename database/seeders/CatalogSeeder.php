<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\CatalogRevisions;
use Onhost\Domain\Catalog\Models\DomainPrice;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Price;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\Models\ProductOption;
use Onhost\Domain\Catalog\Models\PromoCode;
use Onhost\Domain\Catalog\Models\TldPolicy;
use Onhost\Domain\Domains\Models\RegistrarTldCost;
use Onhost\Domain\Services\Models\Service;

/**
 * Launch catalog derived from the approved prototype (apps/surfaces/onhost-svc-*.js,
 * onhost-data.js) with the real limits the blueprint demands (§43.7, §52). Prices
 * are net; the tax engine adds VAT. Renewal price is shown next to the first-period
 * price (§49.4). Idempotent: re-running updates definitions and keeps versions; a version a service or subscription holds is
 * left exactly as it is (entitlements, limits, features and prices).
 */
final class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->product('web-hosting', 'web', 'ispconfig', 'subscription', ['cs' => 'Webhosting', 'en' => 'Web hosting'], ['cs' => 'Sdílený PHP/WordPress hosting na NVMe s vlastními limity na projekt.', 'en' => 'Shared PHP/WordPress hosting on NVMe with per-project limits.'], 10, ['chips' => ['NVMe RAID10', 'PHP 8.1–8.4', 'HTTP/3', 'Zálohy 30 dní'], 'persona' => 'web', 'addon_products' => ['ssl', 'cdn', 'backup-plus', 'mail-hosting']], [
            ['start', ['cs' => 'Start', 'en' => 'Start'], 8900, 89000, 390, 3900, false, 'standard', ['sites' => 1, 'nvme_gb' => 10, 'php_workers' => 2, 'php_memory_mb' => 512, 'mailboxes' => 5, 'databases' => 1, 'backup_days' => 7, 'backup_generations' => 7, 'traffic_gb' => 500, 'ssh' => false, 'staging' => false, 'waf' => 'basic', 'php_versions' => ['8.2', '8.3', '8.4'], 'inodes' => 250000, 'cron_concurrency' => 1], [], ['cs' => 'Pro první web nebo portfolio.', 'en' => 'For a first site or a portfolio.'], ['cs' => ['1 web', '10 GB NVMe', '5 schránek', '1 databáze', 'Zálohy 7 dní', 'Certifikát zdarma'], 'en' => ['1 site', '10 GB NVMe', '5 mailboxes', '1 database', '7-day backups', 'Free certificate']]],             ['standard', ['cs' => 'Standard', 'en' => 'Standard'], 18900, 189000, 790, 7900, true, 'standard', ['sites' => 10, 'nvme_gb' => 50, 'php_workers' => 6, 'php_memory_mb' => 1024, 'mailboxes' => 50, 'databases' => 20, 'backup_days' => 30, 'backup_generations' => 30, 'traffic_gb' => 2048, 'ssh' => true, 'staging' => true, 'waf' => 'standard', 'php_versions' => ['8.1', '8.2', '8.3', '8.4'], 'inodes' => 1000000, 'cron_concurrency' => 2], [], ['cs' => 'Pro weby, které mají návštěvnost.', 'en' => 'For sites with real traffic.'], ['cs' => ['10 webů', '50 GB NVMe', '50 schránek', '20 databází', 'Zálohy 30 dní', 'Staging na klik'], 'en' => ['10 sites', '50 GB NVMe', '50 mailboxes', '20 databases', '30-day backups', 'One-click staging']]],             ['profi', ['cs' => 'Profi', 'en' => 'Pro'], 44900, 449000, 1890, 18900, false, 'business', ['sites' => 50, 'nvme_gb' => 200, 'php_workers' => 12, 'php_workers_dedicated' => true, 'php_memory_mb' => 2048, 'mailboxes' => 500, 'databases' => 100, 'backup_days' => 90, 'backup_generations' => 90, 'traffic_gb' => 5120, 'ssh' => true, 'staging' => true, 'waf' => 'advanced+cdn', 'php_versions' => ['8.1', '8.2', '8.3', '8.4'], 'inodes' => 4000000, 'cron_concurrency' => 4], [], ['cs' => 'Pro e-shopy a klientské weby.', 'en' => 'For shops and client sites.'], ['cs' => ['50 webů', '200 GB NVMe', '500 schránek', 'Dedikované PHP workery', 'Zálohy 90 dní', 'WAF + CDN'], 'en' => ['50 sites', '200 GB NVMe', '500 mailboxes', 'Dedicated PHP workers', '90-day backups', 'WAF + CDN']]],         ]);

        // configurator ("Tarif na míru"): one base plan, every parameter is a priced option (Nastavení → Slevy a doplňky sets the unit prices)
        $this->product('web-custom', 'web', 'ispconfig', 'subscription', ['cs' => 'Webhosting na míru', 'en' => 'Custom web hosting'], ['cs' => 'Sestavte si tarif přesně podle projektu: weby, prostor, schránky, databáze a zálohy platíte jen v množství, které potřebujete.', 'en' => 'Build the plan your project needs: pay for exactly the sites, storage, mailboxes, databases and backups you use.'], 12, ['persona' => 'web', 'builder' => true, 'chips' => ['NVMe RAID10', 'PHP 8.1–8.4', 'HTTP/3'], 'addon_products' => ['ssl', 'cdn', 'backup-plus', 'mail-hosting']], [
            ['custom', ['cs' => 'Na míru', 'en' => 'Custom'], 4900, 49000, 199, 1990, false, 'standard', ['sites' => 1, 'nvme_gb' => 5, 'php_workers' => 2, 'php_memory_mb' => 512, 'mailboxes' => 3, 'databases' => 1, 'backup_days' => 7, 'backup_generations' => 7, 'traffic_gb' => 500, 'ssh' => false, 'staging' => false, 'waf' => 'basic', 'php_versions' => ['8.2', '8.3', '8.4'], 'inodes' => 250000, 'cron_concurrency' => 1], [], ['cs' => 'Základ, ke kterému si přidáte jen to, co potřebujete.', 'en' => 'A base you extend with only what you need.'], ['cs' => ['1 web', '5 GB NVMe', '3 schránky', '1 databáze', 'Zálohy 7 dní', 'Certifikát zdarma'], 'en' => ['1 site', '5 GB NVMe', '3 mailboxes', '1 database', '7-day backups', 'Free certificate']]],
        ]);

        $this->product('wordpress', 'managed', 'aapanel', 'subscription', ['cs' => 'Managed WordPress', 'en' => 'Managed WordPress'], ['cs' => 'WordPress a WooCommerce se správou, aktualizacemi a dohledem.', 'en' => 'WordPress and WooCommerce with updates, monitoring and tuning.'], 20, ['persona' => 'eshop', 'responsibility_matrix' => 'docs/support/managed-responsibility.md', 'addon_products' => ['ssl', 'cdn']], [
            ['managed-wp', ['cs' => 'Managed WP', 'en' => 'Managed WP'], 69000, 690000, 2900, 29000, true, 'business', ['sites' => 1, 'nvme_gb' => 40, 'php_workers' => 8, 'php_memory_mb' => 2048, 'object_cache' => 'redis', 'backup_days' => 30, 'backup_frequency' => '6h', 'monitoring' => true, 'updates' => 'core+plugins staged', 'support' => 'chat 30 min', 'staging' => true], []],
            ['managed-woo', ['cs' => 'Managed WooCommerce', 'en' => 'Managed WooCommerce'], 149000, 1490000, 6200, 62000, false, 'business', ['sites' => 1, 'nvme_gb' => 120, 'php_workers' => 16, 'php_memory_mb' => 4096, 'object_cache' => 'redis', 'backup_days' => 30, 'backup_frequency' => '1h', 'monitoring' => true, 'updates' => 'core+plugins staged', 'support' => 'priority 10 min', 'staging' => true, 'dedicated_db' => true], []],
        ]);

        // e-shop hosting (public page onhost-svc-web.js `eshop`): managed WooCommerce/PrestaShop with per-shop workers
        $this->product('eshop', 'managed', 'aapanel', 'subscription', ['cs' => 'E-shop hosting', 'en' => 'E-shop hosting'], ['cs' => 'WooCommerce, PrestaShop a Shopware s objektovou cache, WAF a zálohami několikrát denně.', 'en' => 'WooCommerce, PrestaShop and Shopware with object cache, WAF and backups several times a day.'], 22, ['persona' => 'eshop', 'chips' => ['Redis', 'WAF', 'Zálohy 4× denně'], 'addon_products' => ['ssl', 'cdn']], [
            ['shop-start', ['cs' => 'Shop Start', 'en' => 'Shop Start'], 39000, 390000, 1590, 15900, false, 'standard', ['products' => 1000, 'nvme_gb' => 60, 'php_workers' => 4, 'php_memory_mb' => 1024, 'object_cache' => 'redis', 'backup_frequency' => '6h', 'backup_days' => 30, 'waf' => 'basic', 'staging' => true, 'mailboxes' => 20, 'databases' => 5], []],
            ['shop-growth', ['cs' => 'Shop Growth', 'en' => 'Shop Growth'], 99000, 990000, 3990, 39900, true, 'business', ['products' => 20000, 'nvme_gb' => 200, 'php_workers' => 12, 'php_memory_mb' => 2048, 'object_cache' => 'redis', 'backup_frequency' => '1h', 'backup_days' => 30, 'waf' => 'pro', 'staging' => true, 'mailboxes' => 100, 'databases' => 20, 'support' => 'chat 30 min'], []],
            ['shop-peak', ['cs' => 'Shop Peak', 'en' => 'Shop Peak'], 249000, 2490000, 9990, 99900, false, 'ha', ['products' => 999999, 'nvme_gb' => 600, 'php_workers' => 24, 'php_workers_dedicated' => true, 'php_memory_mb' => 4096, 'object_cache' => 'redis', 'backup_frequency' => '15m', 'backup_days' => 90, 'waf' => 'pro + CDN + DDoS', 'staging' => true, 'mailboxes' => 500, 'databases' => 100, 'dedicated_db' => true, 'support' => 'priority 10 min'], []],
        ]);

        $this->product('vps', 'cloud', 'proxmox', 'hourly', ['cs' => 'VPS', 'en' => 'VPS'], ['cs' => 'KVM na Proxmox VE, hodinové účtování s měsíčním stropem, IPv6 v ceně.', 'en' => 'KVM on Proxmox VE, hourly billing with a monthly cap, IPv6 included.'], 30, ['persona' => 'sysadmin', 'cpu_class' => 'shared', 'images' => ['debian-13', 'ubuntu-24.04', 'ubuntu-22.04', 'rocky-9', 'alma-9'], 'addon_products' => ['backup-plus', 'backup-hourly', 'cdn']], [
            ['compute-2', ['cs' => 'Compute 2', 'en' => 'Compute 2'], 24900, 249000, 990, 9900, false, 'standard', ['vcpu' => 2, 'cpu_class' => 'shared', 'ram_mb' => 4096, 'nvme_gb' => 80, 'traffic_tb' => 5, 'ipv6' => '/64', 'ipv4' => 'addon', 'snapshots' => 3, 'backup' => 'addon', 'console' => 'noVNC/serial'], []],
            ['compute-4', ['cs' => 'Compute 4', 'en' => 'Compute 4'], 44900, 449000, 1790, 17900, true, 'standard', ['vcpu' => 4, 'cpu_class' => 'shared', 'ram_mb' => 8192, 'nvme_gb' => 160, 'traffic_tb' => 8, 'ipv6' => '/64', 'ipv4' => 'addon', 'snapshots' => 5, 'backup' => 'addon', 'console' => 'noVNC/serial'], []],
            ['compute-8', ['cs' => 'Compute 8', 'en' => 'Compute 8'], 84900, 849000, 3390, 33900, false, 'standard', ['vcpu' => 8, 'cpu_class' => 'performance', 'ram_mb' => 16384, 'nvme_gb' => 320, 'traffic_tb' => 12, 'ipv6' => '/64', 'ipv4' => 'addon', 'snapshots' => 10, 'backup' => 'addon', 'console' => 'noVNC/serial'], []],
            ['compute-16', ['cs' => 'Compute 16', 'en' => 'Compute 16'], 169000, 1690000, 6790, 67900, false, 'standard', ['vcpu' => 16, 'cpu_class' => 'performance', 'ram_mb' => 32768, 'nvme_gb' => 640, 'traffic_tb' => 20, 'ipv6' => '/64', 'ipv4' => 'addon', 'snapshots' => 10, 'backup' => 'addon', 'console' => 'noVNC/serial'], []],
        ], hourly: true);

        $this->product('vds', 'cloud', 'proxmox', 'hourly', ['cs' => 'VDS', 'en' => 'VDS'], ['cs' => 'Dedikovaná (pinned) jádra AMD EPYC, garantovaná RAM a IO třída.', 'en' => 'Dedicated (pinned) AMD EPYC cores, guaranteed RAM and IO class.'], 31, ['persona' => 'sysadmin', 'cpu_class' => 'dedicated'], [
            ['vds-4', ['cs' => 'VDS 4', 'en' => 'VDS 4'], 129000, 1290000, 5190, 51900, false, 'business', ['vcpu' => 4, 'cpu_class' => 'dedicated', 'ram_mb' => 16384, 'nvme_gb' => 240, 'traffic_tb' => 15, 'ipv6' => '/64', 'ipv4' => 1, 'snapshots' => 10, 'backup' => 'daily 7d', 'io_class' => 'reserved'], []],
            ['vds-8', ['cs' => 'VDS 8', 'en' => 'VDS 8'], 249000, 2490000, 9990, 99900, true, 'business', ['vcpu' => 8, 'cpu_class' => 'dedicated', 'ram_mb' => 32768, 'nvme_gb' => 480, 'traffic_tb' => 25, 'ipv6' => '/64', 'ipv4' => 1, 'snapshots' => 10, 'backup' => 'daily 7d', 'io_class' => 'reserved'], []],
            ['vds-16', ['cs' => 'VDS 16', 'en' => 'VDS 16'], 490000, 4900000, 19690, 196900, false, 'ha', ['vcpu' => 16, 'cpu_class' => 'dedicated', 'ram_mb' => 65536, 'nvme_gb' => 960, 'traffic_tb' => 40, 'ipv6' => '/64', 'ipv4' => 1, 'snapshots' => 20, 'backup' => 'daily 14d', 'io_class' => 'reserved'], []],
        ], hourly: true);

        $this->product('game', 'game', 'pterodactyl', 'daily', ['cs' => 'Herní server', 'en' => 'Game server'], ['cs' => 'Minecraft, CS2, Rust, Palworld, Valheim, DayZ a další s konzolí, mody a zálohami.', 'en' => 'Minecraft, CS2, Rust, Palworld, Valheim, DayZ and more with console, mods and backups.'], 40, ['persona' => 'gamer', 'hardware_class' => 'AMD Ryzen 9 7950X3D / 9950X, NVMe', 'eggs' => ['minecraft-paper', 'minecraft-spigot', 'minecraft-purpur', 'minecraft-vanilla', 'minecraft-forge', 'minecraft-sponge', 'minecraft-bungeecord', 'minecraft-bedrock', '7-days-to-die', 'arma-reforger', 'cs2', 'dayz', 'enshrouded', 'factorio', 'hytale', 'palworld', 'project-zomboid', 'rust-autowipe', 'satisfactory', 'terraria', 'v-rising', 'valheim']], [
            // the public configurator's base (audit §5v): the customer picks a game and sizes RAM / vCPU / NVMe / backups / ports / databases; the base covers the minimum, the rest is priced per unit (gameOptions)
            ['game-custom', ['cs' => 'Herní server na míru', 'en' => 'Custom game server'], 4900, 49000, 199, 1990, true, 'standard', ['ram_mb' => 1024, 'vcpu' => 1, 'nvme_gb' => 10, 'slots' => 'podle RAM a hry', 'backups' => 1, 'allocations' => 1, 'databases' => 0, 'ddos' => 'upstream L3/L4', 'console' => 'live websocket', 'sftp' => true], ['pids' => 400, 'cpu_pct' => 100], ['cs' => 'Vyberte hru a nastavte si výkon posuvníky.', 'en' => 'Pick a game and size the server with sliders.']],
            ['game-8', ['cs' => 'Game 8 GB', 'en' => 'Game 8 GB'], 34900, 349000, 1390, 13900, false, 'standard', ['ram_mb' => 8192, 'vcpu' => 3, 'nvme_gb' => 60, 'slots' => 'neomezeno (RAM)', 'backups' => 5, 'allocations' => 2, 'databases' => 2, 'ddos' => 'upstream L3/L4', 'console' => 'live websocket', 'sftp' => true], ['pids' => 500, 'cpu_pct' => 300]],
            ['game-16', ['cs' => 'Game 16 GB', 'en' => 'Game 16 GB'], 64900, 649000, 2590, 25900, false, 'standard', ['ram_mb' => 16384, 'vcpu' => 5, 'nvme_gb' => 120, 'slots' => 'neomezeno (RAM)', 'backups' => 10, 'allocations' => 4, 'databases' => 4, 'ddos' => 'upstream L3/L4', 'console' => 'live websocket', 'sftp' => true], ['pids' => 800, 'cpu_pct' => 500]],
            ['game-32', ['cs' => 'Game 32 GB', 'en' => 'Game 32 GB'], 119000, 1190000, 4790, 47900, false, 'business', ['ram_mb' => 32768, 'vcpu' => 8, 'nvme_gb' => 240, 'slots' => 'neomezeno (RAM)', 'backups' => 20, 'allocations' => 8, 'databases' => 8, 'ddos' => 'upstream L3/L4 + game profiles', 'console' => 'live websocket', 'sftp' => true], ['pids' => 1200, 'cpu_pct' => 800]],
        ], daily: true);

        $this->product('mail', 'mail', 'ispconfig', 'subscription', ['cs' => 'Mailhosting', 'en' => 'Mail hosting'], ['cs' => 'Schránky, aliasy a relay v oddělené reputační doméně, DKIM/SPF/DMARC.', 'en' => 'Mailboxes, aliases and relay in a separate reputation domain, DKIM/SPF/DMARC.'], 50, ['persona' => 'business'], [
            ['mail-business', ['cs' => 'Mail Business', 'en' => 'Mail Business'], 9900, 99000, 390, 3900, true, 'standard', ['mailboxes' => 10, 'quota_gb_per_mailbox' => 10, 'aliases' => 50, 'domains' => 3, 'relay_per_hour' => 500, 'spam_filter' => 'rspamd', 'imap' => true, 'backup_days' => 14], []],
            ['mail-enterprise', ['cs' => 'Mail Enterprise', 'en' => 'Mail Enterprise'], 29900, 299000, 1190, 11900, false, 'business', ['mailboxes' => 100, 'quota_gb_per_mailbox' => 25, 'aliases' => 500, 'domains' => 20, 'relay_per_hour' => 5000, 'spam_filter' => 'rspamd + policies', 'imap' => true, 'backup_days' => 30, 'dedicated_outbound_ip' => true], []],
        ]);

        $this->product('apps', 'apps', 'kubernetes', 'subscription', ['cs' => 'ONhost Apps', 'en' => 'ONhost Apps'], ['cs' => 'Git push → build → deploy pro Node.js, Python, Go, Rust, PHP na hardened RKE2.', 'en' => 'Git push → build → deploy for Node.js, Python, Go, Rust, PHP on hardened RKE2.'], 60, ['persona' => 'developer', 'runtimes' => ['node-22', 'node-20', 'python-3.12', 'go-1.23', 'rust-1.82', 'php-8.3', 'bun-1', 'deno-2', 'static']], [
            ['apps-shared', ['cs' => 'Apps Shared', 'en' => 'Apps Shared'], 17900, 179000, 690, 6900, true, 'standard', ['cpu_request' => '0.5', 'cpu_limit' => '2', 'ram_mb' => 1024, 'replicas' => 1, 'ephemeral_gb' => 5, 'builds_per_day' => 50, 'build_minutes' => 300, 'workers' => 2, 'cron' => 5, 'custom_domains' => 5, 'tls' => 'Let’s Encrypt', 'logs_retention_days' => 7], ['pids' => 256, 'egress' => 'default-deny + allowlist']],
            ['apps-business', ['cs' => 'Apps Business', 'en' => 'Apps Business'], 59000, 590000, 2290, 22900, false, 'ha', ['cpu_request' => '2', 'cpu_limit' => '4', 'ram_mb' => 4096, 'replicas' => 2, 'ephemeral_gb' => 20, 'builds_per_day' => 200, 'build_minutes' => 1500, 'workers' => 10, 'cron' => 20, 'custom_domains' => 25, 'tls' => 'Let’s Encrypt', 'logs_retention_days' => 30, 'zero_downtime' => true, 'private_network' => true], ['pids' => 1024, 'egress' => 'default-deny + allowlist']],
        ], state: 'draft'); // Kubernetes is not one of the operated executors (aaPanel, ISPConfig, Pterodactyl, Proxmox, registrars): kept in the catalogue as a draft

        $this->product('database', 'data', 'proxmox', 'subscription', ['cs' => 'Managed databáze', 'en' => 'Managed database'], ['cs' => 'PostgreSQL, MariaDB a Redis jako služba — single-tenant KVM a zálohy.', 'en' => 'PostgreSQL, MariaDB and Redis as a service — single-tenant KVM and backups.'], 70, ['persona' => 'developer', 'engines' => ['postgresql-16', 'mariadb-11.4', 'redis-7']], [
            ['db-s', ['cs' => 'DB S', 'en' => 'DB S'], 19900, 199000, 790, 7900, false, 'standard', ['vcpu' => 2, 'ram_mb' => 4096, 'nvme_gb' => 40, 'connections' => 100, 'pitr_days' => 7, 'backup_days' => 14, 'ha' => false, 'external_access' => 'off by default (TLS + allowlist)'], []],
            ['db-m', ['cs' => 'DB M', 'en' => 'DB M'], 49900, 499000, 1990, 19900, true, 'business', ['vcpu' => 4, 'ram_mb' => 16384, 'nvme_gb' => 160, 'connections' => 300, 'pitr_days' => 14, 'backup_days' => 30, 'ha' => false, 'external_access' => 'off by default (TLS + allowlist)'], []],
        ]);

        $this->product('ai-endpoint', 'ai', 'kubernetes', 'metered', ['cs' => 'AI Endpoint', 'en' => 'AI Endpoint'], ['cs' => 'OpenAI-kompatibilní endpoint nad vLLM; účtováno po tokenech/GPU-sekundách.', 'en' => 'OpenAI-compatible endpoint on vLLM; billed per tokens/GPU-seconds.'], 80, ['persona' => 'ai', 'role_gate' => 'AI Act §18 review before own-branded GPAI'], [
            ['ai-metered', ['cs' => 'AI Metered', 'en' => 'AI Metered'], 0, 0, 0, 0, true, 'standard', ['models' => ['allowlisted'], 'rate_limit_rpm' => 600, 'token_price_per_1k_minor' => ['CZK' => 90, 'EUR' => 4], 'gpu_second_price_minor' => ['CZK' => 6, 'EUR' => 1]], []],
        ], state: 'draft');

        $this->product('object-storage', 'data', null, 'metered', ['cs' => 'Object storage', 'en' => 'Object storage'], ['cs' => 'S3 kompatibilní úložiště (připravováno — bez executoru zatím není prodejné).', 'en' => 'S3-compatible storage (in preparation — not sellable without an executor).'], 90, ['persona' => 'business'], [], state: 'draft');

        $this->addonProducts();
        $this->definedProducts();
        $this->options();
        $this->webOptions();
        $this->gameOptions();
        $this->tlds();
        $this->promos();
    }

    /**
     * @param  list<array{0:string,1:array,2:int,3:int,4:int,5:int,6:bool,7:string,8:array,9:array}>  $plans  key, name, CZK month, CZK year, EUR month, EUR year, highlighted, sla, entitlements, limits
     */
    private function product(string $key, string $family, ?string $executor, string $billing, array $name, array $description, int $sort, array $meta, array $plans, bool $hourly = false, bool $daily = false, string $state = 'active'): void
    {
        $product = Product::query()->updateOrCreate(['key' => $key], [
            'family' => $family, 'name' => $name, 'description' => $description, 'executor' => $executor,
            'billing_model' => $billing, 'state' => $state, 'sort' => $sort, 'meta' => $meta,
        ]);
        $order = 0;
        foreach ($plans as $row) {
            [$planKey, $planName, $czkMonth, $czkYear, $eurMonth, $eurYear, $highlighted, $sla, $entitlements, $limits] = $row;
            $description = $row[10] ?? null; // optional {cs, en} tagline
            $features = $row[11] ?? []; // optional {cs: [...], en: [...]} bullets; otherwise generated from the entitlements
            $plan = Plan::query()->updateOrCreate(['product_id' => $product->id, 'key' => $planKey], [
                'name' => $planName, 'description' => $description, 'sla_class' => $sla, 'highlighted' => $highlighted, 'state' => 'active', 'sort' => ++$order * 10,
            ]);
            $version = PlanVersion::query()->firstOrCreate(['plan_id' => $plan->id, 'version' => 1], [
                'entitlements' => $entitlements, 'limits' => $limits, 'features' => $features, 'effective_from' => now()->subDay(),
            ]);
            // a version somebody holds is a contract: a re-run of the installer must not rewrite what customers bought (prices included);
            // a change reaches a running catalogue as a new version (PlanVersioning, CatalogRevisions / onhost:catalog:revise)
            if (Service::query()->where('plan_version_id', $version->id)->exists() || Subscription::query()->where('plan_version_id', $version->id)->exists()) {
                continue;
            }
            $version->forceFill(['entitlements' => $entitlements, 'limits' => $limits, 'features' => $features])->save();
            foreach ([['CZK', $czkMonth, $czkYear], ['EUR', $eurMonth, $eurYear]] as [$currency, $month, $year]) {
                $this->price($version->id, $currency, 'month', $month, $month);
                if ($year > 0) {
                    $this->price($version->id, $currency, 'year', $year, $year);
                }
                if ($hourly && $month > 0) {
                    $this->price($version->id, $currency, 'hour', (int) ceil($month / 720), (int) ceil($month / 720), cap: $month);
                }
                if ($daily && $month > 0) {
                    $this->price($version->id, $currency, 'day', (int) ceil($month / 30), (int) ceil($month / 30), cap: $month);
                }
            }
        }
    }

    private function price(string $versionId, string $currency, string $period, int $amount, int $renewal, ?int $cap = null, int $setup = 0): void
    {
        Price::query()->updateOrCreate(['plan_version_id' => $versionId, 'currency' => $currency, 'period' => $period], [
            'amount_minor' => $amount, 'renewal_amount_minor' => $renewal, 'setup_minor' => $setup, 'monthly_cap_minor' => $cap,
            'effective_from' => now()->subDay(), 'state' => 'active',
        ]);
    }

    private function addonProducts(): void
    {
        $this->product('ipv4', 'addon', 'proxmox', 'subscription', ['cs' => 'Dedikovaná IPv4', 'en' => 'Dedicated IPv4'], ['cs' => 'IPv4 je vzácný zdroj; IPv6 je v ceně každé služby.', 'en' => 'IPv4 is a scarce resource; IPv6 is included with every service.'], 200, ['scarce' => true], [
            ['ipv4-1', ['cs' => '1× IPv4', 'en' => '1× IPv4'], 4900, 49000, 199, 1990, false, 'standard', ['addresses' => 1, 'rdns' => true], []],
        ]);
        $this->product('backup-plus', 'addon', 'pbs', 'subscription', ['cs' => 'Zálohy VPS (PBS)', 'en' => 'VPS backups (PBS)'], ['cs' => 'Denní zálohy na Proxmox Backup Server s offsite kopií a restore testem.', 'en' => 'Daily backups to Proxmox Backup Server with offsite copy and restore test.'], 201, [], [
            ['backup-7', ['cs' => '7 denních + 4 týdenní', 'en' => '7 daily + 4 weekly'], 4900, 49000, 199, 1990, true, 'standard', ['daily' => 7, 'weekly' => 4, 'monthly' => 0, 'offsite' => true, 'restore_test' => 'monthly'], []],
            ['backup-30', ['cs' => '30 denních + 6 měsíčních', 'en' => '30 daily + 6 monthly'], 12900, 129000, 519, 5190, false, 'standard', ['daily' => 30, 'weekly' => 4, 'monthly' => 6, 'offsite' => true, 'restore_test' => 'monthly'], []],
        ]);
        // checkout upsells of the public web (Onhost.dc.html `coUpsells`): same prices as the prototype shows in the order summary
        $this->product('backup-hourly', 'addon', 'pbs', 'subscription', ['cs' => 'Hodinové zálohy', 'en' => 'Hourly backups'], ['cs' => 'Snapshot každou hodinu, 30 dní historie, obnova jedním klikem.', 'en' => 'Hourly snapshots, 30 days of history, one-click restore.'], 92, ['upsell' => 'backup'], [
            ['hourly-30', ['cs' => 'Hodinové zálohy', 'en' => 'Hourly backups'], 7900, 79000, 319, 3190, true, 'standard', ['interval_hours' => 1, 'retention_days' => 30, 'offsite' => true], []],
        ]);
        // SSL certificates and CDN (public pages `ssl`, `cdn`): yearly certificates, monthly CDN/WAF tiers
        $this->product('ssl', 'addon', null, 'subscription', ['cs' => 'SSL certifikáty', 'en' => 'SSL certificates'], ['cs' => 'DV zdarma u každého hostingu; OV a wildcard certifikáty s pojištěním.', 'en' => 'DV free with every hosting plan; OV and wildcard certificates with a warranty.'], 94, ['billing_unit' => 'year', 'upsell' => 'ssl'], [
            ['ov-business', ['cs' => 'OV Business', 'en' => 'OV Business'], 12420, 149000, 500, 6000, false, 'standard', ['validation' => 'OV', 'warranty' => '1 mil. $', 'issuance' => 'do 2 dnů', 'wildcard' => false], []],
            ['wildcard-ov', ['cs' => 'Wildcard OV', 'en' => 'Wildcard OV'], 33250, 399000, 1340, 16080, false, 'standard', ['validation' => 'OV', 'warranty' => '1,5 mil. $', 'issuance' => 'do 2 dnů', 'wildcard' => true], []],
        ], state: 'draft'); // ordering a paid OV certificate at a reseller is not implemented; free DV certificates come with every hosting plan
        $this->product('cdn', 'addon', null, 'subscription', ['cs' => 'CDN a WAF', 'en' => 'CDN and WAF'], ['cs' => 'Anycast CDN s HTTP/3, WAF a ochranou proti botům před vaším webem.', 'en' => 'Anycast CDN with HTTP/3, WAF and bot protection in front of your site.'], 95, ['upsell' => 'cdn'], [
            ['cdn-start', ['cs' => 'CDN Start', 'en' => 'CDN Start'], 19000, 190000, 790, 7900, false, 'standard', ['traffic_tb' => 1, 'pops' => 14, 'waf' => 'basic', 'ddos' => 'L3/L4'], []],
            ['shield', ['cs' => 'Shield', 'en' => 'Shield'], 89000, 890000, 3590, 35900, true, 'business', ['traffic_tb' => 10, 'pops' => 14, 'waf' => 'custom rules', 'bot_management' => true, 'ddos' => 'L3/L4 + L7'], []],
            ['shield-max', ['cs' => 'Shield Max', 'en' => 'Shield Max'], 289000, 2890000, 11590, 115900, false, 'ha', ['traffic_tb' => 50, 'pops' => 14, 'waf' => 'custom rules', 'bot_management' => true, 'anycast' => '/24', 'ddos' => 'dedicated scrubbing'], []],
        ]);
        $this->product('anti-ddos-pro', 'addon', null, 'subscription', ['cs' => 'Anti-DDoS Pro', 'en' => 'Anti-DDoS Pro'], ['cs' => 'L7 filtr, herní profily a ochrana portů nad rámec základní mitigace.', 'en' => 'L7 filtering, game profiles and port protection beyond the baseline mitigation.'], 93, ['upsell' => 'ddos'], [
            ['pro', ['cs' => 'Anti-DDoS Pro', 'en' => 'Anti-DDoS Pro'], 14900, 149000, 599, 5990, true, 'standard', ['l7' => true, 'game_profiles' => true, 'capacity_gbps' => 1200], []],
        ], state: 'draft'); // nothing in the control plane applies an L7 profile or a port filter; baseline mitigation is the network's, not a product
        $this->product('mail-hosting', 'addon', 'ispconfig', 'subscription', ['cs' => 'E-mail hosting', 'en' => 'Mail hosting'], ['cs' => 'Schránky s antispamem, DKIM a DMARC k vaší doméně.', 'en' => 'Mailboxes with anti-spam, DKIM and DMARC for your domain.'], 94, ['upsell' => 'mail'], [
            ['basic', ['cs' => 'E-mail hosting', 'en' => 'Mail hosting'], 3900, 39000, 159, 1590, true, 'standard', ['mailboxes' => 5, 'quota_mb' => 5120, 'dkim' => true], []],
        ]);
    }

    /**
     * Products the code defines (CatalogRevisions::PRODUCTS, TASK-0022 limit-raise): created on a fresh install, never rewritten on
     * a running one — there the operator creates them with `php artisan onhost:catalog:revise --apply`, since this seeder is not part
     * of a deployment.
     */
    private function definedProducts(): void
    {
        foreach (array_keys(CatalogRevisions::PRODUCTS) as $key) {
            if (! Product::query()->where('key', $key)->exists()) {
                Product::query()->create(CatalogRevisions::productAttributes($key));
            }
        }
    }

    private function options(): void
    {
        $vps = Product::query()->where('key', 'vps')->firstOrFail();
        foreach ([
            ['vcpu', 'slider', ['cs' => 'vCPU navíc', 'en' => 'Extra vCPU'], 'vCPU', 0, 16, 1, 0, ['CZK' => 9900, 'EUR' => 399]],
            ['ram_gb', 'slider', ['cs' => 'RAM navíc', 'en' => 'Extra RAM'], 'GB', 0, 64, 1, 0, ['CZK' => 4900, 'EUR' => 199]],
            ['nvme_gb', 'slider', ['cs' => 'NVMe navíc', 'en' => 'Extra NVMe'], 'GB', 0, 2000, 10, 0, ['CZK' => 120, 'EUR' => 5]],
            ['ipv4', 'addon', ['cs' => 'Dedikovaná IPv4', 'en' => 'Dedicated IPv4'], 'ks', null, null, null, null, ['CZK' => 4900, 'EUR' => 199]],
            ['backup', 'select', ['cs' => 'Zálohy', 'en' => 'Backups'], null, null, null, null, null, ['CZK' => 4900, 'EUR' => 199]],
        ] as $i => [$key, $kind, $label, $unit, $min, $max, $step, $default, $unitPrice]) {
            ProductOption::query()->updateOrCreate(['product_id' => $vps->id, 'key' => $key], [
                'kind' => $kind, 'label' => $label, 'unit' => $unit, 'min' => $min, 'max' => $max, 'step' => $step, 'default_value' => $default,
                'price_per_unit_minor' => $unitPrice, 'sort' => ($i + 1) * 10,
                'choices' => $key === 'backup' ? [['key' => 'none', 'label' => 'Bez záloh', 'units' => 0], ['key' => 'backup-7', 'label' => '7 denních', 'units' => 1], ['key' => 'backup-30', 'label' => '30 denních', 'units' => 2.6]] : null,
                'meta' => ['cfg_key' => $key],
            ]);
        }
    }

    /**
     * Priced options of the web products: per-item add-ons of the fixed plans (extras on top of the plan) and the parameters
     * of the configurator product (absolute values, the base plan covers the minimum). Unit prices are what staff edit.
     */
    private function webOptions(): void
    {
        $sets = [
            'web-hosting' => [
                ['nvme_gb', 'slider', ['cs' => 'Prostor navíc', 'en' => 'Extra storage'], ['cs' => 'NVMe nad rámec tarifu', 'en' => 'NVMe beyond the plan'], 'GB', 0, 200, 10, 0, ['CZK' => 300, 'EUR' => 12], null],
                ['mailboxes', 'slider', ['cs' => 'Schránky navíc', 'en' => 'Extra mailboxes'], ['cs' => 'IMAP schránky nad rámec tarifu', 'en' => 'IMAP mailboxes beyond the plan'], 'ks', 0, 100, 1, 0, ['CZK' => 500, 'EUR' => 20], null],
                ['databases', 'slider', ['cs' => 'Databáze navíc', 'en' => 'Extra databases'], ['cs' => 'MariaDB databáze nad rámec tarifu', 'en' => 'MariaDB databases beyond the plan'], 'ks', 0, 20, 1, 0, ['CZK' => 1900, 'EUR' => 79], null],
                ['staging', 'addon', ['cs' => 'Staging prostředí', 'en' => 'Staging environment'], ['cs' => 'kopie webu na testovací adrese', 'en' => 'a copy of the site on a test address'], null, null, null, null, null, ['CZK' => 4900, 'EUR' => 199], ['key' => 'staging', 'value' => true]],
                ['ssh', 'addon', ['cs' => 'SSH a WP-CLI', 'en' => 'SSH and WP-CLI'], ['cs' => 'shell přístup k webu', 'en' => 'shell access to the site'], null, null, null, null, null, ['CZK' => 2900, 'EUR' => 119], ['key' => 'ssh', 'value' => true]],
                ['waf_cdn', 'addon', ['cs' => 'WAF + CDN', 'en' => 'WAF + CDN'], ['cs' => 'firewall aplikací a anycast cache', 'en' => 'application firewall and anycast cache'], null, null, null, null, null, ['CZK' => 9900, 'EUR' => 399], ['key' => 'waf', 'value' => 'pro + CDN']],
                ['dedicated_ipv4', 'addon', ['cs' => 'Dedikovaná IPv4', 'en' => 'Dedicated IPv4'], ['cs' => 'vlastní adresa pro TLS nebo mail', 'en' => 'your own address for TLS or mail'], null, null, null, null, null, ['CZK' => 4900, 'EUR' => 199], ['key' => 'ipv4', 'value' => 1]],
                ['priority_support', 'addon', ['cs' => 'Prioritní podpora', 'en' => 'Priority support'], ['cs' => 'reakce do 10 minut, 24/7', 'en' => '10-minute response, 24/7'], null, null, null, null, null, ['CZK' => 14900, 'EUR' => 599], ['key' => 'support', 'value' => 'priority 10 min']],
            ],
            'wordpress' => [
                ['nvme_gb', 'slider', ['cs' => 'Prostor navíc', 'en' => 'Extra storage'], ['cs' => 'NVMe nad rámec tarifu', 'en' => 'NVMe beyond the plan'], 'GB', 0, 500, 10, 0, ['CZK' => 300, 'EUR' => 12], null],
                ['dedicated_ipv4', 'addon', ['cs' => 'Dedikovaná IPv4', 'en' => 'Dedicated IPv4'], ['cs' => 'vlastní adresa pro TLS nebo mail', 'en' => 'your own address for TLS or mail'], null, null, null, null, null, ['CZK' => 4900, 'EUR' => 199], ['key' => 'ipv4', 'value' => 1]],
                ['waf_cdn', 'addon', ['cs' => 'WAF + CDN', 'en' => 'WAF + CDN'], ['cs' => 'firewall aplikací a anycast cache', 'en' => 'application firewall and anycast cache'], null, null, null, null, null, ['CZK' => 9900, 'EUR' => 399], ['key' => 'waf', 'value' => 'pro + CDN']],
                ['priority_support', 'addon', ['cs' => 'Prioritní podpora', 'en' => 'Priority support'], ['cs' => 'reakce do 10 minut, 24/7', 'en' => '10-minute response, 24/7'], null, null, null, null, null, ['CZK' => 14900, 'EUR' => 599], ['key' => 'support', 'value' => 'priority 10 min']],
            ],
            'eshop' => [
                ['nvme_gb', 'slider', ['cs' => 'Prostor navíc', 'en' => 'Extra storage'], ['cs' => 'NVMe nad rámec tarifu', 'en' => 'NVMe beyond the plan'], 'GB', 0, 1000, 10, 0, ['CZK' => 300, 'EUR' => 12], null],
                ['dedicated_ipv4', 'addon', ['cs' => 'Dedikovaná IPv4', 'en' => 'Dedicated IPv4'], ['cs' => 'vlastní adresa pro TLS nebo platební bránu', 'en' => 'your own address for TLS or the payment gateway'], null, null, null, null, null, ['CZK' => 4900, 'EUR' => 199], ['key' => 'ipv4', 'value' => 1]],
                ['priority_support', 'addon', ['cs' => 'Prioritní podpora', 'en' => 'Priority support'], ['cs' => 'reakce do 10 minut, 24/7', 'en' => '10-minute response, 24/7'], null, null, null, null, null, ['CZK' => 14900, 'EUR' => 599], ['key' => 'support', 'value' => 'priority 10 min']],
            ],
            'web-custom' => [
                ['sites', 'slider', ['cs' => 'Weby', 'en' => 'Sites'], ['cs' => 'domény a weby v jednom účtu', 'en' => 'domains and sites in one account'], 'ks', 1, 50, 1, 1, ['CZK' => 2900, 'EUR' => 119], ['key' => 'sites', 'mode' => 'absolute']],
                ['nvme_gb', 'slider', ['cs' => 'NVMe prostor', 'en' => 'NVMe storage'], ['cs' => 'soubory, databáze a pošta dohromady', 'en' => 'files, databases and mail together'], 'GB', 5, 500, 5, 5, ['CZK' => 250, 'EUR' => 10], ['key' => 'nvme_gb', 'mode' => 'absolute']],
                ['mailboxes', 'slider', ['cs' => 'E-mailové schránky', 'en' => 'Mailboxes'], ['cs' => 'IMAP schránky s antispamem a webmailem', 'en' => 'IMAP mailboxes with antispam and webmail'], 'ks', 3, 500, 1, 3, ['CZK' => 400, 'EUR' => 16], ['key' => 'mailboxes', 'mode' => 'absolute']],
                ['databases', 'slider', ['cs' => 'Databáze', 'en' => 'Databases'], ['cs' => 'MariaDB databáze s phpMyAdminem', 'en' => 'MariaDB databases with phpMyAdmin'], 'ks', 1, 50, 1, 1, ['CZK' => 1500, 'EUR' => 59], ['key' => 'databases', 'mode' => 'absolute']],
                ['backup_days', 'select', ['cs' => 'Zálohy', 'en' => 'Backups'], ['cs' => 'denní snapshoty souborů i databází', 'en' => 'daily file and database snapshots'], null, null, null, null, null, ['CZK' => 3900, 'EUR' => 159], ['key' => 'backup_days', 'values' => ['backup-7' => 7, 'backup-30' => 30, 'backup-90' => 90]]],
                ['staging', 'addon', ['cs' => 'Staging prostředí', 'en' => 'Staging environment'], ['cs' => 'kopie webu na testovací adrese', 'en' => 'a copy of the site on a test address'], null, null, null, null, null, ['CZK' => 4900, 'EUR' => 199], ['key' => 'staging', 'value' => true]],
                ['ssh', 'addon', ['cs' => 'SSH a WP-CLI', 'en' => 'SSH and WP-CLI'], ['cs' => 'shell přístup k webu', 'en' => 'shell access to the site'], null, null, null, null, null, ['CZK' => 2900, 'EUR' => 119], ['key' => 'ssh', 'value' => true]],
                ['waf_cdn', 'addon', ['cs' => 'WAF + CDN', 'en' => 'WAF + CDN'], ['cs' => 'firewall aplikací a anycast cache', 'en' => 'application firewall and anycast cache'], null, null, null, null, null, ['CZK' => 9900, 'EUR' => 399], ['key' => 'waf', 'value' => 'pro + CDN']],
                ['dedicated_ipv4', 'addon', ['cs' => 'Dedikovaná IPv4', 'en' => 'Dedicated IPv4'], ['cs' => 'vlastní adresa pro TLS nebo mail', 'en' => 'your own address for TLS or mail'], null, null, null, null, null, ['CZK' => 4900, 'EUR' => 199], ['key' => 'ipv4', 'value' => 1]],
                ['priority_support', 'addon', ['cs' => 'Prioritní podpora', 'en' => 'Priority support'], ['cs' => 'reakce do 10 minut, 24/7', 'en' => '10-minute response, 24/7'], null, null, null, null, null, ['CZK' => 14900, 'EUR' => 599], ['key' => 'support', 'value' => 'priority 10 min']],
            ],
        ];
        foreach ($sets as $productKey => $rows) {
            $product = Product::query()->where('key', $productKey)->firstOrFail();
            foreach ($rows as $i => [$key, $kind, $label, $desc, $unit, $min, $max, $step, $default, $unitPrice, $entitlement]) {
                ProductOption::query()->updateOrCreate(['product_id' => $product->id, 'key' => $key], [
                    'kind' => $kind, 'label' => $label, 'unit' => $unit, 'min' => $min, 'max' => $max, 'step' => $step, 'default_value' => $default,
                    'price_per_unit_minor' => $unitPrice, 'sort' => ($i + 1) * 10,
                    'choices' => $key === 'backup_days' ? [['key' => 'backup-7', 'label' => ['cs' => '7 dní', 'en' => '7 days'], 'units' => 0], ['key' => 'backup-30', 'label' => ['cs' => '30 dní', 'en' => '30 days'], 'units' => 1], ['key' => 'backup-90', 'label' => ['cs' => '90 dní', 'en' => '90 days'], 'units' => 2.5]] : null,
                    'meta' => array_filter(['cfg_key' => $key, 'desc' => $desc, 'entitlement' => $entitlement], fn ($v) => $v !== null),
                ]);
            }
        }
    }

    /**
     * The game configurator's priced parameters (audit §5v): absolute values, the `game-custom` base plan covers the minimum
     * (1 GB / 1 vCPU / 10 GB / 1 backup / 1 port), every unit above it is priced; the game's floors (config onhost.game.eggs)
     * lift the minimum per game. `scale` turns the slider's GB into the entitlement's MB.
     */
    private function gameOptions(): void
    {
        $product = Product::query()->where('key', 'game')->firstOrFail();
        $rows = [
            ['ram_gb', ['cs' => 'Operační paměť', 'en' => 'Memory'], ['cs' => 'RAM serveru; hra určuje minimum a počet slotů', 'en' => 'server RAM; the game sets the minimum and the slot count'], 'GB', 1, (int) config('onhost.game.configurator.max_ram_gb', 64), 1, 1, ['CZK' => 3500, 'EUR' => 140], ['key' => 'ram_mb', 'mode' => 'absolute', 'scale' => 1024]],
            ['vcpu', ['cs' => 'Jádra vCPU', 'en' => 'vCPU cores'], ['cs' => 'vyhrazený výkon procesoru', 'en' => 'dedicated processor share'], 'vCPU', 1, (int) config('onhost.game.configurator.max_vcpu', 8), 1, 1, ['CZK' => 6000, 'EUR' => 240], ['key' => 'vcpu', 'mode' => 'absolute']],
            ['nvme_gb', ['cs' => 'NVMe úložiště', 'en' => 'NVMe storage'], ['cs' => 'svět, mody a zálohy', 'en' => 'world, mods and backups'], 'GB', 10, 500, 10, 10, ['CZK' => 150, 'EUR' => 6], ['key' => 'nvme_gb', 'mode' => 'absolute']],
            ['backups', ['cs' => 'Zálohy', 'en' => 'Backups'], ['cs' => 'počet uchovaných záloh v panelu', 'en' => 'backups kept in the panel'], 'ks', 1, 30, 1, 1, ['CZK' => 900, 'EUR' => 36], ['key' => 'backups', 'mode' => 'absolute']],
            ['allocations', ['cs' => 'Porty', 'en' => 'Ports'], ['cs' => 'síťové porty (další hry, pluginy, RCON)', 'en' => 'network ports (extra games, plugins, RCON)'], 'ks', 1, 10, 1, 1, ['CZK' => 1500, 'EUR' => 60], ['key' => 'allocations', 'mode' => 'absolute']],
            ['databases', ['cs' => 'Databáze', 'en' => 'Databases'], ['cs' => 'MySQL databáze pro pluginy', 'en' => 'MySQL databases for plugins'], 'ks', 0, 10, 1, 0, ['CZK' => 1900, 'EUR' => 79], ['key' => 'databases', 'mode' => 'absolute']],
        ];
        foreach ($rows as $i => [$key, $label, $desc, $unit, $min, $max, $step, $default, $unitPrice, $entitlement]) {
            ProductOption::query()->updateOrCreate(['product_id' => $product->id, 'key' => $key], [
                'kind' => 'slider', 'label' => $label, 'unit' => $unit, 'min' => $min, 'max' => $max, 'step' => $step, 'default_value' => $default,
                'price_per_unit_minor' => $unitPrice, 'sort' => ($i + 1) * 10, 'choices' => null,
                'meta' => ['cfg_key' => $key, 'desc' => $desc, 'entitlement' => $entitlement],
            ]);
        }
    }

    private function tlds(): void
    {
        $tlds = [
            // tld, periods, default, transfer, schema, nsset, idn, grace, redemption, CZK reg/renew/transfer, EUR reg/renew/transfer, cost CZK
            ['cz', [1, 2, 3, 4, 5, 6, 7, 8, 9, 10], 1, 'sync', 'cz', true, false, 30, 30, [17900, 17900, 0], [729, 729, 0], 14500],
            ['sk', [1, 2, 3, 4, 5, 6, 7, 8, 9, 10], 1, 'async', 'sk', false, false, 40, 0, [34900, 34900, 34900], [1429, 1429, 1429], 31500],
            ['eu', [1, 2, 3, 4, 5, 6, 7, 8, 9, 10], 1, 'sync', 'eu', false, true, 40, 0, [22900, 22900, 22900], [929, 929, 929], 16000],
            ['com', [1, 2, 3, 5, 10], 1, 'async', 'generic', false, false, 0, 30, [37900, 37900, 37900], [1549, 1549, 1549], 34200],
            ['net', [1, 2, 3, 5, 10], 1, 'async', 'generic', false, false, 0, 30, [34900, 34900, 34900], [1419, 1419, 1419], 26000],
            ['org', [1, 2, 3, 5, 10], 1, 'async', 'generic', false, false, 0, 30, [33900, 33900, 33900], [1379, 1379, 1379], 25000],
            ['io', [1, 2, 3, 5], 1, 'async', 'generic', false, false, 0, 30, [129000, 129000, 129000], [5290, 5290, 5290], 106900],
            ['dev', [1, 2, 3, 5], 1, 'async', 'generic', false, false, 0, 30, [44900, 44900, 44900], [1839, 1839, 1839], 39600],
            ['gg', [1, 2, 3, 5], 1, 'async', 'generic', false, false, 0, 30, [199000, 199000, 199000], [8150, 8150, 8150], 180300],
            ['pl', [1, 2, 3], 1, 'async', 'pl', false, false, 30, 0, [39900, 39900, 39900], [1619, 1619, 1619], 30000],
        ];
        foreach ($tlds as [$tld, $periods, $default, $transfer, $schema, $nsset, $idn, $grace, $redemption, $czk, $eur, $cost]) {
            TldPolicy::query()->updateOrCreate(['tld' => $tld], [
                'registrar_provider' => 'auto', 'registrable' => true, 'periods' => $periods, 'default_period' => $default,
                'transfer_mode' => $transfer, 'contact_schema' => $schema, 'nsset_required' => $nsset, 'dnssec_supported' => true,
                'idn' => $idn, 'grace_days' => $grace, 'redemption_days' => $redemption, 'async_sla_hours' => $transfer === 'sync' ? 1 : 120,
                'registry_terms_url' => match ($tld) {
                    'cz' => 'https://www.nic.cz/page/314/', 'sk' => 'https://sk-nic.sk/pravidla/', 'eu' => 'https://eurid.eu/en/other-infomation/document-repository/', default => 'https://www.icann.org/resources/pages/registrant-rights-responsibilities'
                },
                'registrar_terms_url' => (string) config('onhost.domains.terms_url', '/dokumenty/podminky-registrace-domen'),
            ]);
            // wholesale price at the first registrar (no price API there): staff keep it current in Nastavení systému → Registrátoři domén
            RegistrarTldCost::query()->firstOrCreate(['registrar_provider' => 'wedos', 'tld' => $tld], [
                'currency' => 'CZK', 'register_minor' => $cost, 'renew_minor' => $cost, 'transfer_minor' => $cost, 'restore_minor' => $cost * 5, 'source' => 'seed', 'fetched_at' => now(),
            ]);
            foreach ([['CZK', $czk, $cost, 'CZK'], ['EUR', $eur, null, null]] as [$currency, $p, $c, $cc]) {
                DomainPrice::query()->updateOrCreate(['tld' => $tld, 'currency' => $currency, 'version' => 1], [
                    'register_minor' => $p[0], 'renew_minor' => $p[1], 'transfer_minor' => $p[2], 'restore_minor' => $p[1] * 5,
                    'cost_minor' => $c, 'cost_currency' => $cc, 'effective_from' => now()->subDay(),
                ]);
            }
        }
    }

    private function promos(): void
    {
        PromoCode::query()->updateOrCreate(['code' => 'ONHOST10'], [
            'kind' => 'percent', 'value' => 10, 'valid_from' => now()->subMonth(), 'valid_to' => now()->addYear(), 'applies_to' => ['web', 'managed', 'cloud', 'apps'],
            'first_period_only' => true, 'state' => 'active',
        ]);
    }
}
