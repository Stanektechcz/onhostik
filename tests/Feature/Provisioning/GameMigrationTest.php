<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\OperationService;
use Onhost\Domain\Provisioning\Workflows\GameMigrationWorkflow;
use Onhost\Domain\Services\Models\GameServer;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Game server migration (audit §5g-2): staff move a server to another node of the same panel as one saga — stop,
 * backup, the same server on the target, the archive streamed daemon to daemon, the platform switched over, the
 * source deleted. A failure before the switch leaves the customer exactly where they were.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

/** The panel double for a two-node game panel; `$state` records what happened, `$failUpload` breaks the transfer. */
/**
 * The collaborators endpoint of the panel's client API, per server identifier. `$state['panel_widens']` makes the panel
 * give more than it was asked for, `$state['panel_forgets']` lists permissions it does not know and silently drops.
 */
function gameMigrationUsers(array &$state, Request $request, string $server, ?string $uuid)
{
    $state['subusers'][$server] ??= [];
    $present = fn (array $u) => ['object' => 'server_subuser', 'attributes' => $u];
    if ($request->method() === 'GET') {
        return Http::response(['object' => 'list', 'data' => array_map($present, array_values($state['subusers'][$server]))]);
    }
    if ($request->method() === 'DELETE') {
        unset($state['subusers'][$server][(string) $uuid]);
        $state['subuser_calls'][] = ['delete', $server, (string) $uuid];

        return Http::response('', 204);
    }
    $permissions = array_values(array_diff((array) $request->data()['permissions'], (array) ($state['panel_forgets'] ?? [])));
    if ($state['panel_widens'] ?? false) {
        $permissions[] = 'user.create';
    }
    $id = 'su-'.$server.'-'.(count($state['subusers'][$server]) + 1);
    $state['subusers'][$server][$id] = ['uuid' => $id, 'email' => (string) $request->data()['email'], 'username' => null, 'permissions' => $permissions, 'created_at' => '2026-09-19T10:00:00+00:00'];
    $state['subuser_calls'][] = ['create', $server, (string) $request->data()['email']];

    return Http::response($present($state['subusers'][$server][$id]));
}

function gameMigrationPanel(array &$state, bool $failUpload = false): void
{
    Http::fake(function (Request $request) use (&$state, $failUpload) {
        $url = $request->url();
        $m = $request->method();
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (str_starts_with($url, 'https://wings01.test/')) {
            $state['calls'][] = [$m, $url];

            return Http::response('TARGZ-BYTES', 200, ['Content-Type' => 'application/gzip']);
        }
        if (str_starts_with($url, 'https://wings02.test/')) {
            $state['calls'][] = [$m, $url];

            return $failUpload ? Http::response('boom', 500) : Http::response(['ok' => true]);
        }
        if (! str_starts_with($url, PTERO)) {
            return null;
        }
        $state['calls'][] = [$m, $path, $request->data()];
        $list = fn (array $items, string $object) => Http::response(['object' => 'list', 'data' => array_map(fn ($a) => ['object' => $object, 'attributes' => $a], $items), 'meta' => ['pagination' => ['total_pages' => 1]]]);
        $server = fn (int $id, int $node, string $identifier, string $uuid, int $allocation, ?string $status) => Http::response(['object' => 'server', 'attributes' => [
            'id' => $id, 'external_id' => $id === 77 ? 'order-item-1' : 'gmig', 'uuid' => $uuid, 'identifier' => $identifier, 'name' => 'mc-liga', 'suspended' => false, 'status' => $status, 'user' => 9, 'node' => $node, 'allocation' => $allocation, 'nest' => 1, 'egg' => 3,
            'limits' => ['memory' => 8192, 'swap' => 0, 'disk' => 61440, 'io' => 500, 'cpu' => 300], 'feature_limits' => ['databases' => 2, 'allocations' => 2, 'backups' => 5],
            'container' => ['startup_command' => 'java -jar {{SERVER_JARFILE}}', 'image' => 'ghcr.io/pterodactyl/yolks:java_21', 'installed' => $status === null ? 1 : 0, 'environment' => ['SERVER_JARFILE' => 'server.jar', 'MOTD' => 'Vitejte', 'P_SERVER_UUID' => $uuid]],
        ]]);

        return match (true) {
            $path === '/api/client/servers/e4c1abcd/power', $path === '/api/client/servers/f00dbabe/power' => (function () use (&$state, $path, $request) {
                $state['power'][] = [substr($path, 20, 8), $request->data()['signal'] ?? null];

                return Http::response('', 204);
            })(),
            $path === '/api/client/servers/e4c1abcd/resources' => Http::response(['object' => 'stats', 'attributes' => ['current_state' => 'offline', 'is_suspended' => false, 'resources' => ['memory_bytes' => 0, 'cpu_absolute' => 0, 'disk_bytes' => 0, 'network_rx_bytes' => 0, 'network_tx_bytes' => 0, 'uptime' => 0]]]),
            $path === '/api/client/servers/e4c1abcd' => Http::response(['object' => 'server', 'attributes' => ['identifier' => 'e4c1abcd', 'name' => 'mc-liga', 'is_installing' => false, 'is_suspended' => false, 'limits' => ['memory' => 8192, 'disk' => 61440]]]),
            $path === '/api/client/servers/e4c1abcd/backups' && $m === 'POST' => Http::response(['object' => 'backup', 'attributes' => ['uuid' => 'bk-1', 'name' => $request->data()['name'], 'is_successful' => false, 'is_locked' => false, 'bytes' => 0, 'completed_at' => null, 'created_at' => now()->toIso8601String()]]),
            $path === '/api/client/servers/e4c1abcd/backups/bk-1' => Http::response(['object' => 'backup', 'attributes' => ['uuid' => 'bk-1', 'name' => 'onhost-migration', 'is_successful' => true, 'is_locked' => false, 'bytes' => 123456, 'checksum' => 'sha1:x', 'completed_at' => now()->toIso8601String(), 'created_at' => now()->toIso8601String()]]),
            $path === '/api/client/servers/e4c1abcd/backups/bk-1/download' => Http::response(['object' => 'signed_url', 'attributes' => ['url' => 'https://wings01.test/download/backup?token=src-jwt']]),
            $path === '/api/application/nodes' => $list([['id' => 2, 'name' => 'games01', 'fqdn' => 'wings01.test'], ['id' => 3, 'name' => 'games02', 'fqdn' => 'wings02.test']], 'node'),
            $path === '/api/application/nodes/3/allocations' => $list([['id' => 31, 'ip' => '203.0.113.20', 'alias' => null, 'port' => 25566, 'assigned' => false], ['id' => 32, 'ip' => '203.0.113.20', 'alias' => null, 'port' => 25567, 'assigned' => true]], 'allocation'),
            $path === '/api/application/servers/77' && $m === 'GET' => $server(77, 2, 'e4c1abcd', '11111111-1111-4111-8111-111111111111', 11, null),
            str_starts_with($path, '/api/application/servers/external/') => Http::response(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'no server']]], 404),
            $path === '/api/application/nests/1/eggs/3' => Http::response(['object' => 'egg', 'attributes' => ['id' => 3, 'name' => 'Paper', 'docker_image' => 'ghcr.io/pterodactyl/yolks:java_21', 'docker_images' => [], 'startup' => 'java -jar {{SERVER_JARFILE}}', 'config' => ['startup' => ['privileged' => false]],
                'relationships' => ['variables' => ['data' => [['attributes' => ['env_variable' => 'SERVER_JARFILE', 'default_value' => 'server.jar', 'rules' => 'required|string', 'user_editable' => true]], ['attributes' => ['env_variable' => 'MOTD', 'default_value' => 'A Minecraft server', 'rules' => 'nullable|string', 'user_editable' => true]]]]]]]),
            $path === '/api/application/servers' && $m === 'POST' => (function () use (&$state, $request, $server) {
                $state['created'] = $request->data();

                return $server(88, 3, 'f00dbabe', '22222222-2222-4222-8222-222222222222', 31, 'installing');
            })(),
            $path === '/api/application/servers/88' && $m === 'GET' => $server(88, 3, 'f00dbabe', '22222222-2222-4222-8222-222222222222', 31, null),
            $path === '/api/client/servers/f00dbabe/files/upload' => Http::response(['object' => 'signed_url', 'attributes' => ['url' => 'https://wings02.test/upload?token=dst-jwt']]),
            $path === '/api/client/servers/f00dbabe/files/decompress', $path === '/api/client/servers/f00dbabe/files/delete' => (function () use (&$state, $path, $request) {
                $state['files'][] = [basename($path), $request->data()];

                return Http::response('', 204);
            })(),
            $path === '/api/application/servers/77' && $m === 'DELETE', $path === '/api/application/servers/88' && $m === 'DELETE' => (function () use (&$state, $path) {
                $state['deleted'][] = (int) explode('/', $path)[4];

                return Http::response('', 204);
            })(),
            $path === '/api/client/account' => Http::response(['object' => 'user', 'attributes' => ['id' => 1, 'admin' => true]]),
            preg_match('#^/api/client/servers/(e4c1abcd|f00dbabe)/users(?:/([\w-]+))?$#', $path, $u) === 1 => gameMigrationUsers($state, $request, $u[1], $u[2] ?? null),
            default => Http::response(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => "no fake for {$m} {$path}"]]], 404),
        };
    });
}

it('moves a game server to another node of its panel: stop, backup, rebuild, transfer, switch, clean-up — and tells the customer the new address', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureGameService($org);
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail();
    $target = Node::query()->create(['provider_instance_id' => $instance->id, 'name' => 'games02', 'region_code' => 'cz1', 'role' => 'game', 'state' => 'active', 'capacity' => ['cpu_cores' => 32, 'ram_mb' => 131072, 'disk_gb' => 2000], 'usage' => [], 'remote_id' => '3']);
    GameServer::query()->create(['service_id' => $service->id, 'egg_key' => 'minecraft-paper', 'nest_id' => 1, 'egg_id' => 3, 'ptero_id' => 77, 'ptero_uuid' => '11111111-1111-4111-8111-111111111111', 'ptero_identifier' => 'e4c1abcd', 'ptero_user_id' => 9, 'ptero_node_id' => 2, 'allocation' => ['id' => 11, 'ip' => '203.0.113.10', 'port' => 25565], 'memory_mb' => 8192, 'cpu_pct' => 300, 'disk_mb' => 61440]);
    $state = ['calls' => [], 'power' => [], 'files' => [], 'deleted' => []];
    gameMigrationPanel($state);
    $sourceBinding = $service->primaryBinding();

    $staff = $this->staff('infrastructure_admin');
    $this->actingAs($staff, 'sanctum');
    $started = $this->withHeader('Idempotency-Key', 'gmig-1')->postJson("/v1/staff/services/{$service->id}/migrate", ['target_node_id' => 'games02', 'reason' => 'údržba uzlu games01'])->assertStatus(202)->json();
    $this->flushHeaders();
    expect($started['kind'])->toBe('game.migrate');
    $operation = driveOperation(Operation::query()->findOrFail($started['id']));
    expect($operation->state)->toBe(Operation::SUCCEEDED)->and($operation->context['swapped'])->toBeTrue()->and($operation->context['address'])->toBe('203.0.113.20:25566')->and($operation->context['backup_uuid'])->toBe('bk-1');

    // the source was stopped before the backup, the target stopped before the import and started after the switch, the source deleted last
    expect($state['power'])->toBe([['e4c1abcd', 'stop'], ['f00dbabe', 'stop'], ['f00dbabe', 'start']])->and($state['deleted'])->toBe([77]);
    expect($state['created'])->toMatchArray(['egg' => 3, 'user' => 9, 'docker_image' => 'ghcr.io/pterodactyl/yolks:java_21', 'startup' => 'java -jar {{SERVER_JARFILE}}', 'allocation' => ['default' => 31]])
        ->and($state['created']['environment'])->toBe(['SERVER_JARFILE' => 'server.jar', 'MOTD' => 'Vitejte'])->and($state['created']['limits']['memory'])->toBe(8192)->and($state['created']['limits']['cpu'])->toBe(300);
    // the archive went daemon to daemon: signed download, signed upload into the root, unpacked, the archive removed
    $wings = array_values(array_filter($state['calls'], fn ($c) => str_starts_with($c[1], 'https://wings')));
    expect($wings)->toBe([['GET', 'https://wings01.test/download/backup?token=src-jwt'], ['POST', 'https://wings02.test/upload?token=dst-jwt&directory=%2F']]);
    expect($state['files'])->toBe([['decompress', ['root' => '/', 'file' => 'onhost-migration.tar.gz']], ['delete', ['root' => '/', 'files' => ['onhost-migration.tar.gz']]]]);
    expect(collect($state['calls'])->contains(fn ($c) => str_contains(json_encode($c), 'src-jwt') && $c[0] === 'GET' && str_starts_with($c[1], PTERO)))->toBeFalse(); // the signed links never hit the panel API log

    // the platform points at the new server: the same binding row, the game server row, the node, the access address
    $binding = $sourceBinding->fresh();
    expect($binding->remote_id)->toBe('88')->and($binding->remote_node)->toBe('3')->and($binding->meta['identifier'])->toBe('f00dbabe')->and(ProviderBinding::query()->where('service_id', $service->id)->count())->toBe(1);
    $game = GameServer::query()->where('service_id', $service->id)->firstOrFail();
    expect($game->ptero_id)->toBe(88)->and($game->ptero_identifier)->toBe('f00dbabe')->and($game->ptero_node_id)->toBe(3)->and($game->allocation['port'])->toBe(25566);
    $service->refresh();
    expect($service->node_id)->toBe($target->id)->and($service->state)->toBe('ACTIVE')->and($service->tags['access']['address'])->toBe('203.0.113.20:25566')->and($service->tags['access']['identifier'])->toBe('f00dbabe');
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('title', 'Server mc-liga byl přestěhován')->value('body'))->toContain('203.0.113.20:25566');

    // the console lists the migration among the game operations
    $queue = collect($this->getJson('/v1/staff/game')->assertOk()->json('data.queue'));
    expect($queue->where('kind', 'game.migrate')->count())->toBe(0)->and($this->getJson('/v1/staff/game')->json('data.recent'))->toBe(1); // finished: no longer queued, counted as recent
});

it('undoes a migration that fails before the switch: the half-built target is deleted, the source starts again, nothing points elsewhere', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureGameService($org);
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail();
    Node::query()->create(['provider_instance_id' => $instance->id, 'name' => 'games02', 'region_code' => 'cz1', 'role' => 'game', 'state' => 'active', 'capacity' => ['cpu_cores' => 32, 'ram_mb' => 131072, 'disk_gb' => 2000], 'usage' => [], 'remote_id' => '3']);
    $state = ['calls' => [], 'power' => [], 'files' => [], 'deleted' => []];
    gameMigrationPanel($state, failUpload: true);
    $sourceBinding = $service->primaryBinding();
    $sourceNode = $service->node_id;

    // per node: the node is drained and every server of it gets its own saga; the scheduler picks the target (the only other active game node)
    $staff = $this->staff('infrastructure_admin');
    $this->actingAs($staff, 'sanctum');
    $node = Node::query()->findOrFail($sourceNode);
    $result = $this->withHeader('Idempotency-Key', 'evac-1')->postJson("/v1/staff/integrations/{$instance->key}/game/nodes/{$node->id}/evacuate", ['reason' => 'disk replacement'])->assertStatus(202)->json();
    $this->flushHeaders();
    expect($result['drained'])->toBeTrue()->and($result['started'])->toHaveCount(1)->and($result['skipped'])->toBe([])->and($node->fresh()->state)->toBe('draining');

    $operation = driveOperation(Operation::query()->findOrFail($result['started'][0]['id']));
    expect($operation->state)->toBe(Operation::FAILED)->and($operation->error['message'])->toContain('Data transfer failed')->and($operation->context['target_node_name'])->toBe('games02');
    // compensation: target 88 deleted, source 77 kept and started again; the binding still names the source
    expect($state['deleted'])->toBe([88])->and($state['power'])->toBe([['e4c1abcd', 'stop'], ['f00dbabe', 'stop'], ['e4c1abcd', 'start']]);
    expect($sourceBinding->fresh()->remote_id)->toBe('77')->and(GameMigrationWorkflow::targetBinding($service->id))->toBeNull()->and($service->fresh()->node_id)->toBe($sourceNode);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'internal')->where('title', 'like', 'Stěhování serveru mc-liga selhalo%')->exists())->toBeTrue();

    // refusals: not a game service, a second migration while one is queued, a node of another panel
    $web = featureWebService($org, 'aapanel');
    $this->postJson("/v1/staff/services/{$web->id}/migrate", [])->assertStatus(422)->assertJsonPath('error', 'migration_unsupported');
    $this->postJson("/v1/staff/services/{$service->id}/migrate", ['target_node_id' => 'nope'])->assertStatus(422)->assertJsonPath('error', 'node_unknown'); // refused before any saga starts
    Service::query()->whereKey($service->id)->update(['state' => 'SUSPENDED']);
    $this->postJson("/v1/staff/services/{$service->id}/migrate", [])->assertStatus(409)->assertJsonPath('error', 'service_state_invalid');
});

/** A second game panel (`games02`) with one node, the customer's account already there, and the catalogue template mapped to its own nest/egg ids. */
function gameMigrationSecondPanel(array &$state): ProviderInstance
{
    $_ENV['PTERODACTYL_GAMES02_APPLICATION_KEY'] = 'ptla_SECONDPANELKEY1234567890';
    $_ENV['PTERODACTYL_GAMES02_CLIENT_KEY'] = 'ptlc_SECONDPANELKEY1234567890';
    $instance = ProviderInstance::query()->create(['key' => 'pterodactyl-games02', 'provider' => 'pterodactyl', 'name' => 'Game panel games02', 'region_code' => 'cz1', 'base_url' => 'https://games02.mgmt.test', 'secret_ref' => 'env://PTERODACTYL_GAMES02', 'state' => 'active', 'capabilities' => ['game.create' => true, 'console' => true], 'options' => ['verify_tls' => true, 'eggs' => ['minecraft-paper' => ['nest' => 5, 'egg' => 15, 'name' => 'Paper']]]]);
    Node::query()->create(['provider_instance_id' => $instance->id, 'name' => 'games02-n1', 'region_code' => 'cz1', 'role' => 'game', 'state' => 'active', 'capacity' => ['cpu_cores' => 32, 'ram_mb' => 131072, 'disk_gb' => 2000], 'usage' => [], 'remote_id' => '1']);
    Http::fake(function (Request $request) use (&$state) {
        $url = $request->url();
        $m = $request->method();
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (str_starts_with($url, 'https://wings03.test/')) {
            $state['calls'][] = [$m, $url];

            return Http::response(['ok' => true]);
        }
        if (! str_starts_with($url, 'https://games02.mgmt.test')) {
            return null;
        }
        $state['calls'][] = [$m, 'games02:'.$path, $request->data()];
        $list = fn (array $items, string $object) => Http::response(['object' => 'list', 'data' => array_map(fn ($a) => ['object' => $object, 'attributes' => $a], $items), 'meta' => ['pagination' => ['total_pages' => 1]]]);
        $server = fn (?string $status) => Http::response(['object' => 'server', 'attributes' => ['id' => 91, 'external_id' => 'gmig', 'uuid' => '33333333-3333-4333-8333-333333333333', 'identifier' => 'cafebabe', 'name' => 'mc-liga', 'suspended' => false, 'status' => $status, 'user' => 44, 'node' => 1, 'allocation' => 71, 'nest' => 5, 'egg' => 15,
            'limits' => ['memory' => 8192, 'swap' => 0, 'disk' => 61440, 'io' => 500, 'cpu' => 300], 'feature_limits' => ['databases' => 2, 'allocations' => 2, 'backups' => 5], 'container' => ['startup_command' => 'java -jar {{SERVER_JARFILE}}', 'image' => 'ghcr.io/pterodactyl/yolks:java_21', 'installed' => $status === null ? 1 : 0, 'environment' => ['SERVER_JARFILE' => 'server.jar', 'MOTD' => 'Vitejte']]]]);

        return match (true) {
            $path === '/api/application/users' && $m === 'GET' => $list([['id' => 44, 'email' => 'billing@example.cz', 'username' => 'test']], 'user'),
            $path === '/api/application/nodes' => $list([['id' => 1, 'name' => 'games-b01', 'fqdn' => 'wings03.test']], 'node'),
            $path === '/api/application/nodes/1/allocations' => $list([['id' => 71, 'ip' => '198.51.100.5', 'alias' => null, 'port' => 27015, 'assigned' => false]], 'allocation'),
            str_starts_with($path, '/api/application/servers/external/') => Http::response(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'no server']]], 404),
            $path === '/api/application/nests/5/eggs/15' => Http::response(['object' => 'egg', 'attributes' => ['id' => 15, 'name' => 'Paper', 'docker_image' => 'ghcr.io/pterodactyl/yolks:java_21', 'docker_images' => [], 'startup' => 'java -jar {{SERVER_JARFILE}}', 'config' => ['startup' => ['privileged' => false]],
                'relationships' => ['variables' => ['data' => [['attributes' => ['env_variable' => 'SERVER_JARFILE', 'default_value' => 'server.jar', 'rules' => 'required|string', 'user_editable' => true]], ['attributes' => ['env_variable' => 'MOTD', 'default_value' => 'A Minecraft server', 'rules' => 'nullable|string', 'user_editable' => true]]]]]]]),
            $path === '/api/application/servers' && $m === 'POST' => (function () use (&$state, $request, $server) {
                $state['created2'] = $request->data();

                return $server('installing');
            })(),
            $path === '/api/application/servers/91' && $m === 'GET' => $server(null),
            $path === '/api/client/servers/cafebabe/power' => (function () use (&$state, $request) {
                $state['power'][] = ['cafebabe', $request->data()['signal'] ?? null];

                return Http::response('', 204);
            })(),
            $path === '/api/client/servers/cafebabe/files/upload' => Http::response(['object' => 'signed_url', 'attributes' => ['url' => 'https://wings03.test/upload?token=dst3-jwt']]),
            $path === '/api/client/servers/cafebabe/files/decompress', $path === '/api/client/servers/cafebabe/files/delete' => (function () use (&$state, $path, $request) {
                $state['files'][] = [basename($path), $request->data()];

                return Http::response('', 204);
            })(),
            $path === '/api/application/servers/91' && $m === 'DELETE' => (function () use (&$state) {
                $state['deleted'][] = 91;

                return Http::response('', 204);
            })(),
            preg_match('#^/api/client/servers/(cafebabe)/users(?:/([\w-]+))?$#', $path, $u) === 1 => gameMigrationUsers($state, $request, $u[1], $u[2] ?? null),
            default => Http::response(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => "no fake for {$m} games02:{$path}"]]], 404),
        };
    });

    return $instance;
}

it('moves a server to a node of another game panel: the account and the template of the target panel, the archive across daemons, the panel switched', function () {
    [$user, $org] = $this->customerWithOrganization([], ['billing_email' => 'billing@example.cz']);
    $service = featureGameService($org);
    $state = ['calls' => [], 'power' => [], 'files' => [], 'deleted' => []];
    gameMigrationPanel($state);
    $second = gameMigrationSecondPanel($state);
    $sourceInstance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail();
    $sourceBinding = $service->primaryBinding();

    $staff = $this->staff('infrastructure_admin');
    $this->actingAs($staff, 'sanctum');
    $started = $this->withHeader('Idempotency-Key', 'gmig-x1')->postJson("/v1/staff/services/{$service->id}/migrate", ['target_node_id' => 'games02-n1', 'reason' => 'konsolidace panelů'])->assertStatus(202)->json();
    $this->flushHeaders();
    $operation = driveOperation(Operation::query()->findOrFail($started['id']));
    expect($operation->state)->toBe(Operation::SUCCEEDED)->and($operation->context['cross_panel'])->toBeTrue()->and($operation->context['target_instance_key'])->toBe('pterodactyl-games02')->and($operation->context['address'])->toBe('198.51.100.5:27015');

    // the target panel created the server with its own template ids and the customer's account there; the archive went from the source daemon to the target daemon
    expect($state['created2'])->toMatchArray(['egg' => 15, 'user' => 44, 'allocation' => ['default' => 71], 'docker_image' => 'ghcr.io/pterodactyl/yolks:java_21'])->and($state['created2']['environment'])->toBe(['SERVER_JARFILE' => 'server.jar', 'MOTD' => 'Vitejte']);
    $wings = array_values(array_filter($state['calls'], fn ($c) => str_starts_with($c[1], 'https://wings')));
    expect($wings)->toBe([['GET', 'https://wings01.test/download/backup?token=src-jwt'], ['POST', 'https://wings03.test/upload?token=dst3-jwt&directory=%2F']]);
    expect($state['power'])->toBe([['e4c1abcd', 'stop'], ['cafebabe', 'stop'], ['cafebabe', 'start']])->and($state['deleted'])->toBe([77]);

    // the platform points at the other panel now: binding, service, game server row, node
    $binding = $sourceBinding->fresh();
    expect($binding->provider_instance_id)->toBe($second->id)->and($binding->remote_id)->toBe('91')->and($binding->remote_node)->toBe('1')->and($binding->meta['identifier'])->toBe('cafebabe');
    $service->refresh();
    expect($service->provider_instance_id)->toBe($second->id)->and($service->node_id)->toBe(Node::query()->where('name', 'games02-n1')->value('id'))->and($service->tags['access']['address'])->toBe('198.51.100.5:27015');
    $game = GameServer::query()->where('service_id', $service->id)->firstOrFail();
    expect($game->ptero_id)->toBe(91)->and($game->ptero_user_id)->toBe(44)->and($game->ptero_node_id)->toBe(1)->and($game->nest_id)->toBe(5)->and($game->egg_id)->toBe(15);
    expect(ProviderBinding::query()->where('service_id', $service->id)->count())->toBe(1)->and($sourceInstance->fresh()->state)->toBe('active');

    // a target panel without the template mapped refuses before anything is touched
    $second->forceFill(['options' => ['verify_tls' => true, 'eggs' => []]])->save();
    $other = featureGameService($org);
    $this->postJson("/v1/staff/services/{$other->id}/migrate", ['target_node_id' => 'games02-n1'])->assertStatus(202);
    $refused = driveOperation(Operation::query()->where('service_id', $other->id)->orderByDesc('queued_at')->firstOrFail());
    expect($refused->state)->toBe(Operation::FAILED)->and($refused->error['message'])->toContain('not mapped on the target panel')->and($state['power'])->toHaveCount(3); // nothing stopped for the refused one
});

it('waits for the window the customer chooses: the saga starts at the chosen time, the customer moves it within the window, the mail names both', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureGameService($org);
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail();
    Node::query()->create(['provider_instance_id' => $instance->id, 'name' => 'games02', 'region_code' => 'cz1', 'role' => 'game', 'state' => 'active', 'capacity' => ['cpu_cores' => 32, 'ram_mb' => 131072, 'disk_gb' => 2000], 'usage' => [], 'remote_id' => '3']);
    $state = ['calls' => [], 'power' => [], 'files' => [], 'deleted' => []];
    gameMigrationPanel($state);
    $from = now()->addHours(2)->startOfMinute();
    $to = $from->copy()->addHours(6);

    $staff = $this->staff('infrastructure_admin');
    $this->actingAs($staff, 'sanctum');
    $started = $this->withHeader('Idempotency-Key', 'gmig-w1')->postJson("/v1/staff/services/{$service->id}/migrate", ['target_node_id' => 'games02', 'reason' => 'výměna disků', 'window_from' => $from->toIso8601String(), 'window_to' => $to->toIso8601String()])->assertStatus(202)->json();
    $this->flushHeaders();
    $operation = Operation::query()->findOrFail($started['id']);
    expect($operation->state)->toBe(Operation::PENDING)->and($operation->step)->toBe(0)->and($operation->next_run_at->toIso8601String())->toBe($from->toIso8601String())->and($state['power'])->toBe([]); // nothing touched yet
    $schedule = $service->fresh()->tags['migration'];
    expect($schedule)->toMatchArray(['operation_id' => $operation->id, 'from' => $from->toIso8601String(), 'to' => $to->toIso8601String(), 'starts_at' => $from->toIso8601String(), 'chosen_at' => null]);
    app(OutboxPublisher::class)->relayPending();
    $shown = CarbonImmutable::parse($schedule['starts_at'])->setTimezone((string) config('app.timezone', 'Europe/Prague'))->format('j. n. Y H:i');
    expect(Notification::query()->where('organization_id', $org->id)->where('title', 'Stěhování serveru mc-liga je naplánované')->value('body'))->toContain('Začne '.$shown)->toContain('termín můžete posunout v okně');
    expect(app(OperationService::class)->dispatchDue())->toBe(0); // not due yet

    // the customer sees the schedule and moves the start inside the window; outside it is refused
    $this->actingAs($user, 'sanctum');
    expect($this->getJson("/v1/services/{$service->id}")->assertOk()->json('data.migration.starts_at'))->toBe($from->toIso8601String());
    $chosen = $from->copy()->addHours(3);
    $this->withHeader('Idempotency-Key', 'mw-1')->putJson("/v1/services/{$service->id}/migration", ['starts_at' => $to->copy()->addHour()->toIso8601String()])->assertStatus(422)->assertJsonPath('error', 'migration_window_out_of_range');
    $this->flushHeaders();
    $this->withHeader('Idempotency-Key', 'mw-2')->putJson("/v1/services/{$service->id}/migration", ['starts_at' => $chosen->toIso8601String()])->assertOk()->assertJsonPath('migration.starts_at', $chosen->toIso8601String());
    $this->flushHeaders();
    expect($operation->fresh()->next_run_at->toIso8601String())->toBe($chosen->toIso8601String())->and($service->fresh()->tags['migration']['chosen_at'])->not->toBeNull();
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'internal')->where('title', 'Zákazník posunul stěhování mc-liga')->exists())->toBeTrue();

    // at the chosen time the saga runs like any other and the schedule is gone from the service
    $done = driveOperation($operation->fresh());
    expect($done->state)->toBe(Operation::SUCCEEDED)->and($done->context['swapped'])->toBeTrue()->and($service->fresh()->tags['migration'])->toMatchArray(['state' => 'finished', 'to_node' => 'games02', 'address' => '203.0.113.20:25566'])->and($state['deleted'])->toBe([77]);
    $this->putJson("/v1/services/{$service->id}/migration", ['starts_at' => $chosen->toIso8601String()])->assertStatus(409)->assertJsonPath('error', 'migration_not_scheduled');
});

/** The fixture of the first scenario: one server on games01, a free node games02 on the same panel. */
function collaboratorMigrationFixture(Organization $org): Service
{
    $service = featureGameService($org);
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail();
    Node::query()->create(['provider_instance_id' => $instance->id, 'name' => 'games02', 'region_code' => 'cz1', 'role' => 'game', 'state' => 'active', 'capacity' => ['cpu_cores' => 32, 'ram_mb' => 131072, 'disk_gb' => 2000], 'usage' => [], 'remote_id' => '3']);
    GameServer::query()->create(['service_id' => $service->id, 'egg_key' => 'minecraft-paper', 'nest_id' => 1, 'egg_id' => 3, 'ptero_id' => 77, 'ptero_uuid' => '11111111-1111-4111-8111-111111111111', 'ptero_identifier' => 'e4c1abcd', 'ptero_user_id' => 9, 'ptero_node_id' => 2, 'allocation' => ['id' => 11, 'ip' => '203.0.113.10', 'port' => 25565], 'memory_mb' => 8192, 'cpu_pct' => 300, 'disk_mb' => 61440]);

    return $service;
}

/*
 * Who may do what on the server moves with the server, unchanged (Brain card H341). The new server is a new resource at
 * the panel: without this its collaborators would silently vanish, and a panel that reads a permission list differently
 * could hand a limited helper more than the customer approved.
 */
it('carries the collaborators to the new server with exactly the permissions they had', function () {
    [, $org] = $this->customerWithOrganization();
    $service = collaboratorMigrationFixture($org);
    $this->actingAs($this->staff('infrastructure_admin'), 'sanctum');
    $console = ['control.console', 'control.start', 'control.stop', 'control.restart', 'websocket.connect'];
    $state = ['calls' => [], 'power' => [], 'files' => [], 'deleted' => [], 'subusers' => ['e4c1abcd' => [
        'su-a' => ['uuid' => 'su-a', 'email' => 'Helper@Example.test', 'username' => 'helper', 'permissions' => $console, 'created_at' => null],
        'su-b' => ['uuid' => 'su-b', 'email' => 'builder@example.test', 'username' => 'builder', 'permissions' => ['file.read', 'file.read-content', 'websocket.connect'], 'created_at' => null],
    ]]];
    gameMigrationPanel($state);

    $started = $this->withHeader('Idempotency-Key', 'h341-1')->postJson("/v1/staff/services/{$service->id}/migrate", ['target_node_id' => 'games02', 'reason' => 'údržba'])->assertStatus(202)->json();
    $operation = driveOperation(Operation::query()->findOrFail($started['id']));

    expect($operation->state)->toBe(Operation::SUCCEEDED, json_encode($operation->error))
        ->and($operation->context['collaborators_state'])->toBe('read')->and($operation->context['collaborators_carried'])->toBe(2)->and($operation->context['collaborators_dropped'])->toBe([]);
    $onTarget = collect($state['subusers']['f00dbabe'])->keyBy('email');
    $sorted = fn (array $p) => tap($p, fn (&$x) => sort($x));
    expect($onTarget->keys()->all())->toBe(['helper@example.test', 'builder@example.test'])
        ->and($sorted($onTarget['helper@example.test']['permissions']))->toBe($sorted($console))                    // not one permission more
        ->and($sorted($onTarget['builder@example.test']['permissions']))->toBe(['file.read', 'file.read-content', 'websocket.connect']); // and not one fewer

    // read before anything was stopped, carried before anything was switched
    $paths = array_map(fn (array $c) => $c[0].' '.$c[1], array_values(array_filter($state['calls'], fn (array $c) => isset($c[1]) && str_starts_with((string) $c[1], '/api/'))));
    $firstRead = array_search('GET /api/client/servers/e4c1abcd/users', $paths, true);
    $firstStop = array_search('POST /api/client/servers/e4c1abcd/power', $paths, true);
    $lastCarry = max(array_keys($paths, 'POST /api/client/servers/f00dbabe/users', true));
    $sourceDeleted = array_search('DELETE /api/application/servers/77', $paths, true);
    expect($firstRead)->toBeLessThan($firstStop)->and($lastCarry)->toBeLessThan($sourceDeleted);
});

it('stops before the switch when the target panel would give a collaborator more than was approved, and removes what it created', function () {
    [, $org] = $this->customerWithOrganization();
    $service = collaboratorMigrationFixture($org);
    $this->actingAs($this->staff('infrastructure_admin'), 'sanctum');
    $binding = $service->primaryBinding();
    $state = ['calls' => [], 'power' => [], 'files' => [], 'deleted' => [], 'panel_widens' => true, 'subusers' => ['e4c1abcd' => [
        'su-a' => ['uuid' => 'su-a', 'email' => 'helper@example.test', 'username' => 'helper', 'permissions' => ['control.console', 'websocket.connect'], 'created_at' => null],
    ]]];
    gameMigrationPanel($state);

    $started = $this->withHeader('Idempotency-Key', 'h341-2')->postJson("/v1/staff/services/{$service->id}/migrate", ['target_node_id' => 'games02', 'reason' => 'údržba'])->assertStatus(202)->json();
    $operation = driveOperation(Operation::query()->findOrFail($started['id']));

    expect($operation->state)->toBeIn([Operation::FAILED, 'COMPENSATED'])
        ->and($operation->error['message'])->toContain('more: user.create')->toContain('nothing was switched')->toContain('collaborator_policy=drop');
    // the wrong grant did not survive, the half-built target is gone, the customer runs where they ran
    expect($state['subusers']['f00dbabe'])->toBe([])->and($state['subuser_calls'])->toContain(['delete', 'f00dbabe', 'su-f00dbabe-1'])
        ->and($state['deleted'])->toBe([88])
        ->and($binding->fresh()->remote_id)->toBe('77')
        ->and(collect($state['subusers']['e4c1abcd'])->pluck('email')->all())->toBe(['helper@example.test']);
});

it('moves without a collaborator the target cannot take unchanged only when that is asked for, and names them to the customer', function () {
    [, $org] = $this->customerWithOrganization();
    $service = collaboratorMigrationFixture($org);
    $this->actingAs($this->staff('infrastructure_admin'), 'sanctum');
    $state = ['calls' => [], 'power' => [], 'files' => [], 'deleted' => [], 'panel_forgets' => ['backup.restore'], 'subusers' => ['e4c1abcd' => [
        'su-a' => ['uuid' => 'su-a', 'email' => 'helper@example.test', 'username' => 'helper', 'permissions' => ['control.console', 'websocket.connect'], 'created_at' => null],
        'su-b' => ['uuid' => 'su-b', 'email' => 'backups@example.test', 'username' => 'backups', 'permissions' => ['backup.read', 'backup.restore', 'websocket.connect'], 'created_at' => null],
    ]]];
    gameMigrationPanel($state);
    $this->postJson("/v1/staff/services/{$service->id}/migrate", ['target_node_id' => 'games02', 'collaborator_policy' => 'whatever'])->assertUnprocessable();

    $started = $this->withHeader('Idempotency-Key', 'h341-3')->postJson("/v1/staff/services/{$service->id}/migrate", ['target_node_id' => 'games02', 'reason' => 'údržba', 'collaborator_policy' => 'drop'])->assertStatus(202)->json();
    $operation = driveOperation(Operation::query()->findOrFail($started['id']));

    expect($operation->state)->toBe(Operation::SUCCEEDED, json_encode($operation->error))->and($operation->context['collaborators_carried'])->toBe(1)
        ->and($operation->context['collaborators_dropped'][0])->toMatchArray(['email' => 'backups@example.test'])->and($operation->context['collaborators_dropped'][0]['reason'])->toContain('fewer: backup.restore');
    expect(collect($state['subusers']['f00dbabe'])->pluck('email')->all())->toBe(['helper@example.test']); // the one that could not be carried unchanged is not there with less either
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $service->organization_id)->where('title', 'like', '%spolupracovníky se nepodařilo přenést')->value('body'))->toContain('spolupracovníci: 1.');
});
