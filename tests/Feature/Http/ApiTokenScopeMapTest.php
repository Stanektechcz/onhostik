<?php

declare(strict_types=1);

use App\Http\Middleware\TokenRouteScope;
use App\Http\Support\ApiContext;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\DnsTemplateSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\TokenScopes;
use Onhost\Domain\Identity\Commands\ApiTokenCommand;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;
use Onhost\Domain\Services\Commands\IssueConsoleTokenCommand;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\DeploySource;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceSpecService;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

/*
 * C13-H2c (TASK-0030): the permission → token-scope decision was a chain of prefixes, so every `service.*` permission that
 * was not managing or deleting fell to `services:read`. A read-only token opened a noVNC console, and the portal's own
 * "operate services" preset (read + power) ran commands and put SSH keys on the server. The decision is now ONE explicit
 * map (TokenScopes) that refuses what it does not name, and a console is a scope of its own that no preset carries.
 */

/** A running VPS on the lab Proxmox (copy of PanelApiTest::panelVps — that file may not be loaded with this one). */
function tokenScopeVps(Organization $org): Service
{
    $instance = pveLab();
    $node = Node::query()->where('name', 'prg1-n2')->firstOrFail();
    $project = Project::query()->where('organization_id', $org->id)->orderBy('created_at')->first();
    $service = Service::query()->create([
        'organization_id' => $org->id, 'project_id' => $project?->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'hostname' => 'vm-scope.cust.onhost.cz', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id,
        'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'], 'entitlements' => ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160], 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => ['access' => ['ipv4' => '192.0.2.2']],
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => 'prg1-n2', 'meta' => [], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "provision:{$service->id}:qemu", 'adapter_version' => '1.0.0']);

    return $service;
}

/** A personal token of `$user` in `$org` with exactly `$scopes`; returns the plain text for the Authorization header. */
function tokenScopeBearer(User $user, Organization $org, array $scopes): string
{
    $token = $user->createToken('scope-map', $scopes);
    $token->accessToken->forceFill(['organization_id' => $org->id])->save();
    app('auth')->forgetGuards();

    return $token->plainTextToken;
}

/** A role for `$user` at the organization, or — `$serviceId` given — on that one service (a guest), or on a project. */
function tokenScopeBind(Organization $org, User $user, string $role, ?string $serviceId = null, ?string $projectId = null): void
{
    [$type, $id] = $serviceId !== null ? ['resource', $serviceId] : ($projectId !== null ? ['project', $projectId] : ['organization', $org->id]);
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => $role, 'scope_type' => $type, 'scope_id' => $id, 'organization_id' => $org->id]);
    app(Authorizer::class)->forget($user);
}

/** A request as a bearer of `$token` (or the portal session when null) — for ApiContext checks outside HTTP. */
function tokenScopeRequest(User $user, mixed $token): Request
{
    $request = Request::create('/v1/services', 'GET');
    if ($token !== null) {
        $user->withAccessToken($token);
    }
    $request->setUserResolver(fn () => $user);

    return $request;
}

/** A finished final archive of `$serviceId` (copy of DeletionLifecycleTest::storedArchive — that file may not be loaded with this one). */
function tokenScopeArchive(string $organizationId, string $serviceId): Backup
{
    Storage::fake('local');
    $set = FinalArchive::PREFIX.'/'.$organizationId.'/'.$serviceId.'-20260901-120000';
    Storage::disk('local')->put($set.'/service.json', json_encode(['service' => ['id' => $serviceId]]));
    Storage::disk('local')->put($set.'/site-files.tar.gz', str_repeat('files', 200));
    Storage::disk('local')->put($set.'/manifest.json', json_encode(['service_id' => $serviceId]));

    return Backup::query()->create([
        'service_id' => $serviceId, 'organization_id' => $organizationId, 'kind' => 'final', 'state' => 'completed', 'protected' => true,
        'started_at' => now()->subDay(), 'finished_at' => now()->subDay(), 'verified_at' => now()->subDay(), 'verify_status' => 'ok', 'size_bytes' => 1024,
        'retention_until' => now()->addDays(60), 'immutable_until' => now()->addDays(60), 'meta' => ['set' => $set, 'family' => 'web', 'parts' => ['service.json', 'site-files.tar.gz'], 'gaps' => []],
    ]);
}

function tokenScopePveFakes(): void
{
    Http::fake([
        PVE.'/nodes/prg1-n2/qemu/1042/status/current' => Http::response(['data' => ['status' => 'running', 'uptime' => 100]]),
        PVE.'/nodes/prg1-n2/qemu/1042/config' => Http::response(pveVmConfig()),
        PVE.'/nodes/prg1-n2/qemu/1042/status/*' => Http::response(['data' => 'UPID:prg1-n2:000A1B2F:0004E1F8:66F0AA14:qmstart:1042:onhost@pve!cp:']),
        PVE.'/nodes/prg1-n2/tasks/*/status' => Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]),
        PVE.'/nodes/prg1-n2/qemu/1042/vncproxy' => Http::response(['data' => ['port' => 5900, 'ticket' => 'PVEVNC:ticket', 'user' => 'onhost@pve!cp']]),
    ]);
}

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class, DnsTemplateSeeder::class]);
    Http::preventStrayRequests();
});

it('has an explicit token decision for every permission in the catalogue, and the UI offers the console only on its own', function () {
    // (1) one decision per catalogue key — a new permission without a decision fails here, not in production
    expect(array_keys(TokenScopes::decisions()))->toEqualCanonicalizing(PermissionCatalog::keys());
    foreach (TokenScopes::decisions() as $permission => $scope) {
        expect($scope === null || in_array($scope, TokenScopes::ALL, true))->toBeTrue("{$permission} maps to an unknown scope {$scope}");
    }
    expect(ApiTokenCommand::SCOPES)->toBe(TokenScopes::ALL)
        ->and(TokenScopes::EXPLICIT_ONLY)->toBe([TokenScopes::SERVICES_CONSOLE])
        ->and(TokenScopes::for('service.console'))->toBe('services:console')
        ->and(TokenScopes::for('service.read'))->toBe('services:read')
        ->and(TokenScopes::for('service.manage'))->toBe('services:power')
        ->and(TokenScopes::for('service.panel_account.manage'))->toBeNull()
        ->and(TokenScopes::for('backup.download'))->toBeNull()
        ->and(TokenScopes::for('backup.policy.manage'))->toBeNull();

    // every permission literal a controller checks is a decided one (the map is the only place that says what a token may do)
    $literals = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('app/Http/Controllers'), FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (! str_ends_with($file->getFilename(), '.php')) {
            continue;
        }
        preg_match_all('/(?:->authorize|->authorizeAction|assertTokenScope|->resolve[A-Za-z]*)(\((?:[^()]++|(?1))*\))/', (string) file_get_contents($file->getPathname()), $calls);
        foreach ($calls[1] as $arguments) {
            preg_match_all("/'([a-z_]+(?:\\.[a-z_]+)+)'/", $arguments, $keys);
            foreach ($keys[1] as $key) {
                $literals[$key] = $file->getFilename();
            }
        }
    }
    expect($literals)->not->toBeEmpty();
    foreach ($literals as $key => $where) {
        expect(array_key_exists($key, TokenScopes::decisions()))->toBeTrue("{$where} checks {$key}, which has no token decision");
    }

    // the panel: the scope list is the server's, and no preset carries the console
    $js = (string) file_get_contents(base_path('apps/surfaces/api/onhost-panel-account.api.js'));
    preg_match('/var SCOPES = \{(.*?)\};/s', $js, $scopes);
    preg_match_all("/'([a-z]+:[a-z]+)':/", $scopes[1] ?? '', $jsScopes);
    expect($jsScopes[1])->toEqualCanonicalizing(TokenScopes::ALL);
    preg_match('/var presets = \[(.*?)\n    \];/s', $js, $presets);
    expect($presets[1] ?? '')->not->toBe('')->not->toContain('services:console')->toContain('EXPLICIT_ONLY');
});

it('refuses a token a permission the map does not know', function () {
    [$owner, $org] = $this->customerWithOrganization();
    expect(TokenScopes::for('service.made_up'))->toBeNull()->and(TokenScopes::for(null))->toBeNull();

    $token = $owner->createToken('all', TokenScopes::ALL)->accessToken;
    $bearer = tokenScopeRequest($owner, $token);
    foreach (['service.made_up', null] as $permission) {
        try {
            app(ApiContext::class)->assertTokenScope($bearer, $permission);
            $this->fail('a token passed an undecided permission: '.var_export($permission, true));
        } catch (DomainError $e) {
            expect($e->status)->toBe(403)->and($e->getMessage())->toBe('This action is not available to API tokens; use the portal.');
        }
    }

    // the portal's own session is untouched: a command without a permission is the bus's business, not the token map's
    $portal = tokenScopeRequest($owner->fresh(), null);
    app(ApiContext::class)->assertTokenScope($portal, null);
    expect(true)->toBeTrue();
});

it('refuses a read-only token a console and every console action', function () {
    tokenScopePveFakes();
    [$owner, $org] = $this->customerWithOrganization();
    $service = tokenScopeVps($org);
    $plain = tokenScopeBearer($owner, $org, ['services:read']);
    $headers = ['X-Organization' => $org->id];

    $this->withToken($plain)->getJson("/v1/services/{$service->id}/console-token", $headers)->assertForbidden()->assertJsonPath('message', 'The API token lacks the services:console scope.');
    app('auth')->forgetGuards();
    $this->withToken($plain)->postJson("/v1/services/{$service->id}/console-token", [], $headers + ['Idempotency-Key' => 'ro-console-post'])->assertForbidden()->assertJsonPath('message', 'The API token lacks the services:console scope.');
    app('auth')->forgetGuards();
    $this->withToken($plain)->getJson("/v1/services/{$service->id}/actions/command.run/preview", $headers)->assertForbidden()->assertJsonPath('message', 'The API token lacks the services:console scope.');

    expect(AuditEvent::query()->where('action', 'service.console')->where('result', 'succeeded')->exists())->toBeFalse();
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'vncproxy'));
});

it('keeps the operate-services preset away from the console, the commands and the SSH keys — and lets it restart', function () {
    tokenScopePveFakes();
    [$owner, $org] = $this->customerWithOrganization();
    $service = tokenScopeVps($org);
    $plain = tokenScopeBearer($owner, $org, ['services:read', 'services:power', 'domains:read', 'dns:write', 'tickets:write']); // the panel's "provoz služeb"
    $headers = ['X-Organization' => $org->id];

    $this->withToken($plain)->postJson("/v1/services/{$service->id}/console-token", [], $headers + ['Idempotency-Key' => 'op-console'])->assertForbidden()->assertJsonPath('message', 'The API token lacks the services:console scope.');

    // every action the bus decides as a console — derived from the one map, so an action moved onto the console later is covered here too
    $commandTask = ['name' => 'nightly', 'cron' => '0 3 * * *', 'actions' => [['action' => 'command', 'payload' => 'say hi']]];
    $consoleActions = [];
    foreach (ServiceActionWorkflow::ACTIONS as $action) {
        $params = $action === 'schedule.create' ? $commandTask : ['command' => 'id'];
        if (call_user_func([ServiceActionCommand::class, 'permissionFor'], $action, $params) === 'service.console') { // a second argument is ignored where the map takes one
            $consoleActions[$action] = $params;
        }
    }
    expect(array_keys($consoleActions))->toContain('command.run', 'command.send', 'shell.create', 'shell.key', 'shell.delete');
    foreach ($consoleActions as $action => $params) {
        app('auth')->forgetGuards();
        $response = $this->withToken($plain)->postJson("/v1/services/{$service->id}/actions", ['action' => $action, 'params' => $params], $headers + ['Idempotency-Key' => "op-{$action}"]);
        expect($response->status())->toBe(403, "{$action} answered {$response->status()}")
            ->and($response->json('message'))->toBe('The API token lacks the services:console scope.', "{$action}: {$response->json('message')}");
    }

    // what the preset is for keeps working
    app('auth')->forgetGuards();
    $this->withToken($plain)->postJson("/v1/services/{$service->id}/power", ['power_action' => 'start'], $headers + ['Idempotency-Key' => 'op-start'])->assertStatus(202);
});

it('gives a token with services:console a console, and its console actions pass the scope check', function () {
    tokenScopePveFakes();
    [$owner, $org] = $this->customerWithOrganization();
    $service = tokenScopeVps($org);
    $web = featureWebService($org, 'aapanel'); // a hosting whose plan offers the terminal: command.run is queued (202), not refused by the service
    $headers = ['X-Organization' => $org->id];

    $plain = tokenScopeBearer($owner, $org, ['services:read', 'services:power', 'services:console']);
    $console = $this->withToken($plain)->getJson("/v1/services/{$service->id}/console-token", $headers)->assertOk();
    expect($console->json('kind'))->toBe('novnc')->and($console->json('token'))->toStartWith('con_')->and(json_encode($console->json()))->not->toContain('PVEVNC');

    app('auth')->forgetGuards();
    $run = $this->withToken($plain)->postJson("/v1/services/{$web->id}/actions", ['action' => 'command.run', 'params' => ['command' => 'id']], $headers + ['Idempotency-Key' => 'con-run']);
    expect($run->status())->toBe(202, "command.run: {$run->status()} {$run->json('message')}");

    // the console scope alone is enough for a console: the route and the service check both ask for the console (D-8)
    app('auth')->forgetGuards();
    $only = tokenScopeBearer($owner, $org, ['services:console']);
    $this->withToken($only)->getJson("/v1/services/{$service->id}/console-token", $headers)->assertOk();

    // … and for the console's commands: the route decides POST …/actions by the action's own permission, not by services:power
    // (round 1: a console-only token was told it lacked services:power on the very actions the scope exists for)
    Operation::query()->where('service_id', $web->id)->update(['state' => Operation::SUCCEEDED, 'finished_at' => now()]); // one run at a time per service
    app('auth')->forgetGuards();
    $alone = $this->withToken($only)->postJson("/v1/services/{$web->id}/actions", ['action' => 'command.run', 'params' => ['command' => 'id']], $headers + ['Idempotency-Key' => 'con-only-run']);
    expect($alone->status())->toBe(202, "console-only command.run: {$alone->status()} {$alone->json('message')}");

    // a console is not a restart: the same token still cannot operate the service
    app('auth')->forgetGuards();
    $this->withToken($only)->postJson("/v1/services/{$service->id}/actions", ['action' => 'power', 'params' => ['power_action' => 'start']], $headers + ['Idempotency-Key' => 'con-only-power'])
        ->assertForbidden()->assertJsonPath('message', 'The API token lacks the services:power scope.');
    app('auth')->forgetGuards();
    $this->withToken($only)->postJson("/v1/services/{$service->id}/power", ['power_action' => 'start'], $headers + ['Idempotency-Key' => 'con-only-power-short'])
        ->assertForbidden()->assertJsonPath('message', 'The API token lacks the services:power scope.');
    unset($_ENV['AAPANEL_MANAGED01_API_KEY']);
});

it('never lets a token pay for and take away the archive of a cancelled service', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $backup = tokenScopeArchive($org->id, $service->id);
    app(WalletService::class)->topup($org, Money::decimal('2000', 'CZK'), 'bank', 'seed', CommandContext::system('test'));
    $headers = ['X-Organization' => $org->id];

    // round 1: the download was checked as backup.restore (services:power), so "operate services" paid from the wallet and
    // got a signed link to wp-config.php and .env; backup.download is not available to tokens (D-1, the map)
    foreach (['operate' => ['services:read', 'services:power'], 'all' => TokenScopes::ALL] as $label => $scopes) {
        app('auth')->forgetGuards();
        $plain = tokenScopeBearer($owner, $org, $scopes);
        $response = $this->withToken($plain)->postJson("/v1/services/archives/{$backup->id}/download", [], $headers + ['Idempotency-Key' => "tok-dl-{$label}"]);
        expect($response->status())->toBe(403, "{$label}: {$response->status()} {$response->json('message')}")
            ->and($response->json('message'))->toBe('This action is not available to API tokens; use the portal.')
            ->and($response->json('data.url'))->toBeNull();
    }
    expect(app(WalletService::class)->balances($org, 'CZK')['posted']->minor)->toBe(200000)
        ->and(data_get(Backup::query()->findOrFail($backup->id)->meta, 'download.paid'))->toBeNull();

    // the portal's own session still pays and gets the link
    app('auth')->forgetGuards();
    $this->flushHeaders();
    $this->actingAs($owner->fresh(), 'sanctum')->postJson("/v1/services/archives/{$backup->id}/download", [], $headers + ['Idempotency-Key' => 'portal-dl'])->assertOk()->assertJsonPath('data.charged', true);
});

it('decides a console token on the service\'s own project, never on the one the payload names', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = tokenScopeVps($org);
    $theirs = Project::query()->create(['organization_id' => $org->id, 'name' => 'Cizí', 'slug' => 'cizi-scope', 'tags' => []]);
    $developer = $this->customer(['email' => 'scope-project@example.cz']);
    tokenScopeBind($org, $developer, 'developer', null, $theirs->id);

    // a dispatcher that forwarded a caller's project_id would have let a developer of another project into this console
    $command = new IssueConsoleTokenCommand($org->id, 'console:scope', ['service_id' => $service->id, 'project_id' => $theirs->id]);
    expect($command->scope()->projectId)->toBe($service->project_id)
        ->and(app(Authorizer::class)->can($developer, 'service.console', $command->scope()))->toBeFalse();
});

it('shows a deploy listing\'s values only to a token that may manage the service', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $web = featureWebService($org, 'aapanel');
    DeploySource::query()->create(['service_id' => $web->id, 'organization_id' => $org->id, 'provider' => 'github', 'repository' => 'firma/shop', 'branch' => 'main', 'clone_url' => 'git@github.com:firma/shop.git', 'env' => ['APP_ENV' => 'production', 'STRIPE_KEY' => 'sk_live_example']]);
    $headers = ['X-Organization' => $org->id];

    // ServiceController::resources passes `secrets` = ApiContext::can(service.manage): a read-only token of an owner reads the masked view
    $read = tokenScopeBearer($owner, $org, ['services:read']);
    $masked = $this->withToken($read)->getJson("/v1/services/{$web->id}/resources/deploy", $headers)->assertOk()->json('data.source');
    expect($masked['env'])->toBe(['APP_ENV' => null, 'STRIPE_KEY' => null]);

    app('auth')->forgetGuards();
    $power = tokenScopeBearer($owner, $org, ['services:read', 'services:power']);
    $full = $this->withToken($power)->getJson("/v1/services/{$web->id}/resources/deploy?fresh=1", $headers)->assertOk()->json('data.source');
    expect($full['env'])->toBe(['APP_ENV' => 'production', 'STRIPE_KEY' => 'sk_live_example']);
    unset($_ENV['AAPANEL_MANAGED01_API_KEY']);
});

it('refuses a HIGH action through a token even when the person has stepped up', function () {
    tokenScopePveFakes();
    [$owner, $org] = $this->customerWithOrganization();
    $service = tokenScopeVps($org);
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1'); // a grant without a session — tests make them, production does not

    $plain = tokenScopeBearer($owner, $org, ['services:read', 'services:power']);
    $this->withToken($plain)->postJson("/v1/services/{$service->id}/terminate", [], ['X-Organization' => $org->id, 'Idempotency-Key' => 'tok-terminate'])
        ->assertForbidden()->assertJsonPath('error', 'step_up_required');
    expect($service->fresh()->terminate_at)->toBeNull();

    // round 1: an Origin of a stateful domain made Sanctum start a session for the bearer request, the session id then
    // replaced `token:<id>` and the session-less grant matched — a token is a token whatever headers it sends
    app('auth')->forgetGuards();
    $this->withToken($plain)->postJson("/v1/services/{$service->id}/terminate", [], ['X-Organization' => $org->id, 'Idempotency-Key' => 'tok-terminate-origin', 'Origin' => 'http://localhost', 'Referer' => 'http://localhost/'])
        ->assertForbidden()->assertJsonPath('error', 'step_up_required');
    expect($service->fresh()->terminate_at)->toBeNull();

    // the same person in the portal, with the same grant, may
    app('auth')->forgetGuards();
    $this->flushHeaders();
    $this->actingAs($owner->fresh(), 'sanctum')->postJson("/v1/services/{$service->id}/terminate", [], ['X-Organization' => $org->id, 'Idempotency-Key' => 'portal-terminate'])->assertStatus(202);
});

it('gives a console-service guest a console token and a manage-service guest none', function () {
    tokenScopePveFakes();
    [$owner, $org] = $this->customerWithOrganization();
    $service = tokenScopeVps($org);
    expect($service->project_id)->not->toBeNull();

    $cases = [
        'svc_console guest' => [fn (User $u) => tokenScopeBind($org, $u, 'svc_console', $service->id), 200],
        'svc_manage guest' => [fn (User $u) => tokenScopeBind($org, $u, 'svc_manage', $service->id), 403],
        'organization developer' => [fn (User $u) => tokenScopeBind($org, $u, 'developer'), 200],
        'project developer' => [fn (User $u) => tokenScopeBind($org, $u, 'developer', null, $service->project_id), 200],
        'viewer' => [fn (User $u) => tokenScopeBind($org, $u, 'viewer'), 403],
    ];
    $i = 0;
    foreach ($cases as $who => [$bind, $status]) {
        $person = $this->customer(['email' => 'scope-'.(++$i).'@example.cz']);
        $bind($person);
        app('auth')->forgetGuards();
        $response = $this->actingAs($person, 'sanctum')->postJson("/v1/services/{$service->id}/console-token", [], ['Idempotency-Key' => "guest-console-{$i}"]);
        expect($response->status())->toBe($status, "{$who} answered {$response->status()}: {$response->json('message')}");
    }
});

it('does not show a read-only token what only managing shows', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $web = featureWebService($org, 'aapanel');
    DeploySource::query()->create(['service_id' => $web->id, 'organization_id' => $org->id, 'provider' => 'github', 'repository' => 'firma/shop', 'branch' => 'main', 'clone_url' => 'git@github.com:firma/shop.git', 'env' => ['APP_ENV' => 'production', 'STRIPE_KEY' => 'sk_live_example']]);
    $scope = CommandScope::resource($web->id, $org->id, $web->project_id);

    expect(app(ApiContext::class)->can(tokenScopeRequest($owner, $owner->createToken('r', ['services:read'])->accessToken), 'service.manage', $scope))->toBeFalse()
        ->and(app(ApiContext::class)->can(tokenScopeRequest($owner->fresh(), $owner->createToken('p', ['services:read', 'services:power'])->accessToken), 'service.manage', $scope))->toBeTrue()
        ->and(app(ApiContext::class)->can(tokenScopeRequest($owner->fresh(), null), 'service.manage', $scope))->toBeTrue();

    $plain = tokenScopeBearer($owner, $org, ['services:read']);
    $source = $this->withToken($plain)->getJson("/v1/services/{$web->id}/deploy", ['X-Organization' => $org->id])->assertOk()->json('data.source');
    expect($source['env'])->toBe(['APP_ENV' => null, 'STRIPE_KEY' => null])->and($source['env_hidden'])->toBeTrue();
    unset($_ENV['AAPANEL_MANAGED01_API_KEY']);
});

it('keeps a power-only token from creating a console schedule through spec apply', function () {
    // PUT services/{id}/spec is checked as service.manage (services:power) and chains per-action commands through the bus,
    // which never sees token scopes. TASK-0029 asks each step its own permission (a schedule with a `command` task is the
    // console) and the spec asks the token's scope for it; enabled at the 0029–0031 integration (was ->skip() until then)
    [$owner, $org] = $this->customerWithOrganization();
    $game = featureGameService($org);
    $sent = [];
    Http::fake(function (HttpClientRequest $request) use (&$sent) {
        if ($request->method() !== 'GET') {
            $sent[] = $request->method().' '.$request->url();
        }

        return Http::response(['object' => 'list', 'data' => [], 'meta' => ['pagination' => ['total_pages' => 1]]]);
    });
    $operations = Operation::query()->count();
    $spec = ['schedules' => [['name' => 'konzole', 'cron' => '0 3 * * *', 'actions' => [['action' => 'command', 'payload' => 'op attacker']]]]];

    $plain = tokenScopeBearer($owner, $org, ['services:read', 'services:power']);
    $result = $this->withToken($plain)->putJson("/v1/services/{$game->id}/spec", ['spec' => $spec], ['X-Organization' => $org->id, 'Idempotency-Key' => 'tok-spec-console'])->assertOk()->json();

    expect($result['skipped'])->toBe([['section' => 'schedules', 'reason' => 'token_scope:schedule.create']])
        ->and($result['operations'])->toBe([])
        ->and(Operation::query()->count())->toBe($operations)
        ->and($sent)->toBe([]); // nothing reached the panel
});

it('lets a console token schedule a console command through spec apply, and refuses a token session whose token is gone', function () {
    // the spec asks the token the same map as /actions (TokenScopes): TASK-0029's first guard refused every console step on a
    // token session, also for a token its owner gave `services:console` on purpose — the scope exists for exactly this
    [$owner, $org] = $this->customerWithOrganization();
    $game = featureGameService($org);
    Http::fake(fn () => Http::response(['object' => 'list', 'data' => [], 'meta' => ['pagination' => ['total_pages' => 1]]]));
    $spec = ['schedules' => [['name' => 'konzole', 'cron' => '0 3 * * *', 'actions' => [['action' => 'command', 'payload' => 'save-all']]]]];

    $plain = tokenScopeBearer($owner, $org, ['services:read', 'services:power', 'services:console']);
    $result = $this->withToken($plain)->putJson("/v1/services/{$game->id}/spec", ['spec' => $spec], ['X-Organization' => $org->id, 'Idempotency-Key' => 'tok-spec-console-ok'])->assertOk()->json();
    expect($result['skipped'])->toBe([])
        ->and(array_column($result['operations'], 'action'))->toBe(['schedule.create'])
        ->and(Operation::query()->findOrFail($result['operations'][0]['operation_id'])->authorized_permission)->toBe('service.console');

    // fail closed: a `token:` session whose token no longer exists takes nothing, not even what managing takes
    $gone = new CommandContext('user', $owner->id, $org->id, null, '127.0.0.1', 'pest', 'token:does-not-exist');
    $backupOnly = ['schedules' => [['name' => 'zaloha', 'cron' => '0 4 * * *', 'actions' => [['action' => 'backup', 'payload' => '']]]]];
    $refused = app(ServiceSpecService::class)->apply($game->fresh(), $backupOnly, $gone, 'tok-spec-gone');
    expect($refused['skipped'])->toBe([['section' => 'schedules', 'reason' => 'token_scope:schedule.create']])
        ->and($refused['operations'])->toBe([]);
});

it('decides a console schedule on /actions by what it schedules, at the route as at the dispatch', function () {
    // TASK-0030 LOW, closed at the stack polish (TASK-0027): TokenRouteScope and ServiceController::action asked
    // permissionFor($action) without the params, so the route layer saw a schedule with a `command` task as service.manage.
    // Red: a token holding only services:console was refused the console schedule it exists for ("lacks the services:power
    // scope"), and the route let a power-only token through to the dispatch, which alone refused it
    [$owner, $org] = $this->customerWithOrganization();
    $game = featureGameService($org);
    Http::fake(fn () => Http::response(['object' => 'list', 'data' => [], 'meta' => ['pagination' => ['total_pages' => 1]]]));
    $headers = ['X-Organization' => $org->id];
    $console = ['action' => 'schedule.create', 'params' => ['name' => 'konzole', 'cron' => '0 3 * * *', 'actions' => [['action' => 'command', 'payload' => 'save-all']]]];
    $backup = ['action' => 'schedule.create', 'params' => ['name' => 'zaloha', 'cron' => '0 4 * * *', 'actions' => [['action' => 'backup', 'payload' => '']]]];

    // (1) the route layer on its own — its defence in depth no longer rests on the dispatch
    $route = function (array $scopes, array $body) use ($owner, $game): string {
        $request = Request::create("/v1/services/{$game->id}/actions", 'POST', $body);
        $user = $owner->fresh();
        $user->withAccessToken($user->createToken('route', $scopes)->accessToken);
        $request->setUserResolver(fn () => $user);
        try {
            app(TokenRouteScope::class)->handle($request, fn () => response('passed'));

            return 'passed';
        } catch (DomainError $e) {
            return $e->getMessage();
        }
    };
    expect($route(['services:read', 'services:power'], $console))->toBe('The API token lacks the services:console scope.')
        ->and($route(['services:read'], $console))->toBe('The API token lacks the services:console scope.')
        ->and($route(['services:console'], $console))->toBe('passed')
        ->and($route(['services:read', 'services:power'], $backup))->toBe('passed') // a power or backup task stays managing
        ->and($route(['services:console'], $backup))->toBe('The API token lacks the services:power scope.');

    // (2) through HTTP: read-only and power-only tokens are refused the console schedule, nothing is queued
    foreach (['read' => ['services:read'], 'power' => ['services:read', 'services:power']] as $label => $scopes) {
        app('auth')->forgetGuards();
        $response = $this->withToken(tokenScopeBearer($owner, $org, $scopes))->postJson("/v1/services/{$game->id}/actions", $console, $headers + ['Idempotency-Key' => "sched-{$label}"]);
        expect($response->status())->toBe(403, "{$label}: {$response->status()} {$response->json('message')}")
            ->and($response->json('message'))->toBe('The API token lacks the services:console scope.');
    }
    expect(Operation::query()->where('service_id', $game->id)->exists())->toBeFalse();

    // (3) a token holding only services:console gets past every scope check and the schedule is queued as a console action
    app('auth')->forgetGuards();
    $only = $this->withToken(tokenScopeBearer($owner, $org, ['services:console']))->postJson("/v1/services/{$game->id}/actions", $console, $headers + ['Idempotency-Key' => 'sched-console-only']);
    expect($only->status())->toBe(202, "console-only: {$only->status()} {$only->json('message')}")
        ->and(Operation::query()->where('service_id', $game->id)->sole()->authorized_permission)->toBe('service.console');
});

it('decides a token scope for every permission the service action map can ask', function () {
    // the seam of TASK-0029 (one permission per action) and TASK-0030 (one scope per permission), pinned at their integration:
    // a permission the action map starts to return without a decision here would be silently closed to every token
    $asked = array_values(array_unique(array_merge(
        array_values(ServiceActionCommand::PERMISSIONS),
        [ServiceActionCommand::permissionFor('schedule.create', ['actions' => [['action' => 'command', 'payload' => 'x']]]), ServiceActionCommand::permissionFor('gbackup.lock', ['locked' => false])],
    )));
    $scopes = array_combine($asked, array_map(fn (string $permission) => TokenScopes::for($permission), $asked));
    ksort($scopes);

    expect($scopes)->toBe([
        'backup.delete' => TokenScopes::SERVICES_POWER, // HIGH with a fresh step-up: a token never holds one
        'backup.policy.manage' => null, // the operator's mailbox retention
        'backup.restore' => TokenScopes::SERVICES_POWER, // HIGH with a fresh step-up
        'compute.vm.delete' => TokenScopes::SERVICES_POWER,
        'game.manage' => TokenScopes::SERVICES_POWER,
        'service.console' => TokenScopes::SERVICES_CONSOLE,
        'service.delete' => TokenScopes::SERVICES_POWER, // HIGH with a fresh step-up
        'service.manage' => TokenScopes::SERVICES_POWER,
        'service.panel_account.manage' => null, // owner only, portal only
    ]);
});

it('refuses a restore through a token even when the person has stepped up — the archive and the service alike', function () {
    tokenScopePveFakes();
    [$owner, $org] = $this->customerWithOrganization();
    $cancelled = featureWebService($org, 'ispconfig');
    $backup = tokenScopeArchive($org->id, $cancelled->id);
    $target = tokenScopeVps($org);
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1'); // a grant without a session — tests make them, production does not
    $plain = tokenScopeBearer($owner, $org, ['services:read', 'services:power']);
    $headers = ['X-Organization' => $org->id];
    $operations = Operation::query()->count();

    // review round 2: TokenScopes gives backup.restore to services:power and relies on the step-up rule for the rest — both
    // ways a restore is asked for through a token have to meet it, not only terminate
    // (1) the archive of a cancelled service onto a live one (ServiceArchiveCommand op=restore, HIGH)
    $this->withToken($plain)->postJson("/v1/services/archives/{$backup->id}/restore", ['service_id' => $target->id], $headers + ['Idempotency-Key' => 'tok-archive-restore'])
        ->assertForbidden()->assertJsonPath('error', 'step_up_required');
    // (2) a restore of the service itself (ServiceActionCommand restore → backup.restore, HIGH), short route and generic endpoint
    app('auth')->forgetGuards();
    $this->withToken($plain)->postJson("/v1/services/{$target->id}/restore", ['params' => ['backup_id' => $backup->id]], $headers + ['Idempotency-Key' => 'tok-restore'])
        ->assertForbidden()->assertJsonPath('error', 'step_up_required');
    app('auth')->forgetGuards();
    $this->withToken($plain)->postJson("/v1/services/{$target->id}/actions", ['action' => 'rollback_snapshot', 'params' => ['snapshot' => 'before-update']], $headers + ['Idempotency-Key' => 'tok-rollback'])
        ->assertForbidden()->assertJsonPath('error', 'step_up_required');

    expect(Operation::query()->count())->toBe($operations)
        ->and(Backup::query()->findOrFail($backup->id)->state)->toBe('completed')
        ->and(data_get(Backup::query()->findOrFail($backup->id)->meta, 'restore'))->toBeNull();

    // the grant is real: the same person in the portal gets past the step-up (whatever the restore itself answers then)
    app('auth')->forgetGuards();
    $this->flushHeaders();
    $portal = $this->actingAs($owner->fresh(), 'sanctum')->postJson("/v1/services/{$target->id}/restore", ['params' => ['backup_id' => $backup->id]], $headers + ['Idempotency-Key' => 'portal-restore']);
    expect($portal->json('error'))->not->toBe('step_up_required', "portal: {$portal->status()} {$portal->json('message')}");
    unset($_ENV['ISPCONFIG_SHARED01_REMOTE_USER'], $_ENV['ISPCONFIG_SHARED01_REMOTE_PASSWORD']);
});

it('refuses deleting a backup through a token even when the person has stepped up', function () {
    // TokenScopes gives backup.delete to services:power and relies on the step-up rule. Without TASK-0029 the rule did not
    // reach it: the `backup.delete` action was decided as service.manage at NORMAL risk (the default arm of permissionFor), so a
    // power token deleted a backup — red, 202 (review round 2). TASK-0029 maps the action to backup.delete with a step-up
    // (DestructivePreview::ACTIONS); enabled at the 0029–0031 integration (was ->skip() until then).
    [$owner, $org] = $this->customerWithOrganization();
    $web = featureWebService($org, 'aapanel');
    $backup = Backup::query()->create([
        'service_id' => $web->id, 'organization_id' => $org->id, 'kind' => 'site', 'state' => 'completed', 'protected' => false, 'remote_id' => 'bk-scope-1',
        'started_at' => now()->subDay(), 'finished_at' => now()->subDay(), 'size_bytes' => 1024, 'meta' => [],
    ]);
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $plain = tokenScopeBearer($owner, $org, ['services:read', 'services:power']);

    $response = $this->withToken($plain)->postJson("/v1/services/{$web->id}/actions", ['action' => 'backup.delete', 'params' => ['remote_id' => $backup->id]], ['X-Organization' => $org->id, 'Idempotency-Key' => 'tok-backup-delete']);
    expect($response->status())->toBe(403, "backup.delete: {$response->status()} {$response->json('message')}")
        ->and($response->json('error'))->toBe('step_up_required')
        ->and(Backup::query()->findOrFail($backup->id)->state)->toBe('completed')
        ->and(Operation::query()->where('service_id', $web->id)->exists())->toBeFalse();
    unset($_ENV['AAPANEL_MANAGED01_API_KEY']);
});

it('lets an unknown action word reach the validator, not the scope map', function () {
    // TokenRouteScope asks the map only about a word in ServiceActionWorkflow::ACTIONS; anything else keeps the family's write
    // scope and meets the controller's `in:` validator. Written down so the reliance on that order is a test, not a comment.
    [$owner, $org] = $this->customerWithOrganization();
    $service = tokenScopeVps($org);
    $headers = ['X-Organization' => $org->id];

    $plain = tokenScopeBearer($owner, $org, ['services:read', 'services:power']);
    foreach (['made.up' => ['action' => 'made.up'], 'not a string' => ['action' => ['command.run']], 'missing' => []] as $label => $body) {
        app('auth')->forgetGuards();
        $response = $this->withToken($plain)->postJson("/v1/services/{$service->id}/actions", $body + ['params' => []], $headers + ['Idempotency-Key' => 'unknown-'.md5($label)]);
        expect($response->status())->toBe(422, "{$label}: {$response->status()} {$response->json('message')}");
    }

    // without the write scope the route answers first, as for any write to the family
    app('auth')->forgetGuards();
    $read = tokenScopeBearer($owner, $org, ['services:read']);
    $this->withToken($read)->postJson("/v1/services/{$service->id}/actions", ['action' => 'made.up'], $headers + ['Idempotency-Key' => 'unknown-read'])
        ->assertForbidden()->assertJsonPath('message', 'The API token lacks the services:power scope.');
    expect(Operation::query()->where('service_id', $service->id)->exists())->toBeFalse();
});

it('gives a svc_console guest with a services:console token the console, and a svc_manage guest with the same scope none', function () {
    // the two checks on the console token — the person (Authorizer, resource scope) and the token (TokenScopes) — composed for
    // the role the fix is named after, through a bearer token and not only a cookie session (review round 2)
    tokenScopePveFakes();
    [$owner, $org] = $this->customerWithOrganization();
    $service = tokenScopeVps($org);

    $guest = $this->customer(['email' => 'guest-console@example.cz']);
    tokenScopeBind($org, $guest, 'svc_console', $service->id);
    $plain = tokenScopeBearer($guest, $org, ['services:console']);
    $console = $this->withToken($plain)->getJson("/v1/services/{$service->id}/console-token", ['X-Organization' => $org->id]);
    expect($console->status())->toBe(200, "svc_console: {$console->status()} {$console->json('message')}")
        ->and($console->json('token'))->toStartWith('con_');

    // the token carries the scope, the person lacks the permission: the person's answer
    app('auth')->forgetGuards();
    $manager = $this->customer(['email' => 'guest-manage@example.cz']);
    tokenScopeBind($org, $manager, 'svc_manage', $service->id);
    $plain2 = tokenScopeBearer($manager, $org, ['services:console']);
    $refused = $this->withToken($plain2)->getJson("/v1/services/{$service->id}/console-token", ['X-Organization' => $org->id]);
    expect($refused->status())->toBe(403, "svc_manage: {$refused->status()} {$refused->json('message')}")
        ->and($refused->json('message'))->not->toBe('The API token lacks the services:console scope.');
    Http::assertSentCount(1); // one vncproxy call: the guest's; the manager never reached Proxmox
});
