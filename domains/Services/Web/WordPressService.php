<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Providers\Contracts\ShellResult;
use Onhost\Providers\Shell\Q;
use Throwable;

/**
 * The WordPress toolkit of a site, read and driven through WP-CLI on the node: versions, pending core/plugin/
 * theme updates, Redis object cache, debug/auto-update flags, checksum integrity. Results are cached for ten
 * minutes because one status costs a shell round-trip; actions run through WordPressWorkflow.
 */
final class WordPressService
{
    private const TTL = 600;

    public function __construct(private readonly ServiceFeatures $features, private readonly StagingService $staging) {}

    /** @return array<string,mixed> */
    public function status(Service $service, bool $fresh = false): array
    {
        $key = 'wp:'.$service->id;
        if (! $fresh) {
            $cached = Cache::get($key);
            if (is_array($cached)) {
                return $cached;
            }
        }
        $features = $this->features->features($service);
        if (empty($features['wordpress']['enabled'])) {
            return ['available' => false, 'installed' => false];
        }
        [$tools, $ref] = $this->features->toolsFor($service);
        $stagingStatus = $this->staging->status($service);
        $base = ['available' => true, 'staging' => ['available' => (bool) $stagingStatus['available'], 'ready' => $stagingStatus['state'] === 'ready', 'domain' => $stagingStatus['staging']['domain'] ?? null], 'checked_at' => now()->toIso8601String()];
        if (! $tools->shellAvailable($ref)) { // jailed panels: the agent user is prepared on first use; report that instead of failing
            try {
                $tools->ensureAgent($ref);
            } catch (Throwable) {
                // reported below
            }

            return array_merge($base, ['installed' => null, 'agent' => 'preparing']);
        }
        $transport = $tools->transport($ref);
        if (! $transport->exists('wp-config.php')) {
            $out = array_merge($base, ['installed' => false]);
            Cache::put($key, $out, self::TTL);

            return $out;
        }
        $wpFull = $tools->wpCommand($ref).' --path='.Q::arg($transport->root());
        $wp = $wpFull.' --skip-plugins --skip-themes';
        [$host, $port] = $this->redisEndpoint($service);
        $script = implode('; ', [
            'echo @@version', "{$wp} core version 2>/dev/null",
            'echo @@core', "{$wp} core check-update --format=json 2>/dev/null",
            'echo @@plugins', "{$wp} plugin list --format=json --fields=name,title,status,version,update,update_version 2>/dev/null",
            'echo @@themes', "{$wp} theme list --format=json --fields=name,title,status,version,update,update_version 2>/dev/null",
            'echo @@home', "{$wp} option get home 2>/dev/null",
            'echo @@debug', "{$wp} config get WP_DEBUG 2>/dev/null",
            'echo @@autoupdate', "{$wp} config get WP_AUTO_UPDATE_CORE 2>/dev/null",
            'echo @@redisplugin', "{$wp} plugin get redis-cache --field=status 2>/dev/null",
            'echo @@redis', "{$wpFull} redis status 2>/dev/null | head -n 4",
            'echo @@redisping', 'timeout 3 bash -c '.Q::arg('echo > /dev/tcp/'.$host.'/'.$port).' 2>/dev/null && echo reachable || echo unreachable',
            'echo @@checksums', "{$wp} core verify-checksums >/dev/null 2>&1 && echo ok || echo bad",
            'echo @@maintenance', "{$wp} maintenance-mode status 2>/dev/null",
            'echo @@dbsize', "{$wp} db size --size_format=b 2>/dev/null",
            'echo @@php', "{$wp} cli info --format=json 2>/dev/null",
            'echo @@end',
        ]);
        $run = $tools->shell($ref)->run($script, ['timeout' => 240, 'user' => $tools->siteUser($ref)]);
        $s = self::sections($run->stdout);
        $json = fn (string $k) => is_array($v = json_decode(trim($s[$k] ?? ''), true)) ? $v : [];
        $plugins = array_values(array_filter($json('plugins'), fn ($p) => isset($p['name'])));
        $themes = array_values(array_filter($json('themes'), fn ($p) => isset($p['name'])));
        $core = $json('core');
        $coreUpdate = is_array($core[0] ?? null) ? $core[0] : null;
        $redisStatus = preg_match('/Status:\s*([A-Za-z ]+)/', (string) ($s['redis'] ?? ''), $m) ? trim($m[1]) : null;
        $out = array_merge($base, [
            'installed' => true,
            'version' => trim($s['version'] ?? '') ?: null,
            'core_update' => $coreUpdate === null ? null : ['version' => (string) ($coreUpdate['version'] ?? ''), 'type' => (string) ($coreUpdate['update_type'] ?? 'major')],
            'plugins' => $plugins,
            'themes' => $themes,
            'updates' => ['core' => $coreUpdate !== null, 'plugins' => count(array_filter($plugins, fn ($p) => ($p['update'] ?? '') === 'available')), 'themes' => count(array_filter($themes, fn ($p) => ($p['update'] ?? '') === 'available'))],
            'home' => trim($s['home'] ?? '') ?: null,
            'debug' => filter_var(trim($s['debug'] ?? ''), FILTER_VALIDATE_BOOLEAN),
            'auto_update_core' => trim($s['autoupdate'] ?? '') ?: null,
            'redis' => ['plugin' => trim($s['redisplugin'] ?? '') ?: null, 'status' => $redisStatus, 'enabled' => $redisStatus !== null && stripos($redisStatus, 'connected') !== false, 'reachable' => trim($s['redisping'] ?? '') === 'reachable', 'host' => $host, 'port' => $port],
            'checksums_ok' => trim($s['checksums'] ?? '') === 'ok',
            'maintenance' => stripos((string) ($s['maintenance'] ?? ''), 'active') !== false && stripos((string) ($s['maintenance'] ?? ''), 'not active') === false,
            'db_size_bytes' => is_numeric(trim($s['dbsize'] ?? '')) ? (int) trim($s['dbsize']) : null,
            'php_version' => (string) (($json('php')['php_version'] ?? null) ?: $service->spec('php_version', '')),
            'shell_ok' => $run->ok(),
        ]);
        Cache::put($key, $out, self::TTL);

        return $out;
    }

    public function forget(Service $service): void
    {
        Cache::forget('wp:'.$service->id);
    }

    /** Run one WP-CLI command in the site root as the site user. */
    public function run(Service $service, string $args, int $timeout = 600, bool $skipPlugins = false): ShellResult
    {
        [$tools, $ref] = $this->features->toolsFor($service);
        $root = $tools->transport($ref)->root();
        $wp = $tools->wpCommand($ref).' --path='.Q::arg($root).($skipPlugins ? ' --skip-plugins --skip-themes' : '');
        $run = $tools->shell($ref)->run($wp.' '.$args, ['timeout' => $timeout, 'user' => $tools->siteUser($ref)]);

        return new ShellResult($run->exitCode, self::clean($run->stdout), $run->stderr, $run->durationMs, $run->timedOut, $run->truncated);
    }

    /** WP-CLI's own PHP deprecation and warning lines (new PHP releases vs. the bundled phar) are noise, not results. */
    public static function clean(string $stdout): string
    {
        $lines = array_filter(preg_split('/\r?\n/', $stdout) ?: [], fn (string $l) => ! preg_match('/^(PHP )?(Deprecated|Warning|Notice|Strict Standards):/', trim($l)));

        return implode("\n", $lines);
    }

    public function installed(Service $service): bool
    {
        [$tools, $ref] = $this->features->toolsFor($service);

        return $tools->transport($ref)->exists('wp-config.php');
    }

    /** @return array{0:string,1:int} */
    public function redisEndpoint(Service $service): array
    {
        $instance = $service->provider_instance_id ? ProviderInstance::query()->find($service->provider_instance_id) : null;

        return [(string) ($instance?->option('redis_host', '127.0.0.1') ?? '127.0.0.1'), (int) ($instance?->option('redis_port', 6379) ?? 6379)];
    }

    /**
     * Is the site answering after a change? Used between the staging update and the production one.
     *
     * @return array{ok:bool, status:?int, error:?string}
     */
    public function healthCheck(string $url): array
    {
        try {
            $response = Http::withoutVerifying()->withUserAgent((string) config('onhost.monitoring.user_agent', 'ONhost-Monitor/1.0'))->timeout(25)->get($url);
        } catch (Throwable $e) {
            return ['ok' => false, 'status' => null, 'error' => mb_substr($e->getMessage(), 0, 200)];
        }
        $body = (string) $response->body();
        $fatal = preg_match('/There has been a critical error|Error establishing a database connection|Fatal error:|Parse error:/i', $body) === 1;

        return ['ok' => $response->status() < 500 && ! $fatal, 'status' => $response->status(), 'error' => $fatal ? 'the site shows a WordPress error page' : ($response->status() >= 500 ? 'HTTP '.$response->status() : null)];
    }

    /** @return array<string,string> */
    private static function sections(string $stdout): array
    {
        $out = [];
        $current = null;
        foreach (preg_split('/\r?\n/', self::clean($stdout)) ?: [] as $line) {
            if (preg_match('/^@@([a-z]+)$/', trim($line), $m)) {
                $current = $m[1];
                $out[$current] = '';

                continue;
            }
            if ($current !== null) {
                $out[$current] .= $line."\n";
            }
        }

        return array_map('trim', $out);
    }
}
