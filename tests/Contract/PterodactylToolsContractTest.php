<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\GameToolsProvider;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Pterodactyl\PterodactylGameProvider;

/*
 * Game tools on Pterodactyl (GameToolsProvider): the client API around one server — status, startup, schedules,
 * databases, collaborators, files, allocations, backup housekeeping — and the application API for the customer's
 * panel account and the control plane (eggs, node allocations). Keys never reach the call log.
 */

function pteroTools(): PterodactylGameProvider
{
    $_ENV['PTERODACTYL_TOOLS01_APPLICATION_KEY'] = 'ptla_APPKEYTOOLS1234567890';
    $_ENV['PTERODACTYL_TOOLS01_CLIENT_KEY'] = 'ptlc_CLIENTKEYTOOLS1234567890';
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'pterodactyl-tools01'], ['provider' => 'pterodactyl', 'name' => 'Panel', 'base_url' => 'https://tools.panel.test', 'secret_ref' => 'env://PTERODACTYL_TOOLS01', 'state' => 'active']);
    $registry = app(ProviderRegistry::class);
    $registry->register('pterodactyl', PterodactylGameProvider::class);

    return $registry->forInstance($instance);
}

function pteroServerRef(): ResourceRef
{
    return new ResourceRef('server', '77', '2', ['uuid' => 'e4c1-uuid', 'identifier' => 'e4c1abcd', 'user_id' => 9, 'allocation_id' => 11], 'srv_g1');
}

beforeEach(fn () => Http::preventStrayRequests());

it('reads status, startup and the server detail, sets a variable and the image, renames and reinstalls', function () {
    $variables = ['SERVER_JARFILE' => 'server.jar', 'MINECRAFT_VERSION' => 'latest'];
    Http::fake(function (Request $request) use (&$variables) {
        $url = $request->url();
        $path = (string) parse_url($url, PHP_URL_PATH);

        return match (true) {
            str_ends_with($path, '/servers/e4c1abcd/resources') => Http::response(['object' => 'stats', 'attributes' => ['current_state' => 'running', 'is_suspended' => false, 'resources' => ['memory_bytes' => 3221225472, 'cpu_absolute' => 42.5, 'disk_bytes' => 10737418240, 'network_rx_bytes' => 1000, 'network_tx_bytes' => 2000, 'uptime' => 90000]]]),
            str_ends_with($path, '/servers/e4c1abcd') => Http::response(['object' => 'server', 'attributes' => ['identifier' => 'e4c1abcd', 'name' => 'mc-liga', 'description' => '', 'sftp_details' => ['ip' => 'games01.example.test', 'port' => 2022], 'limits' => ['memory' => 8192, 'disk' => 61440, 'cpu' => 300], 'is_installing' => false, 'is_suspended' => false, 'egg_features' => ['eula', 'java_version'], 'docker_image' => 'ghcr.io/pterodactyl/yolks:java_21', 'invocation' => 'java -jar server.jar',
                'relationships' => ['allocations' => ['data' => [['attributes' => ['id' => 11, 'ip' => '89.187.160.10', 'ip_alias' => 'mc.liga.test', 'port' => 25566, 'notes' => null, 'is_default' => true]]]]]]]),
            str_ends_with($path, '/servers/e4c1abcd/startup') && $request->method() === 'GET' => Http::response(['object' => 'list', 'data' => array_map(fn ($k, $v) => ['object' => 'egg_variable', 'attributes' => ['name' => $k, 'description' => 'd', 'env_variable' => $k, 'default_value' => 'x', 'server_value' => $v, 'is_editable' => $k !== 'MINECRAFT_VERSION', 'rules' => 'required|string']], array_keys($variables), $variables), 'meta' => ['startup_command' => 'java -jar server.jar', 'raw_startup_command' => 'java -jar {{SERVER_JARFILE}}', 'docker_image' => 'ghcr.io/pterodactyl/yolks:java_21', 'docker_images' => ['Java 21' => 'ghcr.io/pterodactyl/yolks:java_21', 'Java 17' => 'ghcr.io/pterodactyl/yolks:java_17']]]),
            str_ends_with($path, '/startup/variable') => (function () use ($request, &$variables) {
                $variables[$request['key']] = $request['value'];

                return Http::response(['object' => 'egg_variable', 'attributes' => ['env_variable' => $request['key'], 'server_value' => $request['value']]]);
            })(),
            str_ends_with($path, '/settings/docker-image'), str_ends_with($path, '/settings/rename'), str_ends_with($path, '/settings/reinstall') => Http::response('', 204),
            default => null,
        };
    });
    $adapter = pteroTools();
    $ref = pteroServerRef();
    expect($adapter)->toBeInstanceOf(GameToolsProvider::class);
    $status = $adapter->status($ref);
    expect($status)->toMatchArray(['state' => 'running', 'cpu_pct' => 42.5, 'mem_bytes' => 3221225472, 'mem_limit_bytes' => 8192 * 1048576, 'disk_limit_bytes' => 61440 * 1048576, 'uptime_s' => 90, 'installing' => false, 'suspended' => false]);
    $detail = $adapter->serverDetail($ref);
    expect($detail['sftp'])->toBe(['host' => 'games01.example.test', 'port' => 2022, 'username' => 'e4c1abcd'])->and($detail['allocation'])->toBe(['ip' => '89.187.160.10', 'port' => 25566, 'alias' => 'mc.liga.test'])->and($detail['egg_features'])->toBe(['eula', 'java_version']);
    $startup = $adapter->startup($ref);
    expect($startup['docker_images'])->toHaveKey('Java 17')->and($startup['variables'][0])->toMatchArray(['key' => 'SERVER_JARFILE', 'value' => 'server.jar', 'editable' => true])->and($startup['variables'][1]['editable'])->toBeFalse();
    expect($adapter->setVariable($ref, 'SERVER_JARFILE', 'paper.jar')->data['value'])->toBe('paper.jar')->and($variables['SERVER_JARFILE'])->toBe('paper.jar');
    expect($adapter->setDockerImage($ref, 'ghcr.io/pterodactyl/yolks:java_17')->data['docker_image'])->toBe('ghcr.io/pterodactyl/yolks:java_17');
    expect($adapter->rename($ref, 'mc-liga-2')->data['name'])->toBe('mc-liga-2');
    $reinstall = $adapter->reinstall($ref);
    expect($reinstall->isAsync())->toBeTrue()->and($reinstall->async->kind)->toBe('ptero_install')->and($reinstall->async->handle)->toBe('77');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/settings/rename') && $r['name'] === 'mc-liga-2' && $r->hasHeader('Authorization', 'Bearer ptlc_CLIENTKEYTOOLS1234567890'));
    expect(DB::table('provider_calls')->where('instance_key', 'pterodactyl-tools01')->pluck('request')->implode(' '))->not->toContain('CLIENTKEYTOOLS')->not->toContain('APPKEYTOOLS');
});

it('lists, toggles, runs and deletes schedules and manages databases, collaborators, allocations and backups', function () {
    $schedule = ['id' => 4, 'name' => 'Noční restart', 'cron' => ['minute' => '0', 'hour' => '4', 'day_of_month' => '*', 'month' => '*', 'day_of_week' => '*'], 'is_active' => true, 'is_processing' => false, 'only_when_online' => false, 'last_run_at' => null, 'next_run_at' => '2026-09-14T04:00:00+00:00', 'relationships' => ['tasks' => ['data' => [['attributes' => ['id' => 9, 'sequence_id' => 1, 'action' => 'power', 'payload' => 'restart']]]]]];
    $locked = false;
    Http::fake(function (Request $request) use (&$schedule, &$locked) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $m = $request->method();

        return match (true) {
            str_ends_with($path, '/schedules') && $m === 'GET' => Http::response(['object' => 'list', 'data' => [['object' => 'server_schedule', 'attributes' => $schedule]]]),
            str_ends_with($path, '/schedules/4') && $m === 'GET' => Http::response(['object' => 'server_schedule', 'attributes' => $schedule]),
            str_ends_with($path, '/schedules/4') && $m === 'POST' => (function () use ($request, &$schedule) {
                $schedule['is_active'] = (bool) $request['is_active'];

                return Http::response(['object' => 'server_schedule', 'attributes' => $schedule]);
            })(),
            str_ends_with($path, '/schedules/4/execute') => Http::response('', 202),
            str_ends_with($path, '/schedules/4') && $m === 'DELETE' => Http::response('', 204),
            str_ends_with($path, '/databases') && $m === 'GET' => Http::response(['object' => 'list', 'data' => [['object' => 'server_database', 'attributes' => ['id' => 'db1', 'host' => ['address' => '10.0.0.5', 'port' => 3306], 'name' => 's77_stats', 'username' => 'u77_abc', 'connections_from' => '%', 'max_connections' => 0, 'relationships' => ['password' => ['attributes' => ['password' => 'p4ss-only-when-asked']]]]]]]),
            str_ends_with($path, '/databases') && $m === 'POST' => Http::response(['object' => 'server_database', 'attributes' => ['id' => 'db2', 'host' => ['address' => '10.0.0.5', 'port' => 3306], 'name' => 's77_'.$request['database'], 'username' => 'u77_new', 'connections_from' => $request['remote'], 'relationships' => ['password' => ['attributes' => ['password' => 'new-password-once']]]]]),
            str_ends_with($path, '/databases/db1/rotate-password') => Http::response(['object' => 'server_database', 'attributes' => ['id' => 'db1', 'host' => ['address' => '10.0.0.5', 'port' => 3306], 'name' => 's77_stats', 'username' => 'u77_abc', 'connections_from' => '%', 'relationships' => ['password' => ['attributes' => ['password' => 'rotated']]]]]),
            str_ends_with($path, '/databases/db1') && $m === 'DELETE' => Http::response('', 204),
            str_ends_with($path, '/users') && $m === 'GET' => Http::response(['object' => 'list', 'data' => [['object' => 'server_subuser', 'attributes' => ['uuid' => 'su-1', 'username' => 'admin2', 'email' => 'admin2@liga.test', 'permissions' => ['control.console'], 'created_at' => '2026-09-01T00:00:00+00:00']]]]),
            str_ends_with($path, '/users') && $m === 'POST' => Http::response(['object' => 'server_subuser', 'attributes' => ['uuid' => 'su-2', 'email' => $request['email'], 'permissions' => $request['permissions']]]),
            str_ends_with($path, '/users/su-1') && $m === 'DELETE' => Http::response('', 204),
            str_ends_with($path, '/network/allocations') && $m === 'GET' => Http::response(['object' => 'list', 'data' => [['object' => 'allocation', 'attributes' => ['id' => 11, 'ip' => '89.187.160.10', 'ip_alias' => null, 'port' => 25566, 'notes' => null, 'is_default' => true]]]]),
            str_ends_with($path, '/network/allocations') && $m === 'POST' => Http::response(['object' => 'allocation', 'attributes' => ['id' => 12, 'ip' => '89.187.160.10', 'ip_alias' => null, 'port' => 25567, 'notes' => null, 'is_default' => false]]),
            str_ends_with($path, '/network/allocations/12/primary') => Http::response(['object' => 'allocation', 'attributes' => ['id' => 12, 'is_default' => true]]),
            str_ends_with($path, '/network/allocations/11') && $m === 'DELETE' => Http::response('', 204),
            str_ends_with($path, '/backups/bk-1') && $m === 'GET' => Http::response(['object' => 'backup', 'attributes' => ['uuid' => 'bk-1', 'is_locked' => $locked, 'is_successful' => true, 'completed_at' => '2026-09-10T00:00:00+00:00']]),
            str_ends_with($path, '/backups/bk-1/lock') => (function () use (&$locked) {
                $locked = ! $locked;

                return Http::response(['object' => 'backup', 'attributes' => ['uuid' => 'bk-1', 'is_locked' => $locked]]);
            })(),
            $path === '/api/application/nodes' => Http::response(['object' => 'list', 'data' => [['object' => 'node', 'attributes' => ['id' => 2, 'name' => 'games01', 'fqdn' => 'games01.example.test']]], 'meta' => ['pagination' => ['total_pages' => 1]]]),
            str_ends_with($path, '/backups/bk-1/download') => Http::response(['object' => 'signed_url', 'attributes' => ['url' => 'https://games01.example.test:8080/download/backup?token=abc']]),
            str_ends_with($path, '/backups/bk-1') && $m === 'DELETE' => Http::response('', 204),
            default => null,
        };
    });
    $adapter = pteroTools();
    $ref = pteroServerRef();

    $list = $adapter->listSchedules($ref);
    expect($list[0])->toMatchArray(['remote_id' => '4', 'name' => 'Noční restart', 'cron' => '0 4 * * *', 'active' => true])->and($list[0]['tasks'][0])->toBe(['action' => 'power', 'payload' => 'restart', 'sequence' => 1]);
    expect($adapter->setScheduleActive($ref, '4', false)->data['active'])->toBeFalse()->and($schedule['is_active'])->toBeFalse();
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/schedules/4') && $r->method() === 'POST' && $r['name'] === 'Noční restart' && $r['hour'] === '4' && $r['is_active'] === false);
    expect($adapter->runSchedule($ref, '4')->data['executed'])->toBeTrue()->and($adapter->deleteSchedule($ref, '4')->data['deleted'])->toBeTrue();

    expect($adapter->listDatabases($ref)[0])->toMatchArray(['remote_id' => 'db1', 'name' => 's77_stats', 'username' => 'u77_abc', 'host' => '10.0.0.5', 'port' => 3306, 'password' => null]);
    expect($adapter->listDatabases($ref, true)[0]['password'])->toBe('p4ss-only-when-asked');
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/databases') && $r->method() === 'GET' && str_contains($r->url(), 'include=password'));
    $created = $adapter->createDatabase($ref, 'stats2', '10.%');
    expect($created->ref->remoteType)->toBe('game_database')->and($created->data)->toMatchArray(['remote_id' => 'db2', 'name' => 's77_stats2', 'password' => 'new-password-once', 'connections_from' => '10.%']);
    expect($adapter->rotateDatabasePassword($ref, 'db1')->data['password'])->toBe('rotated')->and($adapter->deleteDatabase($ref, 'db1')->data['deleted'])->toBeTrue();

    expect($adapter->listSubusers($ref)[0])->toMatchArray(['remote_id' => 'su-1', 'email' => 'admin2@liga.test', 'permissions' => ['control.console']]);
    $sub = $adapter->createSubuser($ref, 'mod@liga.test', GameToolsProvider::SUBUSER_PRESETS['files']);
    expect($sub->data['remote_id'])->toBe('su-2')->and($sub->data['permissions'])->toContain('file.sftp');
    expect($adapter->deleteSubuser($ref, 'su-1')->data['deleted'])->toBeTrue();

    expect($adapter->listAllocations($ref))->toBe([['remote_id' => '11', 'ip' => '89.187.160.10', 'alias' => null, 'port' => 25566, 'notes' => null, 'primary' => true]]);
    expect($adapter->addAllocation($ref)->data['port'])->toBe(25567)->and($adapter->setPrimaryAllocation($ref, '12')->data['primary'])->toBeTrue()->and($adapter->removeAllocation($ref, '11')->data['deleted'])->toBeTrue();

    expect($adapter->lockBackup($ref, 'bk-1', true)->data['locked'])->toBeTrue()->and($locked)->toBeTrue();
    $adapter->lockBackup($ref, 'bk-1', true); // already locked: no toggle
    expect($locked)->toBeTrue()->and(collect(Http::recorded())->filter(fn ($p) => str_ends_with($p[0]->url(), '/backups/bk-1/lock'))->count())->toBe(1);
    expect($adapter->backupDownloadUrl($ref, 'bk-1'))->toStartWith('https://games01.example.test:8080/download/backup')->and($adapter->deleteBackup($ref, 'bk-1')->data['deleted'])->toBeTrue();
});

it('browses, reads and writes files, and handles the customer panel account without touching administrators', function () {
    $users = [9 => ['id' => 9, 'username' => 'liga_ab12cd', 'email' => 'owner@liga.test', 'first_name' => 'Liga', 'last_name' => 'Customer', 'language' => 'en', 'root_admin' => false], 1 => ['id' => 1, 'username' => 'onhost_api', 'email' => 'api@onhost.test', 'root_admin' => true]];
    $written = null;
    Http::fake(function (Request $request) use (&$users, &$written) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $query = [];
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $m = $request->method();

        return match (true) {
            str_ends_with($path, '/files/list') => Http::response(['object' => 'list', 'data' => [['object' => 'file_object', 'attributes' => ['name' => 'server.properties', 'mode' => '-rw-r--r--', 'size' => 1200, 'is_file' => true, 'modified_at' => '2026-09-01T00:00:00+00:00']], ['object' => 'file_object', 'attributes' => ['name' => 'world', 'mode' => 'drwxr-xr-x', 'size' => 4096, 'is_file' => false, 'modified_at' => '2026-09-02T00:00:00+00:00']]]]),
            str_ends_with($path, '/files/contents') => Http::response("motd=Vitejte\nmax-players=20\n", 200, ['Content-Type' => 'text/plain']),
            str_ends_with($path, '/files/write') => (function () use ($request, $query, &$written) {
                $written = [$query['file'] ?? null, $request->body()];

                return Http::response('', 204);
            })(),
            str_ends_with($path, '/files/delete'), str_ends_with($path, '/files/create-folder'), str_ends_with($path, '/files/rename') => Http::response('', 204),
            preg_match('~/api/application/users/(\d+)$~', $path, $mm) === 1 && $m === 'GET' => Http::response(['object' => 'user', 'attributes' => $users[(int) $mm[1]]]),
            preg_match('~/api/application/users/(\d+)$~', $path, $mm) === 1 && $m === 'PATCH' => (function () use ($request, &$users, $mm) {
                $users[(int) $mm[1]]['password_set'] = strlen((string) $request['password']);

                return Http::response(['object' => 'user', 'attributes' => $users[(int) $mm[1]]]);
            })(),
            str_ends_with($path, '/api/application/servers/78') => Http::response(['object' => 'server', 'attributes' => ['id' => 78, 'identifier' => 'adm1n000', 'user' => 1, 'node' => 2]]),
            str_ends_with($path, '/api/client/account') => Http::response(['object' => 'user', 'attributes' => ['id' => 1, 'admin' => true]]),
            default => null,
        };
    });
    $adapter = pteroTools();
    $ref = pteroServerRef();
    $files = $adapter->listFiles($ref, '/');
    expect($files[0])->toMatchArray(['name' => 'world', 'type' => 'dir'])->and($files[1])->toMatchArray(['name' => 'server.properties', 'type' => 'file', 'size' => 1200]); // folders first
    expect($adapter->readFile($ref, 'server.properties'))->toContain('max-players=20');
    $adapter->writeFile($ref, 'server.properties', "motd=Ahoj\n");
    expect($written)->toBe(['/server.properties', "motd=Ahoj\n"]);
    Http::assertSent(fn (Request $r) => str_ends_with((string) parse_url($r->url(), PHP_URL_PATH), '/files/write') && $r->hasHeader('Content-Type', 'text/plain'));
    expect($adapter->deleteFiles($ref, '/', ['old.log'])->data['deleted'])->toBeTrue()->and($adapter->createDirectory($ref, '/', 'plugins')->data['name'])->toBe('plugins')->and($adapter->renameFile($ref, '/', 'a.txt', 'b.txt')->data['to'])->toBe('b.txt');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/files/delete') && $r['root'] === '/' && $r['files'] === ['old.log']);

    $account = $adapter->panelAccount($ref);
    expect($account)->toMatchArray(['url' => 'https://tools.panel.test', 'username' => 'liga_ab12cd', 'email' => 'owner@liga.test', 'remote_id' => '9']);
    expect($adapter->setPanelPassword($ref, 'Nove-Heslo-1234567')->data['changed'])->toBeTrue()->and($users[9]['password_set'])->toBe(18);
    Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/users/9') && $r['username'] === 'liga_ab12cd' && $r['email'] === 'owner@liga.test' && $r->hasHeader('Authorization', 'Bearer ptla_APPKEYTOOLS1234567890'));
    // a server owned by a panel administrator: the customer path refuses to touch the admin account
    $adminServer = new ResourceRef('server', '78', '2', ['identifier' => 'adm1n000'], 'srv_x');
    expect(fn () => $adapter->setPanelPassword($adminServer, 'Nove-Heslo-1234567'))->toThrow(ProviderException::class, 'administrator');
    expect($adapter->clientApiStatus())->toBe('ok');
    expect(DB::table('provider_calls')->where('instance_key', 'pterodactyl-tools01')->pluck('request')->implode(' '))->not->toContain('Nove-Heslo');
});

it('lists servers, eggs and node allocations for the control plane and creates allocation ranges', function () {
    Http::fake(function (Request $request) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $m = $request->method();

        return match (true) {
            str_ends_with($path, '/api/application/servers') => Http::response(['object' => 'list', 'data' => [['object' => 'server', 'attributes' => ['id' => 77, 'external_id' => 'ord-9:provision.game:v1', 'uuid' => 'e4c1-uuid', 'identifier' => 'e4c1abcd', 'name' => 'mc-liga', 'suspended' => false, 'limits' => ['memory' => 8192, 'disk' => 61440, 'cpu' => 300], 'user' => 9, 'node' => 2, 'allocation' => 11, 'egg' => 5, 'container' => ['installed' => 1], 'status' => null]]], 'meta' => ['pagination' => ['total_pages' => 1]]]),
            str_ends_with($path, '/api/application/nests') => Http::response(['object' => 'list', 'data' => [['object' => 'nest', 'attributes' => ['id' => 1, 'name' => 'Minecraft']]], 'meta' => ['pagination' => ['total_pages' => 1]]]),
            str_ends_with($path, '/api/application/nests/1/eggs') => Http::response(['object' => 'list', 'data' => [['object' => 'egg', 'attributes' => ['id' => 5, 'name' => 'Paper', 'docker_image' => 'ghcr.io/pterodactyl/yolks:java_21', 'docker_images' => ['Java 21' => 'ghcr.io/pterodactyl/yolks:java_21'], 'startup' => 'java -jar {{SERVER_JARFILE}}', 'config' => ['startup' => ['privileged' => false]]]]], 'meta' => ['pagination' => ['total_pages' => 1]]]),
            str_ends_with($path, '/api/application/nodes/2/allocations') && $m === 'GET' => Http::response(['object' => 'list', 'data' => [['object' => 'allocation', 'attributes' => ['id' => 11, 'ip' => '89.187.160.10', 'alias' => null, 'port' => 25566, 'assigned' => true]], ['object' => 'allocation', 'attributes' => ['id' => 12, 'ip' => '89.187.160.10', 'alias' => null, 'port' => 25567, 'assigned' => false]]], 'meta' => ['pagination' => ['total_pages' => 1]]]),
            str_ends_with($path, '/api/application/nodes/2/allocations') && $m === 'POST' => Http::response('', 204),
            default => null,
        };
    });
    $adapter = pteroTools();
    expect($adapter->listServers()[0])->toMatchArray(['id' => 77, 'identifier' => 'e4c1abcd', 'external_id' => 'ord-9:provision.game:v1', 'node' => 2, 'user' => 9, 'egg' => 5, 'installed' => true, 'memory' => 8192]);
    expect($adapter->listEggs())->toBe([['nest_id' => 1, 'nest' => 'Minecraft', 'id' => 5, 'name' => 'Paper', 'docker_image' => 'ghcr.io/pterodactyl/yolks:java_21', 'docker_images' => ['Java 21' => 'ghcr.io/pterodactyl/yolks:java_21'], 'startup' => 'java -jar {{SERVER_JARFILE}}', 'privileged' => false]]);
    $allocations = $adapter->nodeAllocations(2);
    expect($allocations)->toHaveCount(2)->and($allocations[0]['assigned'])->toBeTrue()->and($allocations[1]['assigned'])->toBeFalse();
    expect($adapter->createAllocations(2, '89.187.160.10', ['25570-25579', '25600'], 'mc.liga.test')->data['ports'])->toBe(['25570-25579', '25600']);
    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/nodes/2/allocations') && $r['ip'] === '89.187.160.10' && $r['alias'] === 'mc.liga.test' && $r['ports'] === ['25570-25579', '25600']);
});

it('follows a transfer link only to one of the panel\'s own daemons, with the TLS settings of the instance', function () {
    $pem = "-----BEGIN CERTIFICATE-----\nMIIBszCCAVmgAwIBAgIUOnhostTestPinnedCertificate000000000wCgYIKoZI\n-----END CERTIFICATE-----";
    $adapter = pteroTools();
    ProviderInstance::query()->where('key', 'pterodactyl-tools01')->update(['options' => json_encode(['tls_ca' => $pem])]);
    app(ProviderRegistry::class)->forget(ProviderInstance::query()->where('key', 'pterodactyl-tools01')->firstOrFail());
    $adapter = app(ProviderRegistry::class)->forInstance(ProviderInstance::query()->where('key', 'pterodactyl-tools01')->firstOrFail());
    $link = 'https://games01.example.test:8080/download/backup?token=abc';
    $seen = [];
    Http::fake(function (Request $request) use (&$link, &$seen) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $seen[] = $request->url();

        return match (true) {
            $path === '/api/application/nodes' => Http::response(['object' => 'list', 'data' => [['object' => 'node', 'attributes' => ['id' => 2, 'name' => 'games01', 'fqdn' => 'games01.example.test']]], 'meta' => ['pagination' => ['total_pages' => 1]]]),
            str_ends_with($path, '/backups/bk-1/download') => Http::response(['object' => 'signed_url', 'attributes' => ['url' => $link]]),
            str_starts_with($request->url(), 'https://games01.example.test:8080/download/backup') => Http::response(str_repeat('archive', 30)),
            default => Http::response(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'no fake']]], 404),
        };
    });
    $target = tempnam(sys_get_temp_dir(), 'onhost-dl-');

    // the instance's certificate was pasted in the console: it used to be read by every adapter but this one
    expect($adapter->downloadBackup(pteroServerRef(), 'bk-1', $target))->toBe(210)->and(is_file(storage_path('app/tls/pterodactyl-tools01.pem')))->toBeTrue();

    // a panel that was broken into names an address inside the management network: nothing is fetched from it
    $link = 'http://10.0.0.5:8006/api2/json/access/ticket';
    $before = count($seen);
    expect(fn () => $adapter->downloadBackup(pteroServerRef(), 'bk-1', $target))->toThrow(ProviderException::class, 'not one of its daemons');
    expect(fn () => $adapter->backupDownloadUrl(pteroServerRef(), 'bk-1'))->toThrow(ProviderException::class, 'not one of its daemons');
    expect(collect(array_slice($seen, $before))->contains(fn (string $u) => str_contains($u, '10.0.0.5')))->toBeFalse();
    @unlink($target);
    @unlink(storage_path('app/tls/pterodactyl-tools01.pem'));
});
