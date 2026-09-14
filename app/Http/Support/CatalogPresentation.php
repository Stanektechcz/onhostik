<?php

declare(strict_types=1);

namespace App\Http\Support;

/**
 * Turns catalogue entitlements into the customer-facing bullets and comparison rows the public
 * surfaces render (product pages, the web hosting landing, the pricing page). One vocabulary for
 * every family, Czech and English, so a plan never needs hand-written marketing copy to appear.
 */
final class CatalogPresentation
{
    /** Preferred bullet order per family (entitlement keys). */
    private const ORDER = [
        'web' => ['sites', 'nvme_gb', 'php_workers', 'php_memory_mb', 'mailboxes', 'databases', 'backup_days', 'staging', 'ssh', 'waf', 'traffic', 'object_cache', 'products', 'monitoring', 'updates', 'support'],
        'managed' => ['sites', 'products', 'nvme_gb', 'php_workers', 'object_cache', 'backup_frequency', 'backup_days', 'staging', 'monitoring', 'updates', 'waf', 'support', 'dedicated_db'],
        'cloud' => ['vcpu', 'ram_mb', 'nvme_gb', 'traffic_tb', 'ipv4', 'ipv6', 'snapshots', 'backup', 'io_class', 'console'],
        'game' => ['ram_mb', 'vcpu', 'nvme_gb', 'slots', 'backups', 'allocations', 'databases', 'ddos', 'console', 'sftp'],
        'mail' => ['mailboxes', 'quota_gb_per_mailbox', 'aliases', 'domains', 'relay_per_hour', 'spam_filter', 'imap', 'backup_days', 'dedicated_outbound_ip'],
        'data' => ['vcpu', 'ram_mb', 'nvme_gb', 'connections', 'pitr_days', 'backup_days', 'ha', 'external_access'],
        'apps' => ['cpu_limit', 'ram_mb', 'replicas', 'builds_per_day', 'custom_domains', 'workers', 'cron', 'logs_retention_days', 'zero_downtime', 'tls'],
        'addon' => ['daily', 'weekly', 'monthly', 'interval_hours', 'retention_days', 'offsite', 'restore_test', 'l7', 'game_profiles', 'capacity_gbps', 'traffic_tb', 'pops', 'waf', 'validation', 'warranty', 'issuance', 'wildcard', 'bot_management', 'anycast'],
    ];

    /** @return list<string> */
    public static function bullets(array $entitlements, string $family, string $locale, int $max = 6): array
    {
        $out = [];
        foreach (self::keys($entitlements, $family) as $key) {
            $text = self::format($key, $entitlements[$key], $locale, $entitlements);
            if ($text !== null) {
                $out[] = $text;
            }
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    /** Comparison table: `[label, value per plan …]` rows for the plans of one product. @param list<array{name:string, entitlements:array}> $plans @return list<list<string>> */
    public static function compareRows(array $plans, string $family, string $locale, int $max = 12): array
    {
        $union = [];
        foreach ($plans as $plan) {
            $union += (array) $plan['entitlements'];
        }
        $rows = [];
        foreach (self::keys($union, $family) as $key) {
            $label = self::label($key, $locale);
            if ($label === null) {
                continue;
            }
            $row = [$label];
            foreach ($plans as $plan) {
                $row[] = self::value($key, ((array) $plan['entitlements'])[$key] ?? null, $locale);
            }
            $rows[] = $row;
            if (count($rows) >= $max) {
                break;
            }
        }

        return $rows;
    }

    /** @return list<string> entitlement keys in presentation order */
    /** Every parameter of every plan (the "detailed configuration" listing): `[label, value per plan …]`. @param list<array{name:string, entitlements:array}> $plans @return list<list<string>> */
    public static function detailRows(array $plans, string $family, string $locale): array
    {
        $union = [];
        foreach ($plans as $plan) {
            $union += (array) $plan['entitlements'];
        }
        $keys = self::keys($union, $family);
        foreach (array_keys($union) as $k) {
            if (! in_array((string) $k, $keys, true) && $k !== 'options') {
                $keys[] = (string) $k;
            }
        }
        $rows = [];
        foreach ($keys as $key) {
            $row = [self::label($key, $locale) ?? ucfirst(str_replace('_', ' ', $key))];
            foreach ($plans as $plan) {
                $row[] = self::value($key, ((array) $plan['entitlements'])[$key] ?? null, $locale);
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private static function keys(array $entitlements, string $family): array
    {
        $preferred = self::ORDER[$family] ?? [];
        $keys = array_values(array_filter($preferred, fn ($k) => array_key_exists($k, $entitlements)));
        foreach (array_keys($entitlements) as $k) {
            if (! in_array($k, $keys, true) && self::label((string) $k, 'cs') !== null) {
                $keys[] = (string) $k;
            }
        }

        return $keys;
    }

    private static function label(string $key, string $locale): ?string
    {
        $cs = $locale !== 'en';
        $labels = [
            'sites' => ['Weby', 'Sites'], 'products' => ['Produkty e-shopu', 'Shop products'], 'nvme_gb' => ['NVMe prostor', 'NVMe storage'], 'php_workers' => ['PHP workery', 'PHP workers'],
            'php_memory_mb' => ['Paměť PHP', 'PHP memory'], 'mailboxes' => ['E-mailové schránky', 'Mailboxes'], 'databases' => ['Databáze', 'Databases'], 'backup_days' => ['Zálohy', 'Backups'],
            'backup_frequency' => ['Interval záloh', 'Backup interval'], 'staging' => ['Staging prostředí', 'Staging environment'], 'ssh' => ['SSH / WP-CLI', 'SSH / WP-CLI'], 'waf' => ['WAF', 'WAF'],
            'traffic' => ['Přenos dat', 'Traffic'], 'object_cache' => ['Objektová cache', 'Object cache'], 'monitoring' => ['Monitoring', 'Monitoring'], 'updates' => ['Aktualizace', 'Updates'], 'support' => ['Podpora', 'Support'],
            'dedicated_db' => ['Dedikovaná databáze', 'Dedicated database'], 'vcpu' => ['vCPU', 'vCPU'], 'ram_mb' => ['RAM', 'RAM'], 'traffic_tb' => ['Přenos dat', 'Traffic'], 'ipv4' => ['IPv4', 'IPv4'], 'ipv6' => ['IPv6', 'IPv6'],
            'snapshots' => ['Snapshoty', 'Snapshots'], 'backup' => ['Zálohy', 'Backups'], 'io_class' => ['Třída IO', 'IO class'], 'console' => ['Konzole', 'Console'], 'slots' => ['Sloty', 'Slots'], 'backups' => ['Zálohy', 'Backups'],
            'allocations' => ['Porty / alokace', 'Ports / allocations'], 'ddos' => ['Anti-DDoS', 'Anti-DDoS'], 'sftp' => ['SFTP', 'SFTP'], 'quota_gb_per_mailbox' => ['Prostor na schránku', 'Space per mailbox'], 'aliases' => ['Aliasy', 'Aliases'],
            'domains' => ['Domény', 'Domains'], 'relay_per_hour' => ['Odesílání za hodinu', 'Sending per hour'], 'spam_filter' => ['Antispam', 'Antispam'], 'imap' => ['IMAP', 'IMAP'], 'dedicated_outbound_ip' => ['Dedikovaná odchozí IP', 'Dedicated outbound IP'],
            'connections' => ['Spojení', 'Connections'], 'pitr_days' => ['Obnova k okamžiku (PITR)', 'Point-in-time recovery'], 'ha' => ['Vysoká dostupnost', 'High availability'], 'external_access' => ['Přístup zvenku', 'External access'],
            'cpu_limit' => ['CPU limit', 'CPU limit'], 'replicas' => ['Repliky', 'Replicas'], 'builds_per_day' => ['Buildů denně', 'Builds per day'], 'custom_domains' => ['Vlastní domény', 'Custom domains'], 'workers' => ['Workery', 'Workers'],
            'cron' => ['Cron úlohy', 'Cron jobs'], 'logs_retention_days' => ['Logy', 'Logs'], 'zero_downtime' => ['Nasazení bez výpadku', 'Zero-downtime deploys'], 'tls' => ['TLS', 'TLS'],
            'daily' => ['Denní zálohy', 'Daily backups'], 'weekly' => ['Týdenní zálohy', 'Weekly backups'], 'monthly' => ['Měsíční zálohy', 'Monthly backups'], 'interval_hours' => ['Interval', 'Interval'], 'retention_days' => ['Historie', 'Retention'],
            'offsite' => ['Kopie mimo lokalitu', 'Off-site copy'], 'restore_test' => ['Test obnovy', 'Restore test'], 'l7' => ['L7 filtr', 'L7 filtering'], 'game_profiles' => ['Herní profily', 'Game profiles'], 'capacity_gbps' => ['Kapacita', 'Capacity'],
            'pops' => ['PoP lokality', 'PoP locations'], 'validation' => ['Ověření', 'Validation'], 'warranty' => ['Pojištění', 'Warranty'], 'issuance' => ['Vystavení', 'Issuance'], 'wildcard' => ['Wildcard', 'Wildcard'], 'bot_management' => ['Bot management', 'Bot management'], 'anycast' => ['Anycast', 'Anycast'],
        ];
        $pair = $labels[$key] ?? null;

        return $pair === null ? null : ($cs ? $pair[0] : $pair[1]);
    }

    /** Value cell of the comparison table. */
    private static function value(string $key, mixed $value, string $locale): string
    {
        $cs = $locale !== 'en';
        if ($value === null || $value === '' || $value === false) {
            return '—';
        }
        if ($value === true) {
            return '✓';
        }
        if (is_array($value)) {
            return implode(', ', array_map('strval', $value));
        }
        $v = (string) $value;

        return match ($key) {
            'nvme_gb', 'quota_gb_per_mailbox' => $v.' GB', 'php_memory_mb' => $v.' MB', 'ram_mb' => (is_numeric($v) ? (int) $v >= 1024 ? rtrim(rtrim(number_format((int) $v / 1024, 1, '.', ''), '0'), '.').' GB' : $v.' MB' : $v),
            'backup_days', 'pitr_days', 'retention_days', 'logs_retention_days' => $v.' '.self::days((int) $v, $cs), 'traffic_tb' => $v.' TB', 'capacity_gbps' => $v.' Gbps', 'interval_hours' => $v.' h',
            'sites', 'products', 'mailboxes', 'databases', 'aliases', 'domains', 'connections', 'snapshots', 'backups', 'allocations', 'replicas', 'builds_per_day', 'custom_domains', 'workers', 'cron', 'daily', 'weekly', 'monthly' => is_numeric($v) && (int) $v >= 999999 ? ($cs ? 'neomezeně' : 'unlimited') : $v,
            'php_workers' => $v, default => $v,
        };
    }

    /** Bullet text for one entitlement, or null when it says nothing worth a bullet. */
    private static function format(string $key, mixed $value, string $locale, array $all): ?string
    {
        $cs = $locale !== 'en';
        if ($value === null || $value === '' || $value === false || $value === 0 || $value === '0') {
            return null;
        }
        $n = is_numeric($value) ? (int) $value : null;
        $unlimited = $n !== null && $n >= 999999;
        $plural = fn (int $x, string $one, string $few, string $many) => $x === 1 ? $one : ($x < 5 ? $few : $many);

        return match ($key) {
            'sites' => $unlimited ? ($cs ? 'Neomezeně webů' : 'Unlimited sites') : ($cs ? $n.' '.$plural($n, 'web', 'weby', 'webů') : $n.' '.($n === 1 ? 'site' : 'sites')),
            'products' => $unlimited ? ($cs ? 'Bez limitu produktů' : 'No product limit') : ($cs ? 'do '.number_format($n, 0, ',', ' ').' produktů' : 'up to '.number_format($n, 0, '.', ',').' products'),
            'nvme_gb' => $n.' GB NVMe', 'php_workers' => ($cs ? $n.' PHP '.$plural($n, 'worker', 'workery', 'workerů') : $n.' PHP '.($n === 1 ? 'worker' : 'workers')).(! empty($all['php_workers_dedicated']) ? ($cs ? ' (dedikované)' : ' (dedicated)') : ''),
            'php_memory_mb' => 'PHP '.$n.' MB', 'mailboxes' => $unlimited ? ($cs ? 'Neomezeně schránek' : 'Unlimited mailboxes') : ($cs ? $n.' '.$plural($n, 'schránka', 'schránky', 'schránek') : $n.' '.($n === 1 ? 'mailbox' : 'mailboxes')),
            'databases' => $unlimited ? ($cs ? 'Neomezeně databází' : 'Unlimited databases') : ($cs ? $n.' '.$plural($n, 'databáze', 'databáze', 'databází') : $n.' '.($n === 1 ? 'database' : 'databases')),
            'backup_days' => $cs ? 'Zálohy '.$n.' '.self::days($n, true) : $n.'-day backups', 'backup_frequency' => $cs ? 'Zálohy každých '.$value : 'Backups every '.$value, 'staging' => $cs ? 'Staging na klik' : 'One-click staging',
            'ssh' => $cs ? 'SSH a WP-CLI' : 'SSH and WP-CLI', 'waf' => is_string($value) ? 'WAF '.$value : 'WAF', 'traffic' => $cs ? 'Přenos '.$value : 'Traffic '.$value, 'object_cache' => $cs ? 'Cache '.$value : ucfirst((string) $value).' cache',
            'monitoring' => $cs ? 'Monitoring v ceně' : 'Monitoring included', 'updates' => $cs ? 'Aktualizace: '.$value : 'Updates: '.$value, 'support' => $cs ? 'Podpora '.$value : 'Support '.$value, 'dedicated_db' => $cs ? 'Dedikovaná databáze' : 'Dedicated database',
            'vcpu' => $n.' vCPU'.(($all['cpu_class'] ?? null) === 'dedicated' ? ($cs ? ' dedikovaných' : ' dedicated') : ''), 'ram_mb' => ($n >= 1024 ? rtrim(rtrim(number_format($n / 1024, 1, '.', ''), '0'), '.').' GB' : $n.' MB').' RAM',
            'traffic_tb' => $cs ? $n.' TB přenosu' : $n.' TB traffic', 'ipv4' => $value === 'addon' ? null : ($cs ? 'IPv4 v ceně' : 'IPv4 included'), 'ipv6' => 'IPv6 '.$value, 'snapshots' => $cs ? $n.' '.$plural($n, 'snapshot', 'snapshoty', 'snapshotů') : $n.' snapshots',
            'backup' => $value === 'addon' ? null : ($cs ? 'Zálohy '.$value : 'Backups '.$value), 'io_class' => $cs ? 'IO třída '.$value : 'IO class '.$value, 'console' => $cs ? 'Konzole '.$value : 'Console '.$value,
            'slots' => $cs ? 'Sloty: '.$value : 'Slots: '.$value, 'backups' => $cs ? $n.' záloh' : $n.' backups', 'allocations' => $cs ? $n.' portů' : $n.' ports', 'ddos' => 'Anti-DDoS '.$value, 'sftp' => 'SFTP',
            'quota_gb_per_mailbox' => $cs ? $n.' GB na schránku' : $n.' GB per mailbox', 'aliases' => $cs ? $n.' aliasů' : $n.' aliases', 'domains' => $cs ? $n.' '.$plural($n, 'doména', 'domény', 'domén') : $n.' domains',
            'relay_per_hour' => $cs ? $n.' e-mailů za hodinu' : $n.' e-mails per hour', 'spam_filter' => $cs ? 'Antispam '.$value : 'Antispam '.$value, 'imap' => 'IMAP, SMTP, webmail', 'dedicated_outbound_ip' => $cs ? 'Dedikovaná odchozí IP' : 'Dedicated outbound IP',
            'connections' => $cs ? $n.' spojení' : $n.' connections', 'pitr_days' => 'PITR '.$n.' '.self::days($n, $cs), 'ha' => $cs ? 'Primár + replika' : 'Primary + replica', 'external_access' => null,
            'cpu_limit' => $value.' CPU', 'replicas' => $cs ? $n.' '.$plural($n, 'replika', 'repliky', 'replik') : $n.' replicas', 'builds_per_day' => $cs ? $n.' buildů denně' : $n.' builds a day', 'custom_domains' => $cs ? $n.' vlastních domén' : $n.' custom domains',
            'workers' => $cs ? $n.' workerů' : $n.' workers', 'cron' => $cs ? $n.' cron úloh' : $n.' cron jobs', 'logs_retention_days' => $cs ? 'Logy '.$n.' dní' : $n.'-day logs', 'zero_downtime' => $cs ? 'Nasazení bez výpadku' : 'Zero-downtime deploys', 'tls' => 'TLS '.$value,
            'daily' => $cs ? $n.' denních záloh' : $n.' daily backups', 'weekly' => $cs ? $n.' týdenních' : $n.' weekly', 'monthly' => $cs ? $n.' měsíčních' : $n.' monthly', 'interval_hours' => $cs ? 'Snapshot každou '.($n === 1 ? 'hodinu' : $n.' hodiny') : ($n === 1 ? 'Hourly snapshots' : 'Every '.$n.' hours'),
            'retention_days' => $cs ? 'Historie '.$n.' dní' : $n.'-day retention', 'offsite' => $cs ? 'Kopie mimo lokalitu' : 'Off-site copy', 'restore_test' => $cs ? 'Test obnovy '.$value : 'Restore test '.$value, 'l7' => $cs ? 'L7 filtr' : 'L7 filtering',
            'game_profiles' => $cs ? 'Herní profily' : 'Game profiles', 'capacity_gbps' => number_format($n / 1000, 1, ',', ' ').' Tbps', 'pops' => $cs ? $n.' PoP' : $n.' PoPs', 'validation' => $cs ? 'Ověření '.$value : 'Validation '.$value, 'warranty' => $cs ? 'Pojištění '.$value : 'Warranty '.$value,
            'issuance' => $cs ? 'Vystavení '.$value : 'Issuance '.$value, 'wildcard' => $cs ? 'Neomezeně subdomén' : 'Unlimited subdomains', 'bot_management' => 'Bot management', 'anycast' => 'Anycast '.$value,
            default => null,
        };
    }

    private static function days(int $n, bool $cs): string
    {
        return $cs ? ($n === 1 ? 'den' : ($n < 5 ? 'dny' : 'dní')) : ($n === 1 ? 'day' : 'days');
    }
}
