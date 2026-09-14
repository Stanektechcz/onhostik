<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Incidents\OrganizationStatusService;

/**
 * Prints the edge configuration for the customers' own status hosts (audit §5l-3) with the portal host and the upstream
 * filled in: Caddy (on-demand TLS with the platform's "ask") or nginx (default server + certbot). `--write` stores it
 * next to the templates so the deploy can ship it.
 */
final class EdgeConfig extends Command
{
    protected $signature = 'onhost:edge:config {--format=caddy : caddy|nginx} {--upstream=127.0.0.1:8000 : the portal upstream (host:port)} {--write : Write infra/edge/<format>.generated} {--out= : Write the rendered configuration to this path (the Ansible role uses it)}';

    protected $description = 'Edge configuration for the customers\' status hosts (on-demand TLS asks the platform first)';

    public function handle(): int
    {
        $format = strtolower((string) $this->option('format'));
        $templates = ['caddy' => 'Caddyfile.status-hosts', 'nginx' => 'nginx-status-hosts.conf'];
        if (! isset($templates[$format])) {
            $this->error('format is caddy or nginx');

            return self::FAILURE;
        }
        $path = base_path('infra/edge/'.$templates[$format]);
        if (! is_file($path)) {
            $this->error("Template missing: {$path}");

            return self::FAILURE;
        }
        $host = OrganizationStatusService::customCname();
        $fill = fn (string $template) => str_replace(['{{PORTAL_HOST}}', '{{UPSTREAM}}'], [$host, (string) $this->option('upstream')], $template);
        $rendered = $fill((string) file_get_contents($path));
        // Caddy allows one global options block and only in the main Caddyfile, so the `on_demand_tls { ask }` part is a file of its own (merged by the role)
        $global = $format === 'caddy' && is_file($path.'.global') ? $fill((string) file_get_contents($path.'.global')) : null;
        $this->line(($global !== null ? $global."\n" : '').$rendered);
        if (($out = (string) $this->option('out')) !== '') { // §5m-3: the Ansible role renders to a path and ships the file to the edge host
            $dir = dirname($out);
            if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
                $this->error("Cannot create {$dir}");

                return self::FAILURE;
            }
            file_put_contents($out, $rendered);
            $this->info("written {$out}");
            if ($global !== null) {
                file_put_contents($out.'.global', $global);
                $this->info("written {$out}.global");
            }
        }
        if ($this->option('write')) {
            $out = base_path('infra/edge/'.$format.'.generated');
            file_put_contents($out, ($global !== null ? $global."\n" : '').$rendered);
            $this->info("written {$out}");
        }
        $this->info("ask endpoint: https://{$host}/v1/status/host-check?host=<host> (200 = issue, 404 = never)");

        return self::SUCCESS;
    }
}
