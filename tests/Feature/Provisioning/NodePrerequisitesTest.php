<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\NodePrerequisites;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Providers\Contracts\Naming;
use Onhost\Providers\IspConfig\IspConfigWebProvider;
use Onhost\Providers\Shell\ScriptedShell;

/*
 * Node prerequisites (audit §5e-8): the platform records what an ISPConfig instance can deliver — API, PHP versions,
 * a working cron API, the job queue, the operator's word on mod_proxy — and offers features only where the node
 * supports them: a broken cron API hides cron, mod_proxy=no hides reverse proxies.
 */

beforeEach(fn () => Http::preventStrayRequests());

it('records the prerequisites of an ISPConfig instance and gates cron and proxy features on them', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $instance = ProviderInstance::query()->where('key', 'ispconfig-shared01')->firstOrFail();
    $cronBroken = true;
    Http::fake(function ($request) use (&$cronBroken) {
        if (! str_starts_with($request->url(), ISP)) {
            return null;
        }
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $answer = match ($function) {
            'login' => 'sess-prereq',
            'sites_web_domain_get' => [],
            'monitor_jobqueue_count' => 3,
            'server_get' => ['hostname' => 's2.example.test'],
            'server_get_php_versions' => [['name' => 'PHP 8.3'], ['name' => 'PHP 8.2'], ['name' => 'PHP 7.4']],
            'sites_cron_get' => $cronBroken ? null : [],
            default => false,
        };
        if ($function === 'sites_cron_get' && $cronBroken) {
            return Http::response(['code' => 'remote_fault', 'message' => 'SQL error in cron module', 'response' => false]);
        }

        return Http::response(['code' => 'ok', 'message' => '', 'response' => $answer]);
    });

    // before any check: cron and proxies offered as the adapter defaults
    $this->actingAs($user, 'sanctum');
    $before = $this->getJson("/v1/services/{$service->id}/features")->assertOk()->json('data.features');
    expect($before['cron']['enabled'])->toBeTrue()->and($before['proxy']['enabled'])->toBeTrue();

    // the check (staff endpoint through the bus): API up, PHP versions, cron API broken, mod_proxy unconfirmed
    $staff = $this->staff('infrastructure_admin');
    $result = $this->actingAs($staff, 'sanctum')->postJson("/v1/staff/integrations/{$instance->key}/prerequisites")->assertOk()->json();
    expect($result['api'])->toBe('up')->and($result['php_versions'])->toBe(['7.4', '8.2', '8.3'])->and($result['cron_api'])->toBe('broken')->and($result['jobqueue'])->toBe(3)->and($result['mod_proxy'])->toBe('unknown')
        ->and(implode(' ', $result['warnings']))->toContain('Cron API na uzlu nefunguje')->toContain('mod_proxy');
    $instance->refresh();
    expect($instance->capabilities['prereqs']['cron_api'])->toBe('broken')->and($instance->capabilities['prereqs']['checked_at'])->not->toBeNull();

    // the customer no longer sees cron; the operator declares mod_proxy=no and proxies go too
    $this->actingAs($user, 'sanctum');
    $after = $this->getJson("/v1/services/{$service->id}/features")->assertOk()->json('data.features');
    expect($after['cron']['enabled'])->toBeFalse()->and($after['proxy']['enabled'])->toBeTrue();
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'cron.create', 'params' => ['schedule' => '* * * * *', 'command' => 'php cron.php']])->assertStatus(422)->assertJsonPath('error', 'feature_unavailable');
    $instance->forceFill(['options' => array_merge((array) $instance->options, ['mod_proxy' => 'no'])])->save();
    app(ProviderRegistry::class)->forget($instance);
    expect($this->getJson("/v1/services/{$service->id}/features")->assertOk()->json('data.features.proxy.enabled'))->toBeFalse();

    // the cron API recovers: the daily check brings cron back
    $cronBroken = false;
    $instance->forceFill(['options' => array_merge((array) $instance->options, ['mod_proxy' => 'yes'])])->save();
    app(ProviderRegistry::class)->forget($instance);
    expect(app(NodePrerequisites::class)->checkAll())->toMatchArray(['checked' => 1, 'ok' => 1, 'warnings' => 0]); // the fixture has the one ISPConfig instance; everything answers, mod_proxy confirmed
    expect($instance->refresh()->capabilities['prereqs']['cron_api'])->toBe('ok');
    app(ProviderRegistry::class)->forget($instance);
    expect($this->getJson("/v1/services/{$service->id}/features")->assertOk()->json('data.features.cron.enabled'))->toBeTrue();
    expect(json_encode(app(NodePrerequisites::class)->check($instance, CommandContext::system('test'))))->not->toContain('remote-secret');
});

it('probes the site agent shell for the toolkit binaries and the proxy module and records the verdict on the instance', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $instance = ProviderInstance::query()->where('key', 'ispconfig-shared01')->firstOrFail();
    $agent = Naming::prefix($service->id).'ag'; // the site's agent user must exist on the node for the shell to count as available
    Cache::put("onhost:ispconfig:agent-keys:{$instance->id}", ['private' => 'test-private-key', 'public' => 'ssh-ed25519 AAAA test'], 3600);
    Http::fake(function ($request) use ($agent) {
        if (! str_starts_with($request->url(), ISP)) {
            return null;
        }
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $answer = match ($function) {
            'login' => 'sess-probe', 'sites_web_domain_get' => [], 'monitor_jobqueue_count' => 0, 'server_get' => ['hostname' => 's2.example.test'], 'server_get_php_versions' => [['name' => 'PHP 8.3']], 'sites_cron_get' => [],
            'sites_shell_user_get' => [['shell_user_id' => 5, 'username' => $agent, 'active' => 'y', 'ssh_rsa' => 'ssh-ed25519 AAAA test']],
            default => false,
        };

        return Http::response(['code' => 'ok', 'message' => '', 'response' => $answer]);
    });
    $shell = new ScriptedShell(['/command -v/' => "PHP=8.3.12\nTOOL:git=1\nTOOL:rsync=1\nTOOL:composer=0\nTOOL:wp=1\nTOOL:unzip=1\nTOOL:tar=1\nPROXY=yes\n"]);
    IspConfigWebProvider::$shellFactory = fn () => $shell;
    try {
        $this->actingAs($this->staff('infrastructure_admin'), 'sanctum');
        $result = $this->postJson("/v1/staff/integrations/{$instance->key}/prerequisites")->assertOk()->json();
        expect($result['shell'])->toMatchArray(['available' => true, 'php_cli' => '8.3.12', 'mod_proxy' => 'yes'])->and($result['shell']['tools'])->toMatchArray(['git' => true, 'composer' => false, 'wp' => true])
            ->and(implode(' ', $result['warnings']))->toContain('chybí nástroje: composer')->and($result['mod_proxy'])->toBe('yes');
        expect($instance->fresh()->option('mod_proxy'))->toBe('yes')->and($shell->calls[0]['options']['timeout'])->toBe(20); // the probe confirmed what the operator would otherwise declare
        expect(data_get($instance->fresh()->capabilities, 'prereqs.shell.php_cli'))->toBe('8.3.12');
    } finally {
        IspConfigWebProvider::$shellFactory = null;
    }
});

it('discovers the nodes of a game panel, checks its client key, daemons and template mapping, and hides the server tools while the client key is missing', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureGameService($org);
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail();
    $instance->forceFill(['options' => ['eggs' => ['minecraft-paper' => ['nest' => 1, 'egg' => 5], 'gone' => ['nest' => 1, 'egg' => 99]]]])->save();
    $clientKeyOk = false;
    Http::fake(function ($request) use (&$clientKeyOk) {
        if (! str_starts_with($request->url(), PTERO)) {
            return null;
        }
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $list = fn (array $items, string $object) => Http::response(['object' => 'list', 'data' => array_map(fn ($a) => ['object' => $object, 'attributes' => $a], $items), 'meta' => ['pagination' => ['total_pages' => 1]]]);

        return match (true) {
            str_ends_with($path, '/api/application/nodes') => $list([['id' => 2, 'name' => 'games01', 'memory' => 131072, 'disk' => 2048000, 'allocated_resources' => ['memory' => 40960, 'disk' => 512000], 'maintenance_mode' => false], ['id' => 3, 'name' => 'games02', 'memory' => 65536, 'disk' => 1024000, 'allocated_resources' => ['memory' => 0, 'disk' => 0], 'maintenance_mode' => true]], 'node'),
            str_ends_with($path, '/api/application/servers') => $list([['id' => 77, 'identifier' => 'e4c1abcd', 'uuid' => 'e4c1-uuid', 'external_id' => null, 'name' => 'mc-liga', 'node' => 2, 'user' => 9, 'egg' => 5, 'suspended' => false, 'container' => ['installed' => 1], 'status' => null, 'limits' => ['memory' => 8192, 'disk' => 61440, 'cpu' => 300], 'allocation' => 11]], 'server'),
            str_ends_with($path, '/api/application/nests') => $list([['id' => 1, 'name' => 'Minecraft']], 'nest'),
            str_ends_with($path, '/api/application/nests/1/eggs') => $list([['id' => 5, 'name' => 'Paper', 'docker_image' => 'x', 'docker_images' => [], 'startup' => 'java', 'config' => ['startup' => ['privileged' => false]]]], 'egg'),
            str_ends_with($path, '/api/client/account') => $clientKeyOk ? Http::response(['object' => 'user', 'attributes' => ['id' => 1]]) : Http::response(['errors' => [['code' => 'AccessDeniedHttpException', 'detail' => 'Unauthenticated.']]], 401),
            str_ends_with($path, '/servers/e4c1abcd/resources') => Http::response(['object' => 'stats', 'attributes' => ['current_state' => 'running', 'resources' => ['memory_bytes' => 1, 'cpu_absolute' => 0, 'disk_bytes' => 1, 'network_rx_bytes' => 0, 'network_tx_bytes' => 0, 'uptime' => 1000]]]),
            str_ends_with($path, '/servers/e4c1abcd') => Http::response(['object' => 'server', 'attributes' => ['identifier' => 'e4c1abcd', 'limits' => ['memory' => 8192, 'disk' => 61440]]]),
            default => Http::response(['errors' => [['code' => 'NotFoundHttpException', 'detail' => 'no fake']]], 404),
        };
    });
    $staff = $this->staff('infrastructure_admin');
    $this->actingAs($staff, 'sanctum');

    // discovery: one scheduler node per panel node, maintenance mode respected, allocated resources as usage
    expect($this->postJson("/v1/staff/integrations/{$instance->key}/discover")->assertOk()->json('nodes'))->toBe(['games01', 'games02']);
    $games02 = Node::query()->where('provider_instance_id', $instance->id)->where('name', 'games02')->firstOrFail();
    expect($games02)->toMatchArray(['role' => 'game', 'state' => 'maintenance', 'remote_id' => '3'])->and($games02->capacity['ram_mb'])->toBe(65536)->and($games02->capacity['disk_gb'])->toBe(1000);
    expect(Node::query()->where('provider_instance_id', $instance->id)->where('name', 'games01')->firstOrFail()->usage['ram_used_mb'])->toBe(40960);

    // the check: the client key is refused, one node in maintenance, one mapped template gone
    $result = $this->postJson("/v1/staff/integrations/{$instance->key}/prerequisites")->assertOk()->json();
    expect($result['client_api'])->toBe('rejected')->and($result['game']['nodes'][0])->toMatchArray(['name' => 'games01', 'servers' => 1, 'daemon' => 'unknown'])->and($result['game']['eggs'])->toBe(['minecraft-paper' => 'ok', 'gone' => 'missing']);
    expect(implode(' ', $result['warnings']))->toContain('odmítl klientský API klíč')->toContain('games02 je v údržbě')->toContain('Šablona gone');
    $this->actingAs($user, 'sanctum');
    $features = $this->getJson("/v1/services/{$service->id}/features")->assertOk()->json('data.features');
    expect($features['startup']['enabled'])->toBeFalse()->and($features['power']['enabled'])->toBeTrue();

    // the key is fixed: the daemon answers through the first server on the node and the tools come back
    $clientKeyOk = true;
    $this->actingAs($staff, 'sanctum');
    $again = $this->postJson("/v1/staff/integrations/{$instance->key}/prerequisites")->assertOk()->json();
    expect($again['client_api'])->toBe('ok')->and($again['game']['nodes'][0]['daemon'])->toBe('up')->and($again['game']['nodes'][1]['daemon'])->toBe('no_servers');
    $this->actingAs($user, 'sanctum');
    expect($this->getJson("/v1/services/{$service->id}/features")->assertOk()->json('data.features.startup.enabled'))->toBeTrue();
    expect(DB::table('provider_calls')->where('instance_key', $instance->key)->pluck('request')->implode(' '))->not->toContain('CLIENTKEY');
});
