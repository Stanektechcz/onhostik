<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
use Tests\TestCase;

/*
 * TASK-0029 (audit C13-H1, C13-H1b, C13-H1c): who may run the dangerous service actions once every action asks for its own
 * permission. Owner and organization admin keep everything; the cloud operator keeps VM snapshot deletion, the game operator
 * game backup deletion; a developer, a `svc_manage` or `svc_console` guest and a power token lose backup deletion; a
 * `svc_manage` guest ("without a shell") loses root access, rescue mode, game sub-users and command schedules.
 */

const SARM_ROLES = ['owner', 'org_admin', 'developer', 'cloud_operator', 'game_operator', 'mail_manager', 'svc_manage', 'svc_console', 'svc_backups', 'svc_restore'];

/** A person holding one role in the organization; a `svc_*` capability is a guest with that role on the two services only. */
function sarmMember(TestCase $test, Organization $org, string $role, array $services): User
{
    $user = User::query()->create(['email' => str_replace('_', '-', $role).'@sarm.test', 'name' => $role, 'password' => 'Correct-Horse-Battery-9', 'state' => 'active']);
    $guest = str_starts_with($role, 'svc_');
    OrganizationMembership::query()->create(['organization_id' => $org->id, 'user_id' => $user->id, 'state' => 'active', 'role_key' => $guest ? 'guest' : $role, 'joined_at' => now()]);
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => $guest ? 'guest' : $role, 'scope_type' => 'organization', 'scope_id' => $org->id, 'organization_id' => $org->id]);
    foreach ($guest ? $services : [] as $service) { // the shape ServiceAccessService::bind writes
        PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => $role, 'scope_type' => 'resource', 'scope_id' => $service->id, 'organization_id' => $org->id]);
    }

    return $user;
}

/** Whether the person may run the action on the service, as the bus asks it. */
function sarmCan(User $user, Service $service, string $action, array $params = []): bool
{
    return app(Authorizer::class)->can($user, ServiceActionCommand::permissionFor($action, $params), CommandScope::resource($service->id, $service->organization_id, $service->project_id));
}

/** The game panel answers every call with an empty success; a deleted backup is remembered. */
function sarmGamePanel(array &$deleted): void
{
    Http::preventStrayRequests();
    Http::fake(function (Request $request) use (&$deleted) {
        if (! str_starts_with($request->url(), PTERO)) {
            return null;
        }
        if ($request->method() === 'DELETE' && preg_match('~/backups/([^/]+)$~', (string) parse_url($request->url(), PHP_URL_PATH), $m) === 1) {
            $deleted[] = $m[1];

            return Http::response('', 204);
        }

        return Http::response(['object' => 'list', 'data' => [], 'meta' => ['pagination' => ['total_pages' => 1]]]);
    });
}

function sarmPost(TestCase $test, Service $service, string $action, array $params = [])
{
    return $test->withHeader('Idempotency-Key', (string) Str::ulid())->postJson("/v1/services/{$service->id}/actions", ['action' => $action, 'params' => $params]);
}

function sarmBackup(Service $service, string $remoteId, array $overrides = []): Backup
{
    return Backup::query()->create(array_merge(['service_id' => $service->id, 'organization_id' => $service->organization_id, 'kind' => 'manual', 'state' => 'completed', 'protected' => false, 'remote_id' => $remoteId, 'size_bytes' => 1024, 'started_at' => now()->subDay(), 'finished_at' => now()->subDay()], $overrides));
}

it('lets each role run exactly the dangerous actions its permissions promise', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $web = featureWebService($org, 'aapanel');
    $game = featureGameService($org);
    $people = ['owner' => $owner];
    foreach (array_slice(SARM_ROLES, 1) as $role) {
        $people[$role] = sarmMember($this, $org, $role, [$web, $game]);
    }
    $console = ['owner', 'org_admin', 'developer', 'cloud_operator', 'game_operator', 'svc_console'];
    $command = ['actions' => [['action' => 'command', 'payload' => 'op me']]];
    $power = ['actions' => [['action' => 'power', 'payload' => 'restart']]];
    $restorers = ['owner', 'org_admin', 'cloud_operator', 'game_operator', 'svc_restore'];
    $matrix = [
        ['backup.delete', $web, [], ['owner', 'org_admin']],
        ['gbackup.delete', $game, [], ['owner', 'org_admin', 'game_operator']],
        ['snapshot.delete', $web, [], ['owner', 'org_admin', 'cloud_operator']],
        ['access.reset', $web, [], $console],
        ['rescue.start', $web, [], $console],
        ['subuser.create', $game, [], $console],
        ['schedule.create', $game, $command, $console],
        ['command.run', $web, [], $console],
        ['schedule.create', $game, $power, [...$console, 'svc_manage']],
        ['database.delete', $web, [], [...$console, 'svc_manage']],
        ['php.set', $web, [], [...$console, 'svc_manage']],
        ['archive.restore', $web, [], $restorers],
        ['restore', $web, [], $restorers],
        ['panel.password', $game, [], ['owner']],
        ['mailbox.backup_retention', $web, [], []],
    ];
    foreach ($matrix as [$action, $service, $params, $allowed]) {
        $actual = array_values(array_filter(SARM_ROLES, fn (string $role) => sarmCan($people[$role], $service, $action, $params)));
        expect($actual)->toBe(array_values(array_intersect(SARM_ROLES, $allowed)), "{$action} ".json_encode($params));
    }
    // mail_manager and svc_backups are in none of these rows at all
    foreach ($matrix as [$action, $service, $params]) {
        expect(sarmCan($people['mail_manager'], $service, $action, $params))->toBeFalse($action)
            ->and(sarmCan($people['svc_backups'], $service, $action, $params))->toBeFalse($action);
    }
});

it('widens two staff roles to exactly what their permissions name', function () {
    [, $org] = $this->customerWithOrganization();
    $web = featureWebService($org, 'aapanel');
    $backups = $this->staff('backup_dr_admin');
    $infrastructure = $this->staff('infrastructure_admin');

    // a real behaviour change, listed in the release notes: the retention action is the operator's permission now, and the
    // backup administrator's `backup.delete` now reaches a customer's web backups (step-up only; four-eyes is a follow-up)
    expect(sarmCan($backups, $web, 'mailbox.backup_retention'))->toBeTrue()
        ->and(sarmCan($infrastructure, $web, 'mailbox.backup_retention'))->toBeTrue()
        ->and(sarmCan($backups, $web, 'backup.delete'))->toBeTrue()
        ->and(sarmCan($infrastructure, $web, 'backup.delete'))->toBeFalse()
        ->and(sarmCan($backups, $web, 'php.set'))->toBeFalse();

    // …but not over the customer API: /v1/services/{id} first asks `service.read` of the service, which neither role holds
    Queue::fake();
    foreach ([[$backups, 'backup.delete', ['remote_id' => 'bk-x']], [$infrastructure, 'mailbox.backup_retention', []]] as [$staff, $action, $params]) {
        app(StepUpService::class)->grant($staff, 'totp', null, '127.0.0.1');
        $this->actingAs($staff, 'sanctum');
        expect((string) sarmPost($this, $web, $action, $params)->assertForbidden()->assertJsonPath('error', 'access_not_approved')->json('message'))->toContain('service.read');
    }
    expect(Operation::query()->where('service_id', $web->id)->exists())->toBeFalse();
});

it('does not let a svc_manage guest open a shell, take root or delete backups', function () {
    [, $org] = $this->customerWithOrganization();
    $web = featureWebService($org, 'aapanel');
    $game = featureGameService($org);
    $deleted = [];
    sarmGamePanel($deleted);
    $guest = sarmMember($this, $org, 'svc_manage', [$web, $game]);
    app(StepUpService::class)->grant($guest, 'totp', null, '127.0.0.1'); // a fresh step-up changes nothing about a missing permission
    Queue::fake();
    $this->actingAs($guest, 'sanctum');

    foreach ([
        [$web, 'access.reset', ['ssh_keys' => ['ssh-ed25519 AAAA guest']], 'service.console'],
        [$web, 'rescue.start', [], 'service.console'],
        [$game, 'subuser.create', ['email' => 'friend@elsewhere.test', 'preset' => 'console'], 'service.console'],
        [$game, 'schedule.create', ['name' => 'op', 'cron' => '* * * * *', 'actions' => [['action' => 'command', 'payload' => 'op guest']]], 'service.console'],
        [$web, 'backup.delete', ['remote_id' => 'bk-1'], 'backup.delete'],
        [$game, 'gbackup.delete', ['remote_id' => 'bk-1'], 'game.manage'],
    ] as [$service, $action, $params, $missing]) {
        $response = sarmPost($this, $service, $action, $params)->assertForbidden()->assertJsonPath('error', 'access_not_approved');
        expect((string) $response->json('message'))->toContain($missing);
    }
    expect(Operation::query()->exists())->toBeFalse();

    // a schedule of power and backup tasks is still managing: the bus lets it through (whatever the handler then says)
    $response = sarmPost($this, $game, 'schedule.create', ['name' => 'Restart', 'cron' => '0 4 * * *', 'actions' => [['action' => 'power', 'payload' => 'restart'], ['action' => 'backup', 'payload' => '']]]);
    expect($response->json('error'))->not->toBe('access_not_approved')->not->toBe('step_up_required');
});

it('asks for a fresh step-up before a backup is deleted or an archive restored', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $web = featureWebService($org, 'aapanel');
    $backup = sarmBackup($web, 'bk-old');
    Queue::fake();
    $this->actingAs($owner, 'sanctum');

    sarmPost($this, $web, 'backup.delete', ['remote_id' => 'bk-old'])->assertForbidden()->assertJsonPath('error', 'step_up_required');
    sarmPost($this, $web, 'archive.restore', ['backup_id' => $backup->id])->assertForbidden()->assertJsonPath('error', 'step_up_required'); // C13-H1c
    expect(Operation::query()->exists())->toBeFalse();

    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $id = sarmPost($this, $web, 'backup.delete', ['remote_id' => 'bk-old'])->assertStatus(202)->json('operation_id');
    $operation = Operation::query()->findOrFail($id);
    expect(data_get($operation->desired, 'action'))->toBe('backup.delete')
        ->and($operation->authorized_permission)->toBe('backup.delete'); // the run asks the same permission again before each step (H315)
});

it('re-checks a command schedule as the console while it runs', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $game = featureGameService($org);
    $deleted = [];
    sarmGamePanel($deleted);
    Queue::fake();
    $this->actingAs($owner, 'sanctum');

    $id = sarmPost($this, $game, 'schedule.create', ['name' => 'Oznámení', 'cron' => '0 * * * *', 'actions' => [['action' => 'command', 'payload' => 'say ahoj']]])->assertStatus(202)->json('operation_id');
    expect(Operation::query()->findOrFail($id)->authorized_permission)->toBe('service.console');
});

it('does not delete a protected game backup, when asked or when the run comes', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $game = featureGameService($org);
    $deleted = [];
    sarmGamePanel($deleted);
    sarmBackup($game, 'bk-safe', ['protected' => true]);
    $later = sarmBackup($game, 'bk-later');
    $free = sarmBackup($game, 'bk-free');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $this->actingAs($owner, 'sanctum');
    $realQueue = app('queue');
    Queue::fake();

    sarmPost($this, $game, 'gbackup.delete', ['remote_id' => 'bk-safe'])->assertStatus(409)->assertJsonPath('error', 'backup_protected');
    expect(Operation::query()->exists())->toBeFalse();

    // protected between the request and the run: the workflow asks again and sends nothing to the panel
    $id = sarmPost($this, $game, 'gbackup.delete', ['remote_id' => 'bk-later'])->assertStatus(202)->json('operation_id');
    $later->forceFill(['protected' => true])->save();
    Queue::swap($realQueue);
    $run = driveOperation(Operation::query()->findOrFail($id));
    expect($run->state)->toBe(Operation::FAILED)
        ->and(strtolower(json_encode($run->error)))->toContain('protected')
        ->and($deleted)->toBe([]);

    // an unprotected one goes
    $id = sarmPost($this, $game, 'gbackup.delete', ['remote_id' => 'bk-free'])->assertStatus(202)->json('operation_id');
    expect(driveOperation(Operation::query()->findOrFail($id))->state)->toBe(Operation::SUCCEEDED)
        ->and($deleted)->toBe(['bk-free'])
        ->and($free->fresh()->state)->toBe('deleted');

    // and a platform-protected copy is not unlocked on the panel either
    sarmPost($this, $game, 'gbackup.lock', ['remote_id' => 'bk-safe', 'locked' => false])->assertStatus(409)->assertJsonPath('error', 'backup_protected');
});

it('does not delete a protected VM snapshot, when asked or when the run comes', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $instance = pveLab();
    $node = Node::query()->where('name', 'prg1-n2')->firstOrFail();
    $vps = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'hostname' => 'vm-sarm.cust.onhost.cz', 'state' => ServiceStateMachine::ACTIVE,
        'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id, 'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'],
        'entitlements' => ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160, 'snapshots' => 5], 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => [],
    ]);
    ProviderBinding::query()->create(['service_id' => $vps->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => 'prg1-n2', 'meta' => ['name' => 'vm-sarm'], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "sarm:{$vps->id}", 'adapter_version' => '1.0.0']);
    $deleted = [];
    Http::preventStrayRequests();
    Http::fake(function (Request $request) use (&$deleted) {
        if ($request->method() === 'DELETE') {
            $deleted[] = $request->url();
        }

        return Http::response(['data' => null]);
    });
    sarmBackup($vps, 'onhost-pre-rollback-1', ['kind' => 'pre_rollback', 'protected' => true]); // the platform's own safety snapshot
    $later = sarmBackup($vps, 'weekly', ['kind' => 'manual']);
    $services = app(ServiceService::class);
    $context = $this->contextFor($owner, $org, 'webauthn');

    expect(fn () => $services->requestAction($vps, 'snapshot.delete', $context, 'sarm-snap-1', ['name' => 'onhost-pre-rollback-1']))
        ->toThrow(fn (DomainError $e) => expect($e->error)->toBe('backup_protected'));
    expect(Operation::query()->where('service_id', $vps->id)->exists())->toBeFalse();

    $realQueue = app('queue');
    Queue::fake();
    $operation = $services->requestAction($vps, 'snapshot.delete', $context, 'sarm-snap-2', ['name' => 'weekly']);
    $later->forceFill(['protected' => true])->save();
    Queue::swap($realQueue);
    $run = driveOperation($operation);
    expect($run->state)->toBe(Operation::FAILED)
        ->and(strtolower(json_encode($run->error)))->toContain('protected')
        ->and($deleted)->toBe([]);
});
