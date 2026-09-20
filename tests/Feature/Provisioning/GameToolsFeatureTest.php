<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\DelegatedAccessReview;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\ServiceSpecService;
use Onhost\Domain\Services\UsageWatch;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Game servers through the customer API: the tabs a game panel offers (startup, settings, schedules, databases,
 * collaborators, files, ports, backups, the panel account), every change an audited operation, plan limits enforced,
 * destructive changes behind a fresh step-up; the declarative spec and the usage watch cover the family too.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** One game-panel fake: the server's state the test moves through by reference. */
function gameToolsFake(array &$state): void
{
    Http::fake(function (Request $request) use (&$state) {
        if (str_contains($request->url(), 'wings.test/download')) {
            return Http::response(str_repeat('game archive', 40));
        }
        if (! str_starts_with($request->url(), PTERO)) {
            return null;
        }
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $m = $request->method();
        $ok = fn (array $attributes, string $object = 'x') => Http::response(['object' => $object, 'attributes' => $attributes]);
        $list = fn (array $items, string $object = 'x') => Http::response(['object' => 'list', 'data' => array_map(fn ($a) => ['object' => $object, 'attributes' => $a], $items), 'meta' => ['pagination' => ['total_pages' => 1]]]);
        $variables = fn () => array_map(fn ($k, $v) => ['name' => $k, 'description' => '', 'env_variable' => $k, 'default_value' => 'x', 'server_value' => $v, 'is_editable' => true, 'rules' => 'required|string'], array_keys($state['variables']), $state['variables']);

        return match (true) {
            str_ends_with($path, '/servers/e4c1abcd/resources') => $ok(['current_state' => $state['power'], 'is_suspended' => false, 'resources' => ['memory_bytes' => $state['mem'], 'cpu_absolute' => 12.0, 'disk_bytes' => $state['disk'], 'network_rx_bytes' => 0, 'network_tx_bytes' => 0, 'uptime' => 5000]]),
            str_ends_with($path, '/servers/e4c1abcd') && $m === 'GET' => $ok(['identifier' => 'e4c1abcd', 'name' => $state['name'], 'sftp_details' => ['ip' => 'games01.mgmt.test', 'port' => 2022], 'limits' => ['memory' => 8192, 'disk' => 61440, 'cpu' => 300], 'is_installing' => false, 'is_suspended' => false, 'egg_features' => ['eula'], 'docker_image' => $state['image'], 'invocation' => 'java -jar server.jar', 'relationships' => ['allocations' => ['data' => [['attributes' => ['id' => 11, 'ip' => '89.187.160.10', 'ip_alias' => null, 'port' => 25566, 'is_default' => true]]]]]]),
            str_ends_with($path, '/api/application/servers/77') => $ok(['id' => 77, 'uuid' => 'e4c1-uuid', 'identifier' => 'e4c1abcd', 'user' => 9, 'node' => 2, 'status' => $state['status'], 'container' => ['installed' => $state['status'] === null ? 1 : 0], 'suspended' => false, 'limits' => ['memory' => 8192, 'disk' => 61440, 'cpu' => 300], 'feature_limits' => ['databases' => 2, 'allocations' => 2, 'backups' => 5], 'egg' => 5, 'name' => $state['name']]),
            str_ends_with($path, '/startup') && $m === 'GET' => Http::response(['object' => 'list', 'data' => array_map(fn ($a) => ['object' => 'egg_variable', 'attributes' => $a], $variables()), 'meta' => ['startup_command' => 'java -jar server.jar', 'raw_startup_command' => 'java -jar {{SERVER_JARFILE}}', 'docker_image' => $state['image'], 'docker_images' => ['Java 21' => 'ghcr.io/pterodactyl/yolks:java_21', 'Java 17' => 'ghcr.io/pterodactyl/yolks:java_17']]]),
            str_ends_with($path, '/startup/variable') => (function () use ($request, &$state, $ok) {
                $state['variables'][$request['key']] = $request['value'];

                return $ok(['env_variable' => $request['key'], 'server_value' => $request['value']]);
            })(),
            str_ends_with($path, '/settings/docker-image') => (function () use ($request, &$state) {
                $state['image'] = $request['docker_image'];

                return Http::response('', 204);
            })(),
            str_ends_with($path, '/settings/rename') => (function () use ($request, &$state) {
                $state['name'] = $request['name'];

                return Http::response('', 204);
            })(),
            str_ends_with($path, '/settings/reinstall') => (function () use (&$state) {
                $state['status'] = 'installing';
                $state['reinstalls']++;

                return Http::response('', 204);
            })(),
            str_ends_with($path, '/schedules') && $m === 'GET' => $list(array_values($state['schedules']), 'server_schedule'),
            str_ends_with($path, '/schedules') && $m === 'POST' => (function () use ($request, &$state, $ok) {
                $id = 100 + count($state['schedules']);
                $state['schedules'][$id] = ['id' => $id, 'name' => $request['name'], 'cron' => ['minute' => $request['minute'], 'hour' => $request['hour'], 'day_of_month' => $request['day_of_month'], 'month' => $request['month'], 'day_of_week' => $request['day_of_week']], 'is_active' => true, 'is_processing' => false, 'only_when_online' => false, 'last_run_at' => null, 'next_run_at' => null, 'relationships' => ['tasks' => ['data' => []]]];

                return $ok($state['schedules'][$id], 'server_schedule');
            })(),
            preg_match('~/schedules/(\d+)/tasks$~', $path, $mm) === 1 => (function () use ($request, &$state, $mm, $ok) {
                $state['schedules'][(int) $mm[1]]['relationships']['tasks']['data'][] = ['attributes' => ['id' => 1, 'sequence_id' => $request['sequence_id'], 'action' => $request['action'], 'payload' => $request['payload']]];

                return $ok(['id' => 1], 'schedule_task');
            })(),
            preg_match('~/schedules/(\d+)$~', $path, $mm) === 1 && $m === 'GET' => $ok($state['schedules'][(int) $mm[1]], 'server_schedule'),
            preg_match('~/schedules/(\d+)$~', $path, $mm) === 1 && $m === 'POST' => (function () use ($request, &$state, $mm, $ok) {
                $state['schedules'][(int) $mm[1]]['is_active'] = (bool) $request['is_active'];

                return $ok($state['schedules'][(int) $mm[1]], 'server_schedule');
            })(),
            preg_match('~/schedules/(\d+)$~', $path, $mm) === 1 && $m === 'DELETE' => (function () use (&$state, $mm) {
                unset($state['schedules'][(int) $mm[1]]);

                return Http::response('', 204);
            })(),
            str_ends_with($path, '/databases') && $m === 'GET' => $list(array_values($state['databases']), 'server_database'),
            str_ends_with($path, '/databases') && $m === 'POST' => (function () use ($request, &$state, $ok) {
                $id = 'db'.(count($state['databases']) + 1);
                $state['databases'][$id] = ['id' => $id, 'host' => ['address' => '10.0.0.5', 'port' => 3306], 'name' => 's77_'.$request['database'], 'username' => 'u77_'.$id, 'connections_from' => $request['remote'], 'relationships' => ['password' => ['attributes' => ['password' => 'pw-'.$id]]]];

                return $ok($state['databases'][$id], 'server_database');
            })(),
            preg_match('~/users/(su-\d+)$~', $path, $mm) === 1 && $m === 'DELETE' => (function () use (&$state, $mm) {
                unset($state['subusers'][$mm[1]]);

                return Http::response('', 204);
            })(),
            str_ends_with($path, '/api/application/servers/77/suspend') && $m === 'POST' => (function () use (&$state) {
                $state['suspended'] = true;

                return Http::response('', 204);
            })(),
            str_ends_with($path, '/api/application/servers/77/unsuspend') && $m === 'POST' => (function () use (&$state) {
                $state['suspended'] = false;

                return Http::response('', 204);
            })(),
            str_ends_with($path, '/backups') && $m === 'POST' => $ok(['uuid' => 'bk-final', 'name' => 'final', 'is_successful' => false, 'is_locked' => false, 'bytes' => 0, 'completed_at' => null, 'created_at' => now()->toIso8601String()], 'backup'),
            str_ends_with($path, '/backups') && $m === 'GET' => $list([['uuid' => 'bk-final', 'name' => 'final', 'is_successful' => true, 'is_locked' => false, 'bytes' => 1024, 'completed_at' => now()->toIso8601String(), 'created_at' => now()->toIso8601String()]], 'backup'),
            str_ends_with($path, '/backups/bk-final') => $ok(['uuid' => 'bk-final', 'name' => 'final', 'is_successful' => true, 'is_locked' => false, 'bytes' => 1024, 'completed_at' => now()->toIso8601String(), 'created_at' => now()->toIso8601String()], 'backup'),
            $path === '/api/application/nodes' => $list([['id' => 2, 'name' => 'games01', 'fqdn' => 'wings.test']], 'node'), // a transfer link must point at one of the panel's own daemons
            str_ends_with($path, '/backups/bk-final/download') => Http::response(['object' => 'signed_url', 'attributes' => ['url' => 'https://wings.test/download/backup?token=abc']]),
            str_ends_with($path, '/users') && $m === 'GET' => $list(array_values($state['subusers']), 'server_subuser'),
            str_ends_with($path, '/users') && $m === 'POST' => (function () use ($request, &$state, $ok) {
                $uuid = 'su-'.(count($state['subusers']) + 1);
                $state['subusers'][$uuid] = ['uuid' => $uuid, 'email' => $request['email'], 'permissions' => $request['permissions'], 'created_at' => null];

                return $ok($state['subusers'][$uuid], 'server_subuser');
            })(),
            str_ends_with($path, '/files/list') => $list([['name' => 'server.properties', 'mode' => '-rw-r--r--', 'size' => 100, 'is_file' => true, 'modified_at' => null]], 'file_object'),
            str_ends_with($path, '/files/contents') => Http::response((string) ($state['files'][(string) parse_url($request->url(), PHP_URL_QUERY)] ?? "motd=Vitejte\n"), 200, ['Content-Type' => 'text/plain']),
            str_ends_with($path, '/files/write') => (function () use ($request, &$state) {
                $state['files'][(string) parse_url($request->url(), PHP_URL_QUERY)] = $request->body();

                return Http::response('', 204);
            })(),
            str_ends_with($path, '/network/allocations') && $m === 'GET' => $list(array_values($state['allocations']), 'allocation'),
            str_ends_with($path, '/network/allocations') && $m === 'POST' => (function () use (&$state, $ok) {
                $id = 11 + count($state['allocations']);
                $state['allocations'][$id] = ['id' => $id, 'ip' => '89.187.160.10', 'ip_alias' => null, 'port' => 25555 + $id, 'notes' => null, 'is_default' => false];

                return $ok($state['allocations'][$id], 'allocation');
            })(),
            str_ends_with($path, '/api/application/users/9') && $m === 'GET' => $ok(['id' => 9, 'username' => 'liga_ab12cd', 'email' => 'owner@liga.test', 'first_name' => 'Liga', 'last_name' => 'Customer', 'language' => 'en', 'root_admin' => false], 'user'),
            str_ends_with($path, '/api/application/users/9') && $m === 'PATCH' => (function () use ($request, &$state, $ok) {
                $state['panel_password'] = (string) $request['password'];

                return $ok(['id' => 9, 'username' => 'liga_ab12cd'], 'user');
            })(),
            default => Http::response(['errors' => [['code' => 'NotFoundHttpException', 'detail' => "no fake for {$m} {$path}"]]], 404),
        };
    });
}

function gameToolsState(): array
{
    return ['power' => 'running', 'mem' => 2 * 1024 ** 3, 'disk' => 20 * 1024 ** 3, 'name' => 'mc-liga', 'image' => 'ghcr.io/pterodactyl/yolks:java_21', 'status' => null, 'reinstalls' => 0, 'variables' => ['SERVER_JARFILE' => 'server.jar', 'MOTD' => 'Vitejte'],
        'schedules' => [4 => ['id' => 4, 'name' => 'Noční restart', 'cron' => ['minute' => '0', 'hour' => '4', 'day_of_month' => '*', 'month' => '*', 'day_of_week' => '*'], 'is_active' => true, 'is_processing' => false, 'only_when_online' => false, 'last_run_at' => null, 'next_run_at' => null, 'relationships' => ['tasks' => ['data' => [['attributes' => ['id' => 9, 'sequence_id' => 1, 'action' => 'power', 'payload' => 'restart']]]]]]],
        'databases' => ['db1' => ['id' => 'db1', 'host' => ['address' => '10.0.0.5', 'port' => 3306], 'name' => 's77_stats', 'username' => 'u77_db1', 'connections_from' => '%', 'relationships' => ['password' => ['attributes' => ['password' => 'pw-db1']]]]],
        'subusers' => [], 'files' => [], 'allocations' => [11 => ['id' => 11, 'ip' => '89.187.160.10', 'ip_alias' => null, 'port' => 25566, 'notes' => null, 'is_default' => true]], 'panel_password' => null];
}

it('offers the game tabs, runs every change as an operation, enforces plan limits and asks for a step-up before a reinstall or a panel password', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureGameService($org, ['databases' => 2, 'allocations' => 2, 'subusers' => 1]);
    $state = gameToolsState();
    gameToolsFake($state);
    $this->actingAs($user, 'sanctum');

    $features = $this->getJson("/v1/services/{$service->id}/features")->assertOk()->json('data');
    foreach (['startup', 'game_settings', 'schedule_tools', 'game_databases', 'subusers', 'game_files', 'allocations', 'backup_tools', 'panel_access', 'game_status'] as $key) {
        expect($features['features'][$key]['enabled'])->toBeTrue($key);
    }
    expect($features['features']['game_databases']['limit'])->toBe(2)->and($features['actions'])->toContain('variable.set')->toContain('gamedb.create')->toContain('panel.password')->not->toContain('file.save');
    expect(json_encode($features))->not->toContain('terodactyl');

    // live status and startup: the customer sees state, resources and the variables the egg lets them edit
    expect($this->getJson("/v1/services/{$service->id}/resources/status")->assertOk()->json('data'))->toMatchArray(['state' => 'running', 'mem_limit_bytes' => 8192 * 1048576]);
    $startup = $this->getJson("/v1/services/{$service->id}/resources/startup")->assertOk()->json('data');
    expect($startup['variables'][1])->toMatchArray(['key' => 'MOTD', 'value' => 'Vitejte'])->and($startup['docker_images'])->toHaveCount(2);
    expect($this->getJson("/v1/services/{$service->id}/resources/panel_access")->assertOk()->json('data'))->toMatchArray(['username' => 'liga_ab12cd', 'url' => PTERO]);
    expect($this->getJson("/v1/services/{$service->id}/resources/server_detail")->assertOk()->json('data.sftp.username'))->toBe('e4c1abcd');

    // a variable, the image, the name: one operation each
    $op = fn (string $action, array $params, string $key) => Operation::query()->findOrFail($this->withHeader('Idempotency-Key', $key)->postJson("/v1/services/{$service->id}/actions", ['action' => $action, 'params' => $params])->assertStatus(202)->json('operation_id'));
    expect(driveOperation($op('variable.set', ['key' => 'motd', 'value' => 'Ahoj hráči'], 'g-var'))->state)->toBe(Operation::SUCCEEDED)->and($state['variables']['MOTD'])->toBe('Ahoj hráči');
    expect(driveOperation($op('image.set', ['image' => 'ghcr.io/pterodactyl/yolks:java_17'], 'g-img'))->state)->toBe(Operation::SUCCEEDED)->and($state['image'])->toBe('ghcr.io/pterodactyl/yolks:java_17');
    expect(driveOperation($op('rename', ['name' => 'Liga SMP'], 'g-name'))->state)->toBe(Operation::SUCCEEDED)->and($state['name'])->toBe('Liga SMP')->and($service->fresh()->label)->toBe('Liga SMP');
    $this->flushHeaders();
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'variable.set', 'params' => ['key' => 'bad key', 'value' => 'x']])->assertStatus(422)->assertJsonPath('error', 'action_param_invalid');

    // schedules: toggle, run, create, delete
    expect(driveOperation($op('schedule.toggle', ['remote_id' => '4', 'active' => false], 'g-sch-off'))->state)->toBe(Operation::SUCCEEDED)->and($state['schedules'][4]['is_active'])->toBeFalse();
    expect(driveOperation($op('schedule.create', ['name' => 'Záloha', 'cron' => '0 3 * * *', 'actions' => [['action' => 'backup', 'payload' => '']]], 'g-sch-new'))->state)->toBe(Operation::SUCCEEDED)->and($state['schedules'])->toHaveCount(2);
    $schedules = $this->getJson("/v1/services/{$service->id}/resources/schedules?fresh=1")->assertOk()->json('data');
    expect($schedules)->toHaveCount(2)->and($schedules[1])->toMatchArray(['name' => 'Záloha', 'cron' => '0 3 * * *'])->and($schedules[1]['tasks'][0]['action'])->toBe('backup');
    expect(driveOperation($op('schedule.delete', ['remote_id' => '4'], 'g-sch-del'))->state)->toBe(Operation::SUCCEEDED)->and($state['schedules'])->not->toHaveKey(4);

    // databases: the plan allows two — one exists, one more fits, the third is refused; the password is shown only when asked for
    expect($this->getJson("/v1/services/{$service->id}/resources/game_databases")->assertOk()->json('data.0.password'))->toBeNull();
    expect($this->getJson("/v1/services/{$service->id}/resources/game_databases?reveal=1")->assertOk()->json('data.0.password'))->toBe('pw-db1');
    expect(driveOperation($op('gamedb.create', ['name' => 'shop', 'remote' => '%'], 'g-db-2'))->state)->toBe(Operation::SUCCEEDED)->and($state['databases'])->toHaveCount(2);
    $this->flushHeaders();
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'gamedb.create', 'params' => ['name' => 'third']])->assertStatus(422)->assertJsonPath('error', 'feature_limit_reached');

    // collaborators from a preset (the raw permission keys never come from the customer); the plan allows one
    expect(driveOperation($op('subuser.create', ['email' => 'mod@liga.test', 'preset' => 'files'], 'g-sub'))->state)->toBe(Operation::SUCCEEDED)->and($state['subusers']['su-1']['permissions'])->toContain('file.sftp')->not->toContain('backup.restore');
    $this->flushHeaders();
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'subuser.create', 'params' => ['email' => 'x@liga.test', 'preset' => 'root']])->assertStatus(422)->assertJsonPath('error', 'feature_limit_reached');

    // files: written through the panel API as plain text, paths kept inside the server
    expect(driveOperation($op('gfile.save', ['path' => 'server.properties', 'content' => "motd=Ahoj\n"], 'g-file'))->state)->toBe(Operation::SUCCEEDED)->and($state['files'])->toBe(['file=%2Fserver.properties' => "motd=Ahoj\n"]);
    $this->flushHeaders();
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'gfile.save', 'params' => ['path' => '../etc/passwd', 'content' => 'x']])->assertStatus(422)->assertJsonPath('error', 'action_param_invalid');
    expect($this->get("/v1/services/{$service->id}/files/download?path=server.properties")->assertOk()->getContent())->toBe("motd=Ahoj\n");

    // ports: the second allocation fits the plan, the third does not
    expect(driveOperation($op('allocation.add', [], 'g-alloc-2'))->state)->toBe(Operation::SUCCEEDED)->and($state['allocations'])->toHaveCount(2);
    $this->flushHeaders();
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'allocation.add', 'params' => []])->assertStatus(422)->assertJsonPath('error', 'feature_limit_reached');

    // destructive: a reinstall needs confirm=true and a fresh step-up; so does the panel password (it opens the whole account)
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'reinstall', 'params' => ['confirm' => true]])->assertForbidden()->assertJsonPath('error', 'step_up_required');
    expect(fn () => app(ServiceService::class)->requestAction($service->fresh(), 'reinstall', $this->contextFor($user, $org, 'password'), 'g-reinstall-noconfirm', []))->toThrow(DomainError::class, 'confirm=true');
    $reinstall = app(ServiceService::class)->requestAction($service->fresh(), 'reinstall', $this->contextFor($user, $org, 'password'), 'g-reinstall', ['confirm' => true]);
    expect(driveOperation($reinstall)->state)->toBe(Operation::WAITING)->and($state['reinstalls'])->toBe(1); // the panel reinstalls in the background; the operation waits for the install to finish
    $state['status'] = null;
    expect(driveOperation($reinstall->fresh())->state)->toBe(Operation::SUCCEEDED);
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'panel.password', 'params' => ['password' => 'Nove-Heslo-1234567']])->assertForbidden()->assertJsonPath('error', 'step_up_required');
    expect($state['panel_password'])->toBeNull();
    expect(DB::table('provider_calls')->where('instance_key', 'pterodactyl-games01')->pluck('request')->implode(' '))->not->toContain('CLIENTKEY')->not->toContain('APPLICATIONKEY');
});

it('reads and applies the declarative spec of a game server and measures its usage', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureGameService($org);
    $state = gameToolsState();
    gameToolsFake($state);
    $this->actingAs($user, 'sanctum');

    $spec = $this->getJson("/v1/services/{$service->id}/spec")->assertOk()->json('data');
    expect($spec)->toMatchArray(['name' => 'mc-liga', 'image' => 'ghcr.io/pterodactyl/yolks:java_21', 'variables' => ['SERVER_JARFILE' => 'server.jar', 'MOTD' => 'Vitejte']])->and($spec['schedules'][0])->toMatchArray(['name' => 'Noční restart', 'cron' => '0 4 * * *', 'actions' => [['action' => 'power', 'payload' => 'restart']]])->and($spec['features'])->toBe(['name', 'image', 'variables', 'schedules']);
    expect(ServiceSpecService::sectionsFor('cloud'))->toBe(['firewall']);

    // the same document changes nothing; a changed variable and a new schedule become two operations, an unknown variable is reported
    $same = $this->withHeader('Idempotency-Key', 'g-spec-same')->putJson("/v1/services/{$service->id}/spec", ['spec' => ['name' => 'mc-liga', 'variables' => ['MOTD' => 'Vitejte'], 'schedules' => $spec['schedules']]])->assertOk()->json();
    expect($same['operations'])->toBe([])->and($same['unchanged'])->toBe(['name', 'variables', 'schedules']);
    $changed = $this->withHeader('Idempotency-Key', 'g-spec-2')->putJson("/v1/services/{$service->id}/spec", ['spec' => ['variables' => ['MOTD' => 'Nový svět', 'NOPE' => 'x'], 'schedules' => array_merge($spec['schedules'], [['name' => 'Záloha', 'cron' => '0 3 * * *', 'actions' => [['action' => 'backup', 'payload' => '']]]])]])->assertOk()->json();
    expect(array_column($changed['operations'], 'action'))->toBe(['variable.set', 'schedule.create'])->and($changed['skipped'])->toBe([['section' => 'variables', 'reason' => 'variable_unknown:NOPE']]);
    driveOperations();
    expect($state['variables']['MOTD'])->toBe('Nový svět')->and($state['schedules'])->toHaveCount(2);
    $this->withHeader('Idempotency-Key', 'g-spec-bad')->putJson("/v1/services/{$service->id}/spec", ['spec' => ['php' => '8.3']])->assertStatus(422)->assertJsonPath('error', 'spec_section_unknown');

    // the usage watch measures memory and disk against the plan (8 GB, 60 GB): 57 GB used is critical
    $state['disk'] = 57 * 1024 ** 3;
    $watch = app(UsageWatch::class);
    expect($watch->measure($service))->toMatchArray(['disk' => ['used' => 57 * 1024 ** 3, 'limit' => 60 * 1024 ** 3, 'pct' => 95]]);
    expect($watch->run())->toMatchArray(['checked' => 1, 'critical' => 1, 'errors' => 0]);
    expect($service->fresh()->tags['usage']['level'])->toBe('critical');
});

it('revokes every collaborator when a game server is cancelled, after listing them in the archive (Brain card H346)', function () {
    Storage::fake('local');
    [$user, $org] = $this->customerWithOrganization();
    $service = featureGameService($org, ['subusers' => 3]);
    $state = gameToolsState();
    $state['subusers'] = [
        'su-1' => ['uuid' => 'su-1', 'email' => 'admin@liga.test', 'permissions' => ['control.console'], 'created_at' => null],
        'su-2' => ['uuid' => 'su-2', 'email' => 'mod@liga.test', 'permissions' => ['control.start'], 'created_at' => null],
    ];
    gameToolsFake($state);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'terminate', $this->contextFor($user, $org, 'webauthn'), 'game-cancel-1', ['reason' => 'season over']));
    expect($operation->state)->toBe(Operation::SUCCEEDED);
    expect($state['subusers'])->toBe([])->and($state['suspended'] ?? false)->toBeTrue();

    $cancelled = $service->fresh();
    $revoked = (array) data_get($cancelled->tags, 'deletion.revoked');
    expect($cancelled->state)->toBe('SUSPENDED')->and(array_column($revoked, 'label'))->toBe(['admin@liga.test', 'mod@liga.test'])->and(array_column($revoked, 'kind'))->toBe(['subuser', 'subuser']);

    // the archive written before the revocation still knows who had access — names only, no secret
    $backup = Backup::query()->where('service_id', $service->id)->where('kind', 'final')->firstOrFail();
    $metadata = json_decode((string) Storage::disk('local')->get(data_get($backup->meta, 'set').'/service.json'), true);
    expect(array_column($metadata['access']['subusers'], 'email'))->toBe(['admin@liga.test', 'mod@liga.test']);
    expect(OutboxMessage::query()->where('name', 'service.delegations.revoked')->exists())->toBeTrue();
});

it('takes a removed member off the game servers at once and only reports the collaborators it cannot judge (Brain cards H333, H332)', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureGameService($org, ['subusers' => 5]);
    $contractor = $this->customer(['email' => 'dodavatel@studio.test']);
    OrganizationMembership::query()->create(['organization_id' => $org->id, 'user_id' => $contractor->id, 'role_key' => 'developer', 'state' => 'active']);
    $stranger = $this->customer(['email' => 'byvaly@jinde.test']); // has an ONhost account, was never a member here
    $state = gameToolsState();
    $state['subusers'] = [
        'su-1' => ['uuid' => 'su-1', 'email' => 'dodavatel@studio.test', 'permissions' => ['control.console'], 'created_at' => null],
        'su-2' => ['uuid' => 'su-2', 'email' => 'kamarad@bez-uctu.test', 'permissions' => ['control.start'], 'created_at' => null],
        'su-3' => ['uuid' => 'su-3', 'email' => 'byvaly@jinde.test', 'permissions' => ['file.read'], 'created_at' => null],
    ];
    gameToolsFake($state);

    // the contractor leaves the organization: their panel account goes with them, through an audited operation
    app(OrganizationService::class)->removeMember($org, $contractor, $this->contextFor($owner, $org));
    app(OutboxPublisher::class)->relayPending();
    expect(array_keys($state['subusers']))->toBe(['su-2', 'su-3']);
    $operation = Operation::query()->where('service_id', $service->id)->where('idempotency_key', 'like', 'member-removed:%')->firstOrFail();
    expect($operation->state)->toBe(Operation::SUCCEEDED)->and($operation->actor_type)->toBe('system')->and(data_get($operation->desired, 'action'))->toBe('subuser.delete');

    // the weekly review: an account of someone with an ONhost login who is not a member is reported, an outside friend is not, nothing is deleted
    $stats = app(DelegatedAccessReview::class)->review($org->id);
    expect($stats)->toMatchArray(['organizations' => 1, 'services' => 1, 'findings' => 1, 'errors' => 0]);
    $finding = OutboxMessage::query()->where('name', 'access.review.findings')->latest('created_at')->firstOrFail();
    expect(data_get($finding->payload, 'findings.0.email'))->toBe('byvaly@jinde.test')->and(data_get($finding->payload, 'count'))->toBe(1);
    expect(array_keys($state['subusers']))->toBe(['su-2', 'su-3']);
    expect($stranger->id)->not->toBe($contractor->id);
});
