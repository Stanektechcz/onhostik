<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Metering;

use Onhost\Domain\Provisioning\AutomationLedger;

/**
 * Ground truth for every number a plan sells (audit §5ad, brain card H278): a plan must never promise a limit the
 * backend does not enforce. `PlanPromises` used to believe a key was kept the moment its own text appeared anywhere
 * under `domains/`, `providers/`, `platform/` or `app/` — including the price-list wording itself
 * (`app/Http/Support/CatalogPresentation.php`). That let `products`, `connections` and `dedicated_outbound_ip` pass:
 * the price list names them, nothing else does.
 *
 * This class is not a scanner. It is a hand-verified table, written by reading `UsageWatch`, `UsageGuard` and every
 * adapter's `usage()`/`quotas()`/client-limit code once (2026-09-24) and recording what each entitlement or limit key
 * actually causes today. `PlanPromises::unapplied()` trusts it for every numeric (or otherwise non-fair-use) key
 * instead of asking "does the word appear somewhere" — the question that let a price-list mention count as proof.
 *
 * A row goes stale the moment an adapter changes; nothing here re-derives it. The guard test
 * (`tests/Feature/Catalog/PlanPromisesTest.php`) checks that every `enforced_only`/`hard` row names an enforcer and
 * every `measured` row names a source, so an empty `sources` entry fails loudly instead of silently trusting a label.
 */
final class MetricRegistry
{
    /** The platform reads the used/limit pair and acts on it continuously (`UsageWatch`, `service.usage.high`). */
    public const MEASURED = 'measured';

    /** The platform sets or checks the number at the moment it matters (provisioning, a create action, a drift check) but does not track a running percentage. */
    public const ENFORCED_ONLY = 'enforced_only';

    /** Nothing today measures or enforces this number; it is sold and not kept — see `reason`. */
    public const GAP = 'gap';

    public const HARD = 'hard';

    public const SOFT = 'soft';

    public const NONE = 'none';

    /**
     * @var array<string, array{
     *     entitlement: list<string>,
     *     unit: string,
     *     scope: string,
     *     limit_kind: string,
     *     families: list<string>,
     *     sources: array<string, string|null>,
     *     interval_minutes: int|null,
     *     drives_guard: bool,
     *     status: string,
     *     reason: string|null,
     *     kept_under?: array<string, string>,
     * }>
     */
    public const REGISTRY = [
        // ---- web hosting / managed (ISPConfig, aaPanel) --------------------------------------------------------
        'sites' => [
            'entitlement' => ['sites'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::HARD, 'families' => ['web', 'managed'],
            'sources' => ['ispconfig' => 'IspConfigWebProvider::ensureClient/updateClientLimits sets limit_web_domain from the client-wide sum (ClientAllowance)', 'aapanel' => 'ServiceSites::limit + the "sites" feature gate; aaPanel plans sell exactly 1 and are provisioned one site at a time'],
            'interval_minutes' => null, 'drives_guard' => true, 'status' => self::ENFORCED_ONLY, 'reason' => null,
        ],
        'nvme_gb' => [
            'entitlement' => ['nvme_gb'], 'unit' => 'bytes', 'scope' => 'service', 'limit_kind' => self::HARD, 'families' => ['web', 'managed', 'cloud', 'data', 'game'],
            'sources' => ['web/managed' => 'UsageWatch::measure() disk metric against panel quotas.disk_used_bytes/disk_limit_bytes, falling back to nvme_gb', 'cloud/data' => 'ServiceService dispatches ProvisionVpsWorkflow by executor=proxmox, not family, so the managed-database product (family data, executor proxmox) sizes its disk from nvme_gb exactly like a VPS/VDS: ProxmoxComputeProvider disk sized at provision/resize and drift-checked (disk_gb) — usage() has no used-bytes field, so no running percentage', 'game' => 'ProvisionGameServerWorkflow sizes the Pterodactyl allocation\'s disk_mb from nvme_gb'],
            'interval_minutes' => 60, 'drives_guard' => true, 'status' => self::MEASURED, 'reason' => null,
        ],
        'php_workers' => [
            'entitlement' => ['php_workers'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::HARD, 'families' => ['web', 'managed'],
            'sources' => ['ispconfig' => 'IspConfigWebProvider sets pm_max_children and drift-checks it', 'aapanel' => null],
            'interval_minutes' => null, 'drives_guard' => false, 'status' => self::GAP,
            'reason' => 'AaPanelWebProvider::configurePhp() (line ~162) returns applied=false — "one PHP-FPM pool per PHP version for the whole node; it is not resized per site". wordpress/eshop (aaPanel) sell it and nothing applies it there.',
        ],
        'php_memory_mb' => [
            'entitlement' => ['php_memory_mb'], 'unit' => 'bytes', 'scope' => 'service', 'limit_kind' => self::HARD, 'families' => ['web', 'managed'],
            'sources' => ['ispconfig' => 'IspConfigWebProvider writes php_memory_mb into the site\'s custom_php_ini memory_limit', 'service' => 'ServiceService::featureParams() reads the same entitlement to validate a php.set change against it'],
            'interval_minutes' => null, 'drives_guard' => false, 'status' => self::ENFORCED_ONLY, 'reason' => null,
        ],
        'mailboxes' => [
            'entitlement' => ['mailboxes'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::HARD, 'families' => ['web', 'managed', 'mail', 'addon'],
            'sources' => ['ispconfig' => 'IspConfigWebProvider::ensureClient sets limit_mailbox; mailbox.create runs ServiceService\'s generic $limit(\'mailboxes\',...) count check first', 'addon' => 'Addons::patch()\'s mail-hosting case adds the addon\'s mailboxes onto the parent service\'s own mailboxes entitlement, which is then enforced exactly like any other mailboxes sale'],
            'interval_minutes' => null, 'drives_guard' => true, 'status' => self::ENFORCED_ONLY, 'reason' => null,
        ],
        'databases' => [
            'entitlement' => ['databases'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::HARD, 'families' => ['web', 'managed', 'game'],
            'sources' => ['ispconfig' => 'IspConfigWebProvider::ensureClient sets limit_database; database.create runs the generic $limit(\'databases\',...) check first', 'pterodactyl' => 'PterodactylGameProvider sets feature_limits.databases at server create/update — the panel itself refuses more'],
            'interval_minutes' => null, 'drives_guard' => true, 'status' => self::ENFORCED_ONLY, 'reason' => null,
        ],
        'backup_days' => [
            'entitlement' => ['backup_days'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::SOFT, 'families' => ['web', 'managed', 'mail', 'data'],
            'sources' => [
                'scheduler' => 'BackupScheduler::tick() schedules web/managed (backup_schedule) and, under backups.compute, data; under backups.as_sold web/managed keep one backup a day for backup_days beyond their generations (BackupDailyKeepers), and data prunes by backup_days',
                'ispconfig-mail' => 'under mail.backup_retention a mail plan\'s new mailbox is created with backup_interval=daily/backup_copies=backup_days, a paid plan change applies it to the service\'s own mailboxes, existing ones via onhost:mail:backup-retention --apply (MailboxBackupPolicy); the doctor lists services behind',
            ],
            'interval_minutes' => 1440, 'drives_guard' => false, 'status' => self::GAP,
            // TASK-0024: kept for a family only while the owner's default-off rule that delivers it is on (isKept)
            'kept_under' => ['web' => 'backups.as_sold', 'managed' => 'backups.as_sold', 'mail' => 'mail.backup_retention', 'data' => 'backups.compute'],
            'reason' => 'kept only behind default-off rules (kept_under): web/managed keep backup_days as daily backups under backups.as_sold (otherwise pruned to the generation count), mail mailboxes get backup_copies=backup_days under mail.backup_retention (existing ones after onhost:mail:backup-retention --apply), managed databases (db-s/db-m, family data) are backed up and pruned by it under backups.compute. With the rules off nothing keeps the number as sold — see KNOWN_GAPS.',
        ],
        'backup_generations' => [
            'entitlement' => ['backup_generations'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::SOFT, 'families' => ['web'],
            'sources' => ['ispconfig' => 'IspConfigWebProvider forwards backup_generations as backup_copies', 'scheduler' => 'BackupScheduler trims backups beyond the generation count'],
            'interval_minutes' => 1440, 'drives_guard' => false, 'status' => self::ENFORCED_ONLY, 'reason' => null,
        ],
        'traffic_gb' => [
            'entitlement' => ['traffic_gb'], 'unit' => 'bytes_month', 'scope' => 'service', 'limit_kind' => self::HARD, 'families' => ['web', 'managed'],
            'sources' => ['ispconfig' => 'IspConfigTools reads trafficquota_get_by_user\'s this_month into UsageWatch', 'aapanel' => 'AaPanelTools sums the site\'s monthly traffic log into UsageWatch'],
            'interval_minutes' => 60, 'drives_guard' => true, 'status' => self::MEASURED, 'reason' => null,
        ],
        'inodes' => [
            'entitlement' => ['inodes'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::HARD, 'families' => ['web', 'managed'],
            'sources' => ['ispconfig' => 'IspConfigTools quota_get_by_user "files" into UsageWatch', 'aapanel' => 'AaPanelTools\' du/find file count into UsageWatch'],
            'interval_minutes' => 60, 'drives_guard' => true, 'status' => self::MEASURED, 'reason' => null,
        ],
        'cron_concurrency' => [
            'entitlement' => ['cron_concurrency'], 'unit' => 'count', 'scope' => 'organization', 'limit_kind' => self::HARD, 'families' => ['web'],
            'sources' => ['ispconfig' => 'IspConfigWebProvider::ensureClient/updateClientLimits sets limit_cron from the client-wide sum (ClientAllowance::SUMMED)'],
            'interval_minutes' => null, 'drives_guard' => true, 'status' => self::ENFORCED_ONLY,
            'reason' => null, // the "cron" FEATURE gate separately shows a default of 10 via the unsold ent['cron_jobs'] (ServiceFeatures.php ~183) — a cosmetic display mismatch, not an enforcement gap: nothing is sold under that key.
        ],
        'quota_gb_per_mailbox' => [
            'entitlement' => ['quota_gb_per_mailbox'], 'unit' => 'bytes', 'scope' => 'service', 'limit_kind' => self::HARD, 'families' => ['mail'],
            'sources' => ['ispconfig' => 'IspConfigWebProvider::ensureClient sets limit_mailquota from quota_gb_per_mailbox'],
            'interval_minutes' => null, 'drives_guard' => false, 'status' => self::ENFORCED_ONLY, 'reason' => null,
        ],
        'domains' => [
            'entitlement' => ['domains'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::HARD, 'families' => ['mail'],
            'sources' => ['ispconfig' => 'IspConfigWebProvider::ensureClient sets limit_maildomain from domains'],
            'interval_minutes' => null, 'drives_guard' => false, 'status' => self::ENFORCED_ONLY, 'reason' => null,
        ],
        'aliases' => [
            'entitlement' => ['aliases'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::HARD, 'families' => ['mail'],
            'sources' => ['ispconfig' => null],
            'interval_minutes' => null, 'drives_guard' => false, 'status' => self::GAP,
            'reason' => 'ISPConfig has no limit_mailalias field, and ServiceService\'s alias.create action never calls the generic $limit() count check that mailbox.create/database.create use — a mail plan\'s "aliases" number is never read outside the price list.',
        ],
        'dedicated_outbound_ip' => [
            'entitlement' => ['dedicated_outbound_ip'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::NONE, 'families' => ['mail'],
            'sources' => ['ispconfig' => null],
            'interval_minutes' => null, 'drives_guard' => false, 'status' => self::GAP,
            'reason' => 'Only app/Http/Support/CatalogPresentation.php ever names it; no workflow allocates a dedicated sending IP for mail-enterprise. No longer sold from catalogue revision 2026-09-honest-promises (owner decision 6, CatalogRevisions / onhost:catalog:revise); still carried by the versions customers hold — see PlanPromises::grandfatheredGaps().',
        ],
        'dedicated_db' => [
            'entitlement' => ['dedicated_db'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::NONE, 'families' => ['managed'],
            'sources' => ['aapanel' => null],
            'interval_minutes' => null, 'drives_guard' => false, 'status' => self::GAP,
            'reason' => 'Only CatalogPresentation names it (managed-woo, shop-peak); no workflow provisions a single-tenant database for these plans. No longer sold from catalogue revision 2026-09-honest-promises (owner decision 6, CatalogRevisions / onhost:catalog:revise); still carried by the versions customers hold — see PlanPromises::grandfatheredGaps().',
        ],
        // ---- e-shop -----------------------------------------------------------------------------------------
        'products' => [
            'entitlement' => ['products'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::NONE, 'families' => ['managed'],
            'sources' => ['aapanel' => null],
            'interval_minutes' => null, 'drives_guard' => false, 'status' => self::GAP,
            'reason' => 'Declared fair use (PlanPromises::FAIR_USE, owner decision 5): a recommended catalogue size worded "Doporučeno do N produktů", not a limit. A WooCommerce/PrestaShop product count is never read back from the store or capped by the platform; only the price list mentions the number. (The bare word "products" elsewhere — Product.php\'s $table, CatalogCommandHandler.php, PlacementService.php — is an unrelated identifier, not this entitlement, which is exactly the false "read" the old text scan produced.)',
        ],
        // ---- VPS / VDS (Proxmox) -----------------------------------------------------------------------------
        'vcpu' => [
            'entitlement' => ['vcpu'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::HARD, 'families' => ['cloud', 'data', 'game'],
            'sources' => ['proxmox' => 'ProxmoxComputeProvider sets qemu cores at provision/resize and drift-checks it against vcpu', 'pterodactyl' => 'ServiceService::provision raises a game server\'s cpu_pct to at least vcpu × 100, which PterodactylGameProvider sends as limits.cpu (a plan change re-sends only the version\'s own cpu_pct — follow-up, not a sale-time gap)'],
            'interval_minutes' => null, 'drives_guard' => true, 'status' => self::ENFORCED_ONLY, 'reason' => null,
        ],
        'ram_mb' => [
            'entitlement' => ['ram_mb'], 'unit' => 'bytes', 'scope' => 'service', 'limit_kind' => self::HARD, 'families' => ['cloud', 'data', 'game'],
            'sources' => ['proxmox' => 'ProxmoxComputeProvider sets qemu memory at provision/resize and drift-checks it; UsageWatch also measures live mem_bytes against ram_mb for cloud/game', 'pterodactyl' => 'ProvisionGameServerWorkflow sets memory_mb from ram_mb'],
            'interval_minutes' => 60, 'drives_guard' => true, 'status' => self::MEASURED, 'reason' => null,
        ],
        'traffic_tb' => [
            'entitlement' => ['traffic_tb'], 'unit' => 'bytes_month', 'scope' => 'service', 'limit_kind' => self::NONE, 'families' => ['cloud', 'addon'],
            'sources' => ['proxmox' => null, 'cdn' => null],
            'interval_minutes' => null, 'drives_guard' => false, 'status' => self::GAP,
            'reason' => 'UsageWatch only measures "traffic" for web/managed families; cloud services (VPS/VDS) and the CDN add-on never have their monthly transfer read or capped anywhere.',
        ],
        'snapshots' => [
            'entitlement' => ['snapshots'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::NONE, 'families' => ['cloud'],
            'sources' => ['proxmox' => null],
            'interval_minutes' => null, 'drives_guard' => false, 'status' => self::GAP,
            'reason' => 'ServiceFeatures shows "snapshots" as a display limit only; ServiceService\'s snapshot action never calls the generic $limit() count check that database.create/ftp.create/cron.create/subdomain.add use, so a VPS can take unlimited snapshots.',
        ],
        // TASK-0023 placement-capacity (decision 7): kept for the web family only — a web plan that sells it runs only on ISPConfig
        'php_workers_dedicated' => [
            'entitlement' => ['php_workers_dedicated'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::HARD, 'families' => ['web'],
            'sources' => ['ispconfig' => 'IspConfigWebProvider gives every site its own FPM pool (pm=ondemand, pm_max_children = php_workers) and drift-checks it; PlacementRules keeps a plan that sells it on ISPConfig at the placement, the staff pin, the cart, the scheduler and a plan change', 'aapanel' => null],
            'interval_minutes' => null, 'drives_guard' => false, 'status' => self::ENFORCED_ONLY,
            'reason' => 'Not kept for the managed family: eshop/shop-peak runs on aaPanel (one pool per PHP version for the whole node) and is not moved to ISPConfig, where its WAF rate limit and the client-wide site count would break. Listed by onhost:doctor (capacity) and onhost:capacity:basis until the owner decides.',
        ],
        'spam_filter' => [
            'entitlement' => ['spam_filter'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::NONE, 'families' => ['mail'],
            'sources' => ['ispconfig' => null],
            'interval_minutes' => null, 'drives_guard' => false, 'status' => self::GAP,
            'reason' => 'Only CatalogPresentation names the antispam tier ("rspamd" vs "rspamd + policies"); no mail workflow writes an rspamd policy set from it.',
        ],
        // ---- CDN add-on (no executor at all) ---------------------------------------------------------------
        'bot_management' => [
            'entitlement' => ['bot_management'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::NONE, 'families' => ['addon'],
            'sources' => ['cdn' => null],
            'interval_minutes' => null, 'drives_guard' => false, 'status' => self::GAP,
            'reason' => 'The cdn product is seeded with executor=null (CatalogSeeder::addonProducts) — there is no adapter for it at all, so nothing configures bot management.',
        ],
        // ---- game (Pterodactyl) -------------------------------------------------------------------------------
        'cpu_pct' => [
            'entitlement' => ['cpu_pct'], 'unit' => 'pct', 'scope' => 'service', 'limit_kind' => self::HARD, 'families' => ['game', 'cloud'],
            'sources' => ['pterodactyl' => 'PterodactylGameProvider sets the container\'s cpu limit from limits.cpu_pct at server create/resize', 'proxmox' => 'ServiceService::activate() derives the VM\'s cpu_pct cap from vcpu * 100'],
            'interval_minutes' => null, 'drives_guard' => false, 'status' => self::ENFORCED_ONLY, 'reason' => null,
        ],
        'pids' => [
            'entitlement' => ['pids'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::NONE, 'families' => ['game'],
            'sources' => ['pterodactyl' => null],
            'interval_minutes' => null, 'drives_guard' => false, 'status' => self::GAP,
            'reason' => 'Sold in every game plan\'s limits bag; PterodactylGameProvider\'s resource limits (memory/swap/disk/io/cpu/threads/oom_disabled) have no PID-count field, so it is never sent to the panel.',
        ],
        'backups' => [
            'entitlement' => ['backups'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::HARD, 'families' => ['game'],
            'sources' => ['pterodactyl' => 'PterodactylGameProvider sets feature_limits.backups at server create/update — enforced by the panel itself'],
            'interval_minutes' => null, 'drives_guard' => false, 'status' => self::ENFORCED_ONLY, 'reason' => null,
        ],
        'allocations' => [
            'entitlement' => ['allocations'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::HARD, 'families' => ['game'],
            'sources' => ['pterodactyl' => 'PterodactylGameProvider sets feature_limits.allocations at server create/update — enforced by the panel itself'],
            'interval_minutes' => null, 'drives_guard' => false, 'status' => self::ENFORCED_ONLY, 'reason' => null,
        ],
        // ---- managed database (Proxmox, single-tenant KVM) -----------------------------------------------------
        'connections' => [
            'entitlement' => ['connections'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::HARD, 'families' => ['data'],
            'sources' => ['proxmox' => null],
            'interval_minutes' => null, 'drives_guard' => false, 'status' => self::GAP,
            'reason' => 'Only CatalogPresentation names it; no engine config (PostgreSQL max_connections / MariaDB max_connections) is written from it, and nothing measures the live connection count. No longer sold from catalogue revision 2026-09-honest-promises (owner decision 4, CatalogRevisions / onhost:catalog:revise); still carried by the versions customers hold — see PlanPromises::grandfatheredGaps().',
        ],
        'pitr_days' => [
            'entitlement' => ['pitr_days'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::SOFT, 'families' => ['data'],
            'sources' => ['service' => null],
            'interval_minutes' => null, 'drives_guard' => false, 'status' => self::GAP,
            'reason' => 'Point-in-time recovery is not provided: nothing archives WAL, and since TASK-0022 a new DatabaseInstance never claims pitr (ServiceService::activate no longer reads this key). No longer sold from catalogue revision 2026-09-honest-promises (owner decision 2, CatalogRevisions / onhost:catalog:revise); still carried by the versions customers hold — see PlanPromises::grandfatheredGaps().',
        ],
        // ---- backup add-ons (PBS) -----------------------------------------------------------------------------
        'daily' => [
            'entitlement' => ['daily'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::SOFT, 'families' => ['addon'],
            'sources' => ['addons' => 'Addons::backupPolicy() turns daily/weekly/monthly into a BackupPolicy generation count the scheduler prunes to'],
            'interval_minutes' => 1440, 'drives_guard' => false, 'status' => self::ENFORCED_ONLY, 'reason' => null,
        ],
        'weekly' => [
            'entitlement' => ['weekly'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::SOFT, 'families' => ['addon'],
            'sources' => ['addons' => 'Addons::backupPolicy() — see daily'],
            'interval_minutes' => 1440, 'drives_guard' => false, 'status' => self::ENFORCED_ONLY, 'reason' => null,
        ],
        'monthly' => [
            'entitlement' => ['monthly'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::SOFT, 'families' => ['addon'],
            'sources' => ['addons' => 'Addons::backupPolicy() — see daily'],
            'interval_minutes' => 1440, 'drives_guard' => false, 'status' => self::ENFORCED_ONLY, 'reason' => null,
        ],
        'interval_hours' => [
            'entitlement' => ['interval_hours'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::SOFT, 'families' => ['addon'],
            'sources' => ['addons' => 'Addons::backupPolicy() turns interval_hours into the scheduler\'s frequency (hourly/6h/daily)'],
            'interval_minutes' => 60, 'drives_guard' => false, 'status' => self::ENFORCED_ONLY, 'reason' => null,
        ],
        'retention_days' => [
            'entitlement' => ['retention_days'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::SOFT, 'families' => ['addon'],
            'sources' => ['addons' => 'Addons::backupPolicy() feeds retention_days into the BackupPolicy the scheduler prunes to'],
            'interval_minutes' => 1440, 'drives_guard' => false, 'status' => self::ENFORCED_ONLY, 'reason' => null,
        ],
        // ---- mail-hosting add-on and IPv4 add-on -----------------------------------------------------------
        'quota_mb' => [
            'entitlement' => ['quota_mb'], 'unit' => 'bytes', 'scope' => 'service', 'limit_kind' => self::HARD, 'families' => ['web', 'addon'],
            'sources' => ['mail' => 'UsageWatch mail metric falls back to quota_mb when mail_quota_gb is not sold'],
            'interval_minutes' => 60, 'drives_guard' => true, 'status' => self::MEASURED, 'reason' => null,
        ],
        'addresses' => [
            'entitlement' => ['addresses'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::HARD, 'families' => ['addon'],
            'sources' => ['addons' => 'Addons::patch() turns the ipv4 add-on\'s "addresses" into the parent\'s ipv4 entitlement, which IpamService allocates against at resize'],
            'interval_minutes' => null, 'drives_guard' => false, 'status' => self::ENFORCED_ONLY, 'reason' => null,
        ],
        'ipv4' => [
            'entitlement' => ['ipv4'], 'unit' => 'count', 'scope' => 'service', 'limit_kind' => self::HARD, 'families' => ['cloud'],
            'sources' => ['proxmox' => 'ProvisionVpsWorkflow requests one address from IpamService per unit of ipv4 at provision/resize (VDS sells ipv4 => 1)'],
            'interval_minutes' => null, 'drives_guard' => false, 'status' => self::ENFORCED_ONLY, 'reason' => null,
        ],
    ];

    /** @return array{entitlement:list<string>, unit:string, scope:string, limit_kind:string, families:list<string>, sources:array<string,string|null>, interval_minutes:int|null, drives_guard:bool, status:string, reason:string|null}|null */
    public static function get(string $key): ?array
    {
        return self::REGISTRY[$key] ?? null;
    }

    /**
     * Whether the platform actually keeps this promise today (measured or enforced), as opposed to merely
     * registered. `$family` scopes the check to the product family the key is being sold under (audit §5ad,
     * MEDIUM finding): a row is verified for the families it lists, never for every family that happens to sell the
     * same key name. A key sold on a family absent from its own `families` list is not kept, whatever its `status`
     * says about the families it does list — `PlanPromises` passes the plan's product family for exactly this
     * reason. `$family === null` skips the family check (used only where no plan context exists).
     */
    public static function isKept(string $key, ?string $family = null): bool
    {
        $entry = self::REGISTRY[$key] ?? null;
        if ($entry === null) {
            return false;
        }
        if ($family !== null && ! in_array($family, $entry['families'], true)) {
            return false;
        }

        if (in_array($entry['status'], [self::MEASURED, self::ENFORCED_ONLY], true)) {
            return true;
        }
        // a promise delivered only while the owner's default-off rule for it is on (TASK-0024): kept for that family then, never before
        $rule = $family === null ? null : ($entry['kept_under'][$family] ?? null);

        return $rule !== null && self::ruleOn($rule);
    }

    /** Whether an automation rule is on; a settings store that cannot be read counts as off — the promise is then not kept. */
    private static function ruleOn(string $rule): bool
    {
        try {
            return app(AutomationLedger::class)->enabled($rule);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }
}
