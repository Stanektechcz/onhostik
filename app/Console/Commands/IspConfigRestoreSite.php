<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Secrets\SecretStore;
use Onhost\Providers\IspConfig\IspConfigConnector;
use Throwable;

/**
 * Bring back a web site that was deleted on an ISPConfig node — through the API, never by hand in the panel.
 *
 * The site is recreated from the record the panel still has in its data log (`--record` with the JSON of the old
 * `web_domain` row, as `sys_datalog.data` keeps it), so domain, client, PHP, quota and the document root come back the
 * way they were. Afterwards the node's own backups of that site can be listed and restored (`--restore-backup`),
 * which is what puts the files and databases back.
 *
 *   php artisan onhost:ispconfig:restore-site s4s.electree.cz --instance=ispconfig-s2 --record=old-web27.json
 *   php artisan onhost:ispconfig:restore-site s4s.electree.cz --instance=ispconfig-s2 --list-backups
 */
final class IspConfigRestoreSite extends Command
{
    /** Fields of the old record that are carried over; everything else the panel fills in itself. */
    private const CARRY = [
        'server_id', 'ip_address', 'ipv6_address', 'domain', 'type', 'parent_domain_id', 'vhost_type', 'document_root', 'system_user', 'system_group',
        'hd_quota', 'traffic_quota', 'cgi', 'ssi', 'suexec', 'errordocs', 'is_subdomainwww', 'subdomain', 'php', 'ruby', 'python', 'perl', 'redirect_type',
        'redirect_path', 'seo_redirect', 'ssl', 'ssl_letsencrypt', 'rewrite_to_https', 'php_fpm_use_socket', 'pm', 'pm_max_children', 'pm_start_servers',
        'pm_min_spare_servers', 'pm_max_spare_servers', 'pm_process_idle_timeout', 'pm_max_requests', 'php_open_basedir', 'custom_php_ini', 'backup_interval',
        'backup_copies', 'active', 'traffic_quota_lock', 'fastcgi_php_version', 'proxy_directives', 'enable_spdy', 'http_port', 'https_port', 'directive_snippets_id',
    ];

    protected $signature = 'onhost:ispconfig:restore-site
        {domain : the domain of the deleted site}
        {--instance= : provider instance key (ispconfig-…)}
        {--record= : path to a JSON file with the old sites_web_domain record}
        {--client= : client_id to own the site (default: the one in the record)}
        {--list-backups : only list the backups the node still has for this site}
        {--restore-backup= : backup_id to restore into the site once it exists}
        {--dry-run : show what would be sent, send nothing}';

    protected $description = 'Recreate a deleted ISPConfig web site from its old record and restore its backup (API only)';

    public function handle(SecretStore $secrets): int
    {
        $domain = strtolower(trim((string) $this->argument('domain'), " .\t"));
        $instance = ProviderInstance::query()->where('provider', 'ispconfig')
            ->when($this->option('instance'), fn ($q) => $q->where('key', (string) $this->option('instance')))->first();
        if ($instance === null) {
            $this->error('no ISPConfig instance found'.($this->option('instance') ? ': '.$this->option('instance') : '; pass --instance'));

            return 1;
        }
        $api = app(IspConfigConnector::class, ['instance' => $instance, 'credentials' => $secrets->read($instance->secretRef())]);

        $existing = self::row($api->call('sites_web_domain_get', ['primary_id' => ['domain' => $domain]]));
        if ($this->option('list-backups') || $this->option('restore-backup')) {
            if (! is_array($existing)) {
                $this->error("the site {$domain} does not exist on {$instance->key}; recreate it first");

                return 1;
            }

            return $this->backups($api, (int) $existing['domain_id'], $domain);
        }
        if (is_array($existing)) {
            $this->info("the site {$domain} already exists (domain_id {$existing['domain_id']}, user ".(string) ($existing['system_user'] ?? '?').'); nothing to recreate');
            $this->line('next: php artisan onhost:ispconfig:restore-site '.$domain.' --instance='.$instance->key.' --list-backups');

            return 0;
        }

        $record = $this->record();
        if ($record === null) {
            return 1;
        }
        $params = array_intersect_key($record, array_flip(self::CARRY));
        $params['domain'] = $domain;
        $params['active'] = 'y';
        $clientId = (int) ($this->option('client') ?: ($record['client_id'] ?? 0));
        if ($clientId <= 0) {
            $clientId = $this->clientOfGroup($api, (int) ($record['sys_groupid'] ?? 0));
        }
        if ($clientId <= 0) {
            $this->error('the record carries no client; pass --client=<client_id>');

            return 1;
        }
        $this->table(['field', 'value'], array_map(fn ($k, $v) => [$k, is_scalar($v) ? mb_substr((string) $v, 0, 70) : gettype($v)], array_keys($params), array_values($params)));
        $this->line("client_id: <info>{$clientId}</info> · instance: <info>{$instance->key}</info>");
        if ((bool) $this->option('dry-run')) {
            $this->warn('dry run: nothing was sent');

            return 0;
        }

        try {
            $domainId = (int) $api->call('sites_web_domain_add', ['client_id' => $clientId, 'params' => $params, 'readonly' => false], true);
        } catch (Throwable $e) {
            $this->error('sites_web_domain_add failed: '.$e->getMessage());

            return 1;
        }
        $this->info("site recreated: domain_id {$domainId}");
        $this->waitForQueue($api, (int) ($params['server_id'] ?? 1));
        $site = self::row($api->call('sites_web_domain_get', ['primary_id' => $domainId]));
        $this->line('system_user: <info>'.(string) ($site['system_user'] ?? '?').'</info> · document_root: <info>'.(string) ($site['document_root'] ?? '?').'</info>');
        $this->line('next: php artisan onhost:ispconfig:restore-site '.$domain.' --instance='.$instance->key.' --list-backups');

        return 0;
    }

    /**
     * ISPConfig answers a lookup either with the record itself or with a list of records; both shapes come back here
     * as the record (or null when nothing matched).
     *
     * @return array<string,mixed>|null
     */
    private static function row(mixed $response): ?array
    {
        if (! is_array($response) || $response === []) {
            return null;
        }
        if (array_keys($response) !== range(0, count($response) - 1)) {
            return $response; // a single associative record
        }
        $first = $response[0] ?? null;

        return is_array($first) ? $first : null;
    }

    /** @return array<string,mixed>|null */
    private function record(): ?array
    {
        $path = (string) ($this->option('record') ?? '');
        if ($path === '' || ! is_file($path)) {
            $this->error('pass --record=<file.json> with the old sites_web_domain record (from the node: SELECT data FROM sys_datalog WHERE dbtable=\'web_domain\' AND dbidx=\'domain_id:<id>\' ORDER BY tstamp DESC LIMIT 1)');

            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            $this->error('the record file is not valid JSON');

            return null;
        }
        // sys_datalog stores {"old":{…},"new":{…}}; the delete entry keeps the site in "old"
        $record = is_array($data['old'] ?? null) && $data['old'] !== [] ? $data['old'] : (is_array($data['new'] ?? null) && $data['new'] !== [] ? $data['new'] : $data);

        return $record;
    }

    private function clientOfGroup(IspConfigConnector $api, int $groupId): int
    {
        if ($groupId <= 0) {
            return 0;
        }
        try {
            $client = self::row($api->call('client_get', ['client_id' => ['groupid' => $groupId]]));

            return $client === null ? 0 : (int) ($client['client_id'] ?? 0);
        } catch (Throwable) {
            return 0;
        }
    }

    private function backups(IspConfigConnector $api, int $domainId, string $domain): int
    {
        $backups = (array) $api->call('sites_web_domain_backup_list', ['site_id' => $domainId]);
        if ($backups === []) {
            $this->warn("the node reports no backups for {$domain} (domain_id {$domainId}); the archives on the node are under /var/backup/web{$domainId}");

            return 0;
        }
        $rows = array_map(fn (array $b) => [
            $b['backup_id'] ?? '?', $b['backup_type'] ?? '?', isset($b['tstamp']) ? date('j. n. Y H:i', (int) $b['tstamp']) : '?',
            isset($b['filesize']) ? number_format((int) $b['filesize'] / 1048576, 1).' MB' : '?', $b['filename'] ?? '',
        ], $backups);
        $this->table(['backup_id', 'druh', 'kdy', 'velikost', 'soubor'], $rows);
        $restore = (string) ($this->option('restore-backup') ?? '');
        if ($restore === '') {
            $this->line('restore with: --restore-backup=<backup_id>');

            return 0;
        }
        if ((bool) $this->option('dry-run')) {
            $this->warn('dry run: the restore was not requested');

            return 0;
        }
        $api->call('sites_web_domain_backup', ['primary_id' => $domainId, 'action_type' => 'restore', 'backup_id' => (int) $restore], true);
        $this->info("restore of backup {$restore} requested; the node applies it through its job queue");

        return 0;
    }

    private function waitForQueue(IspConfigConnector $api, int $serverId): void
    {
        $deadline = time() + 180;
        while (time() < $deadline) {
            $open = (int) $api->call('monitor_jobqueue_count', ['server_id' => $serverId]);
            if ($open === 0) {
                return;
            }
            $this->line("waiting for the job queue: {$open} open");
            sleep(5);
        }
        $this->warn('the job queue is still busy; the site appears once the server catches up');
    }
}
