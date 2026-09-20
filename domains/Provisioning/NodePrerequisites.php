<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\GameProvider;
use Onhost\Providers\Contracts\GameToolsProvider;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\SelfProbing;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Contracts\WebToolsProvider;
use Throwable;

/**
 * Node prerequisites (audit §5e-8): what each provider instance can actually deliver, recorded on the instance so
 * features are offered only where the node supports them — the panel API reachable and its version, the PHP
 * versions installed, whether the cron API works (broken on some ISPConfig servers), the job-queue depth, and the
 * operator-declared facts the API cannot see (Apache `mod_proxy` for reverse proxies). `siteFeatures()` of the
 * adapters read the record; the staff integrations page shows it and runs the check on demand.
 */
final class NodePrerequisites
{
    public function __construct(private readonly ProviderRegistry $providers, private readonly AuditRecorder $audit, private readonly OutboxPublisher $outbox) {}

    /** @return array{checked:int, ok:int, warnings:int} */
    public function checkAll(): array
    {
        $stats = ['checked' => 0, 'ok' => 0, 'warnings' => 0];
        foreach (ProviderInstance::query()->whereIn('state', ['active', 'draining', 'maintenance'])->get() as $instance) {
            $stats['checked']++;
            $result = $this->check($instance, CommandContext::system('nodes-check'));
            $result['warnings'] === [] ? $stats['ok']++ : $stats['warnings']++;
        }

        return $stats;
    }

    /** @return array{checked_at:string, api:string, version:?string, php_versions:list<string>, cron_api:string, jobqueue:?int, mod_proxy:string, client_api:?string, game:?array<string,mixed>, warnings:list<string>} */
    public function check(ProviderInstance $instance, CommandContext $context): array
    {
        $out = ['checked_at' => now()->toIso8601String(), 'api' => 'down', 'version' => null, 'php_versions' => [], 'cron_api' => 'unknown', 'jobqueue' => null, 'mod_proxy' => (string) $instance->option('mod_proxy', 'unknown'), 'client_api' => null, 'game' => null, 'shell' => null, 'warnings' => []];
        try {
            $adapter = $this->providers->forInstance($instance);
            $health = $adapter->health();
            $out['api'] = $health->healthy ? 'up' : 'down';
            $out['version'] = $health->version;
            $out['jobqueue'] = isset($health->detail['jobqueue']) ? (int) $health->detail['jobqueue'] : null;
            if (! $health->healthy) {
                $out['warnings'][] = 'API nedostupné: '.(string) ($health->error ?? '');
            }
            if (! empty($health->detail['warning'])) {
                $out['warnings'][] = (string) $health->detail['warning'];
            }
            if ($adapter instanceof WebHostingProvider && $health->healthy) {
                try {
                    $out['php_versions'] = array_values(array_map('strval', $adapter->phpVersions()));
                    if ($out['php_versions'] === []) {
                        $out['warnings'][] = 'Uzel nehlásí žádnou verzi PHP.';
                    }
                } catch (Throwable $e) {
                    $out['warnings'][] = 'Verze PHP nelze zjistit: '.mb_substr($e->getMessage(), 0, 160);
                }
                $binding = ProviderBinding::query()->where('provider_instance_id', $instance->id)->whereIn('remote_type', ['web_domain', 'site'])->orderBy('created_at')->first();
                if ($binding !== null) {
                    try {
                        $adapter->listCron($binding->ref());
                        $out['cron_api'] = 'ok';
                    } catch (Throwable $e) {
                        $out['cron_api'] = 'broken';
                        $out['warnings'][] = 'Cron API na uzlu nefunguje ('.mb_substr($e->getMessage(), 0, 120).'); plánované úlohy se v panelu nenabízejí.';
                    }
                }
            }
            if ($adapter instanceof WebToolsProvider && $health->healthy) {
                $out['shell'] = $this->shellProbe($instance, $adapter, $out['warnings']);
                if ($out['mod_proxy'] === 'unknown' && ($out['shell']['mod_proxy'] ?? null) === 'yes') { // the probe saw the module: record what the operator would otherwise declare
                    $instance->forceFill(['options' => array_merge((array) $instance->options, ['mod_proxy' => 'yes'])])->save();
                    $out['mod_proxy'] = 'yes';
                }
            }
            if ($adapter instanceof GameToolsProvider && $adapter instanceof GameProvider && $health->healthy) {
                $out['game'] = $this->gamePanel($instance, $adapter, $out['warnings']);
                $out['client_api'] = $out['game']['client_api'];
            }
            if ($adapter instanceof SelfProbing && $health->healthy) { // which of the calls we rely on does THIS panel really answer
                $any = ProviderBinding::query()->where('provider_instance_id', $instance->id)->whereIn('remote_type', ['web_domain', 'site', 'server', 'qemu', 'lxc'])->orderBy('created_at')->first();
                $out['probes'] = $adapter->probes($any?->ref());
                foreach ($out['probes'] as $probe => $answer) {
                    // `datalog_api` is knowledge for the next change of the adapter, not something operations has to act on tonight
                    if (is_string($answer) && $probe !== 'datalog_api' && ! str_starts_with($answer, 'ok') && ! str_starts_with($answer, 'skipped')) {
                        $out['warnings'][] = "Kontrola „{$probe}“: {$answer}";
                    }
                }
                foreach (['backup_api', 'datalog_api'] as $key) { // read by the adapters like `cron_api`
                    if (isset($out['probes'][$key]) && is_string($out['probes'][$key])) {
                        $out[$key] = str_starts_with($out['probes'][$key], 'ok') ? 'ok' : (str_starts_with($out['probes'][$key], 'skipped') ? 'unknown' : 'broken');
                    }
                }
            }
            if ($instance->provider === 'ispconfig' && $out['mod_proxy'] === 'unknown') {
                $out['warnings'][] = 'mod_proxy na Apache není potvrzený (nastavte volbu instance mod_proxy=yes/no); reverzní proxy se nabízí, dokud ji nevypnete.';
            }
            if ($out['jobqueue'] !== null && $out['jobqueue'] >= 50) {
                $out['warnings'][] = "Fronta úloh panelu má {$out['jobqueue']} položek; změny se projeví se zpožděním.";
            }
        } catch (Throwable $e) {
            $out['warnings'][] = 'Kontrola selhala: '.mb_substr($e->getMessage(), 0, 200);
        }
        $capabilities = (array) ($instance->capabilities ?? []);
        $before = array_values(array_map('strval', (array) data_get($capabilities, 'prereqs.warnings', [])));
        $wasUp = data_get($capabilities, 'prereqs.api') === 'up';
        $instance->forceFill(['capabilities' => array_merge($capabilities, ['prereqs' => $out])])->save();
        if (isset($capabilities['prereqs'])) { // §5p-4: the nightly pass against the real vendors tells operations what changed since yesterday
            $appeared = array_values(array_diff($out['warnings'], $before));
            $cleared = array_values(array_diff($before, $out['warnings']));
            if ($appeared !== [] || ($wasUp && $out['api'] !== 'up')) {
                $this->outbox->publish(GenericEvent::of('integration.prereqs.regressed', 'provider_instance', $instance->id, ['key' => $instance->key, 'provider' => $instance->provider, 'api' => $out['api'], 'appeared' => $appeared, 'warnings' => $out['warnings']]));
            } elseif ($cleared !== [] && $out['warnings'] === []) {
                $this->outbox->publish(GenericEvent::of('integration.prereqs.recovered', 'provider_instance', $instance->id, ['key' => $instance->key, 'provider' => $instance->provider, 'cleared' => $cleared]));
            }
        }
        $this->providers->forget($instance);
        $this->audit->record($context, 'provider.instance.prereqs', $out['api'] === 'up' ? 'succeeded' : 'failed', ['key' => $instance->key, 'warnings' => $out['warnings'], 'php_versions' => $out['php_versions'], 'cron_api' => $out['cron_api'], 'client_api' => $out['client_api'] ?? null], 'provider_instance', $instance->id);

        return $out;
    }

    /**
     * Shell probe (audit §5f-6): what the site agent can run on the node — the CLI tools the toolkit relies on (php,
     * git, rsync, composer, wp) and, where the agent may look, whether the Apache proxy module is enabled. Runs as the
     * first site's agent user with a short timeout; a node without a usable agent simply reports `available: false`.
     *
     * @param  list<string>  $warnings
     * @return array{available:bool, php_cli:?string, tools:array<string,bool>, mod_proxy:?string, error:?string}
     */
    private function shellProbe(ProviderInstance $instance, WebToolsProvider $adapter, array &$warnings): array
    {
        $out = ['available' => false, 'php_cli' => null, 'tools' => [], 'mod_proxy' => null, 'error' => null];
        $binding = ProviderBinding::query()->where('provider_instance_id', $instance->id)->whereIn('remote_type', ['web_domain', 'site'])->orderBy('created_at')->first();
        if ($binding === null) {
            return $out;
        }
        try {
            $ref = $binding->ref();
            if (! $adapter->shellAvailable($ref)) {
                return $out;
            }
            $probe = 'echo PHP=$(php -r "echo PHP_VERSION;" 2>/dev/null); for t in git rsync composer wp unzip tar; do if command -v $t >/dev/null 2>&1; then echo "TOOL:$t=1"; else echo "TOOL:$t=0"; fi; done; if [ -e /etc/apache2/mods-enabled/proxy.load ] || [ -e /etc/httpd/conf.modules.d/00-proxy.conf ]; then echo PROXY=yes; elif [ -d /etc/apache2/mods-enabled ] || [ -d /etc/httpd/conf.modules.d ]; then echo PROXY=no; else echo PROXY=unknown; fi';
            $result = $adapter->shell($ref)->run($probe, ['timeout' => 20]);
            $out['available'] = true;
            foreach (preg_split('/\r?\n/', $result->output()) as $line) {
                if (preg_match('/^PHP=(.*)$/', $line, $m) === 1) {
                    $out['php_cli'] = $m[1] !== '' ? $m[1] : null;
                } elseif (preg_match('/^TOOL:([a-z]+)=([01])$/', $line, $m) === 1) {
                    $out['tools'][$m[1]] = $m[2] === '1';
                } elseif (preg_match('/^PROXY=(yes|no|unknown)$/', $line, $m) === 1) {
                    $out['mod_proxy'] = $m[1];
                }
            }
            if ($out['php_cli'] === null) {
                $warnings[] = 'Agent na uzlu nemá PHP v příkazové řádce; WP-CLI, Composer a importy poběží jen přes panel.';
            }
            $missing = array_keys(array_filter($out['tools'], fn ($ok) => ! $ok));
            if ($missing !== []) {
                $warnings[] = 'Na uzlu chybí nástroje: '.implode(', ', $missing).'.';
            }
        } catch (Throwable $e) {
            $out['error'] = mb_substr($e->getMessage(), 0, 160);
            $warnings[] = 'Shellová sonda selhala: '.$out['error'];
        }

        return $out;
    }

    /**
     * A game panel (Pterodactyl): the client API key (power, console, backups, every server tool need it), the panel
     * nodes and their maintenance mode, whether the templates mapped on the instance (`options.eggs`) still exist,
     * and whether the daemon of every node answers (one server per node is asked for its resources).
     *
     * @param  list<string>  $warnings
     * @return array{client_api:string, nodes:list<array{id:int,name:string,maintenance:bool,servers:int,daemon:string}>, eggs:array<string,string>, eggs_known:int}
     */
    private function gamePanel(ProviderInstance $instance, GameProvider&GameToolsProvider $adapter, array &$warnings): array
    {
        $game = ['client_api' => $adapter->clientApiStatus(), 'nodes' => [], 'eggs' => [], 'eggs_known' => 0];
        if ($game['client_api'] !== 'ok') {
            $warnings[] = $game['client_api'] === 'missing' ? 'Klientský API klíč herního panelu není uložený; konzole, zálohy a nástroje serverů se zákazníkům nenabízejí.' : 'Herní panel odmítl klientský API klíč; konzole, zálohy a nástroje serverů nefungují.';
        }
        $servers = [];
        try {
            foreach ($adapter->listServers() as $server) {
                $servers[(int) $server['node']][] = $server;
            }
        } catch (Throwable $e) {
            $warnings[] = 'Seznam serverů panelu nelze načíst: '.mb_substr($e->getMessage(), 0, 120);
        }
        foreach ($adapter->listNodes() as $node) {
            $daemon = 'unknown';
            $first = $servers[(int) $node['id']][0] ?? null;
            if ($first !== null && $game['client_api'] === 'ok') {
                try {
                    $adapter->status(new ResourceRef('server', (string) $first['id'], (string) $node['id'], ['identifier' => (string) $first['identifier']]));
                    $daemon = 'up';
                } catch (Throwable) {
                    $daemon = 'down';
                    $warnings[] = "Démon uzlu {$node['name']} neodpovídá; servery na něm nelze ovládat.";
                }
            } elseif ($first === null) {
                $daemon = 'no_servers';
            }
            if ($node['maintenance']) {
                $warnings[] = "Uzel {$node['name']} je v údržbě; nové servery se na něj neumísťují.";
            }
            $game['nodes'][] = ['id' => (int) $node['id'], 'name' => (string) $node['name'], 'maintenance' => (bool) $node['maintenance'], 'servers' => count($servers[(int) $node['id']] ?? []), 'daemon' => $daemon];
        }
        $mapped = (array) $instance->option('eggs', []);
        if ($mapped === []) {
            $warnings[] = 'Instance nemá namapované šablony her (volba eggs); herní servery na ní nelze zřídit.';
        } else {
            try {
                $known = collect($adapter->listEggs())->keyBy(fn ($e) => $e['nest_id'].':'.$e['id']);
                $game['eggs_known'] = $known->count();
                foreach ($mapped as $key => $map) {
                    $id = (int) ($map['nest'] ?? 0).':'.(int) ($map['egg'] ?? 0);
                    $egg = $known->get($id);
                    $game['eggs'][(string) $key] = $egg === null ? 'missing' : (! empty($egg['privileged']) ? 'privileged' : 'ok');
                    if ($egg === null) {
                        $warnings[] = "Šablona {$key} (hnízdo/egg {$id}) na panelu neexistuje; objednávky s ní selžou.";
                    } elseif (! empty($egg['privileged'])) {
                        $warnings[] = "Šablona {$key} vyžaduje privilegovaný kontejner; na sdílených uzlech ji nenabízíme.";
                    }
                }
            } catch (Throwable $e) {
                $warnings[] = 'Šablony her nelze ověřit: '.mb_substr($e->getMessage(), 0, 120);
            }
        }

        return $game;
    }
}
