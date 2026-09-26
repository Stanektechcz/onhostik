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
use Onhost\Domain\Integrations\ActionHookService;
use Onhost\Domain\Integrations\DiscordService;
use Onhost\Domain\Integrations\Models\ActionHook;
use Onhost\Domain\Integrations\Models\DiscordLink;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\OperationRunner;
use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;
use Onhost\Domain\Services\Access\ServiceAccessService;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\BackupPolicy;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceAccessGrant;
use Onhost\Domain\Services\Models\SshKeyGrant;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\ServiceSpecService;
use Onhost\Domain\Services\Web\BackupDailyKeepers;
use Onhost\Domain\Support\Assistant\ServiceIntent;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;
use Tests\TestCase;

/*
 * TASK-0029 WP2 (D29.7, audit C13): the paths that start a service action on a customer's word without the service action
 * command itself — a declarative spec apply, an action hook, a Discord button — ask the same permission map as the bus.
 * Each used to ask `service.manage` by itself, so the one role that exists to hand over the day-to-day work without a shell
 * could still schedule a console command through a spec, and a hook or a button could run what the panel guards with a
 * fresh step-up. The delegated-access cleanup follows the same line: SSH keys and game sub-users are the console's.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Queue::fake(); // what the actions do on a panel is not the subject here
});

/** A person holding one role in the organization; a `svc_*` capability is a guest with that role on the given services only. */
function sasgMember(Organization $org, string $role, array $services, string $email): User
{
    $user = User::query()->create(['email' => $email, 'name' => $role, 'password' => 'Correct-Horse-Battery-9', 'state' => 'active']);
    $guest = str_starts_with($role, 'svc_');
    OrganizationMembership::query()->create(['organization_id' => $org->id, 'user_id' => $user->id, 'state' => 'active', 'role_key' => $guest ? 'guest' : $role, 'joined_at' => now()]);
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => $guest ? 'guest' : $role, 'scope_type' => 'organization', 'scope_id' => $org->id, 'organization_id' => $org->id]);
    foreach ($guest ? $services : [] as $service) { // the shape ServiceAccessService::bind writes
        PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => $role, 'scope_type' => 'resource', 'scope_id' => $service->id, 'organization_id' => $org->id]);
    }

    return $user;
}

/** Every panel answers with an empty success: the spec reads no schedules, a backup list is empty. */
function sasgPanels(): void
{
    Http::fake(function (Request $request) {
        if (str_starts_with($request->url(), PTERO)) {
            return Http::response(['object' => 'list', 'data' => [], 'meta' => ['pagination' => ['total_pages' => 1]]]);
        }

        return Http::response(['status' => true, 'msg' => 'ok', 'data' => []]);
    });
}

/** The two jobs of the spec: a console command and a backup. */
function sasgSchedules(): array
{
    return ['schedules' => [
        ['name' => 'x', 'cron' => '0 3 * * *', 'actions' => [['action' => 'command', 'payload' => 'say hi']]],
        ['name' => 'y', 'cron' => '0 4 * * *', 'actions' => [['action' => 'backup', 'payload' => '']]],
    ]];
}

/** The operation a spec apply started for one job. */
function sasgOperation(array $result, int $index): Operation
{
    return Operation::query()->findOrFail($result['operations'][$index]['operation_id']);
}

/** A hook as an older release stored it, with a token the test knows. @return array{0:ActionHook,1:string} */
function sasgLegacyHook(Service $service, User $creator, string $action, array $params = []): array
{
    $token = 'ahk_'.Str::random(40);
    $hook = ActionHook::query()->create(['organization_id' => $service->organization_id, 'service_id' => $service->id, 'created_by' => $creator->id, 'name' => $action, 'action' => $action, 'params' => $params, 'token_hash' => hash('sha256', $token), 'enabled' => true]);

    return [$hook, $token];
}

/** Discord's signature over a body (copied here under our own name: the file order of a filtered run is not stable). */
function sasgDiscordSign(string $secretKey, string $body): array
{
    $timestamp = (string) time();

    return ['HTTP_X_SIGNATURE_ED25519' => bin2hex(sodium_crypto_sign_detached($timestamp.$body, $secretKey)), 'HTTP_X_SIGNATURE_TIMESTAMP' => $timestamp, 'CONTENT_TYPE' => 'application/json'];
}

/** A button click on a proposal the bot cached for the linked account (the assistant's button, a /onhost confirmation). */
function sasgDiscordClick(TestCase $test, string $secret, DiscordLink $link, Service $service, string $action, array $params = []): array
{
    $id = Str::lower(Str::random(16));
    cache()->put('discord:proposal:'.$id, ['service_id' => $service->id, 'action' => $action, 'params' => $params, 'label' => $action, 'link_id' => $link->id], 900);
    $body = json_encode(['type' => 3, 'member' => ['user' => ['id' => (string) $link->discord_user_id, 'username' => 'jana']], 'data' => ['custom_id' => 'act:'.$id]]);

    return $test->call('POST', '/v1/integrations/discord/interactions', [], [], [], sasgDiscordSign($secret, $body), $body)->assertOk()->json();
}

it('does not let a spec apply schedule a console command for somebody who only manages', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $game = featureGameService($org);
    sasgPanels();
    $guest = sasgMember($org, 'svc_manage', [$game], 'agentura@sasg.test');
    $specs = app(ServiceSpecService::class);

    // the spec command itself asked `service.manage` once; the command job asks the console, the backup job stays managing
    $result = $specs->apply($game, sasgSchedules(), $this->contextFor($guest, $org), 'spec-1');
    expect($result['skipped'])->toBe([['section' => 'schedules', 'reason' => 'forbidden:schedule.create']])
        ->and(array_column($result['operations'], 'action'))->toBe(['schedule.create'])
        ->and(data_get(sasgOperation($result, 0)->desired, 'name'))->toBe('y')
        ->and(sasgOperation($result, 0)->authorized_permission)->toBe('service.manage');

    // the owner holds the console: both jobs go, and the run of the command job asks the console again (H315)
    $result = $specs->apply($game, sasgSchedules(), $this->contextFor($owner, $org, 'totp'), 'spec-2');
    expect($result['skipped'])->toBe([])
        ->and(array_column($result['operations'], 'action'))->toBe(['schedule.create', 'schedule.create'])
        ->and(sasgOperation($result, 0)->authorized_permission)->toBe('service.console')
        ->and(sasgOperation($result, 1)->authorized_permission)->toBe('service.manage');

    // each step's audit row says who, under which session grant — as the /actions path records it (no spec step needs a fresh
    // step-up of its own, ServiceActionPermissionMapTest; review round 1, LOW)
    $rows = AuditEvent::query()->where('action', 'service.action.schedule.create')->where('result', 'succeeded')->get();
    expect($rows->where('actor_id', $owner->id)->pluck('step_up_method')->all())->toBe(['totp', 'totp'])
        ->and($rows->where('actor_id', $guest->id)->pluck('step_up_method')->all())->toBe([null]);
});

it('does not offer pushing staging to production from a chat sentence', function () {
    // decision (1) of TASK-0029 retired the staging.push proposal (AssistantProposals, hooks, Discord); the plain-language intent
    // still offered it as a button that the panel then refuses without a fresh step-up (review round 1, LOW)
    [$owner, $org] = $this->customerWithOrganization();
    $web = featureWebService($org, 'aapanel');
    $web->update(['entitlements' => array_replace((array) $web->entitlements, ['staging' => true])]);
    $features = app(ServiceFeatures::class);
    expect($features->actions($web->refresh()))->toContain('staging.push', 'staging.refresh'); // the plan has staging: not vacuous

    $offered = fn (string $text) => array_column(ServiceIntent::detect($text, $org, $features), 'action');
    expect($offered('obnov staging na shop.cz'))->toBe(['staging.refresh'])
        ->and($offered('přenes staging do produkce na shop.cz'))->not->toContain('staging.push')
        ->and($offered('push staging to production shop.cz'))->not->toContain('staging.push');
});

it('does not let a power-only API token schedule a console command through the spec', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $game = featureGameService($org);
    sasgPanels();
    $token = $owner->createToken('ci', ['services:read', 'services:power'])->plainTextToken;

    // PUT /spec is checked as `service.manage`, which the power scope covers; the console command inside is not the token's
    $result = $this->withToken($token)->putJson("/v1/services/{$game->id}/spec", ['spec' => sasgSchedules()], ['X-Organization' => $org->id, 'Idempotency-Key' => 'sasg-tok-1'])->assertOk()->json();
    expect($result['skipped'])->toBe([['section' => 'schedules', 'reason' => 'token_scope:schedule.create']])
        ->and(array_column($result['operations'], 'action'))->toBe(['schedule.create'])
        ->and(data_get(sasgOperation($result, 0)->desired, 'name'))->toBe('y');
});

it('neither makes nor runs a hook for an action that needs a fresh step-up', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $web = featureWebService($org, 'aapanel');
    sasgPanels();
    $this->actingAs($owner, 'sanctum');

    // a hook cannot give a step-up: pushing a staging copy over the live site is not something a URL can do
    $this->postJson('/v1/hooks/actions', ['service_id' => $web->id, 'name' => 'Push', 'action' => 'staging.push'], ['Idempotency-Key' => 'sasg-hook-1'])
        ->assertStatus(422)->assertJsonPath('error', 'action_param_invalid');

    // …and one stored by an older release stops with the reason, stays enabled and starts nothing
    [$legacy, $token] = sasgLegacyHook($web, $owner, 'staging.push');
    $run = $this->post('/v1/hooks/run/'.$token)->assertOk()->json();
    expect($run['accepted'])->toBeFalse()->and($run['reason'])->toBe('step_up_required')
        ->and($legacy->fresh()->last_result)->toBe('step_up_required')->and($legacy->fresh()->enabled)->toBeTrue();

    // an action no map names is not run either
    [$dead, $token] = sasgLegacyHook($web, $owner, 'monitoring.set');
    $run = $this->post('/v1/hooks/run/'.$token)->assertOk()->json();
    expect($run['accepted'])->toBeFalse()->and($run['reason'])->toBe('service_action_unknown')
        ->and($dead->fresh()->last_result)->toBe('service_action_unknown');
    expect(Operation::query()->where('service_id', $web->id)->exists())->toBeFalse();
});

it('asks for the permission of the hook\'s own action when it runs', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $web = featureWebService($org, 'aapanel');
    sasgPanels();
    $developer = sasgMember($org, 'developer', [], 'dev@sasg.test');
    $this->actingAs($developer, 'sanctum');
    $created = $this->postJson('/v1/hooks/actions', ['service_id' => $web->id, 'name' => 'Záloha', 'action' => 'backup', 'params' => ['kind' => 'manual']], ['X-Organization' => $org->id, 'Idempotency-Key' => 'sasg-hook-2'])->assertCreated()->json();

    // the developer's role ends: the hook they made runs as them, and they may not back up any more
    PolicyBinding::query()->where('principal_id', $developer->id)->delete();
    app(Authorizer::class)->forget($developer);
    $run = $this->post('/v1/hooks/run/'.$created['token'])->assertOk()->json();
    expect($run['accepted'])->toBeFalse()->and($run['reason'])->toBe('forbidden')
        ->and(Operation::query()->where('service_id', $web->id)->exists())->toBeFalse();

    // a hook the owner makes for the same action runs, and its run asks the same permission again (H315)
    $this->actingAs($owner, 'sanctum');
    $mine = $this->postJson('/v1/hooks/actions', ['service_id' => $web->id, 'name' => 'Záloha', 'action' => 'backup', 'params' => ['kind' => 'manual']], ['Idempotency-Key' => 'sasg-hook-3'])->assertCreated()->json();
    $run = $this->post('/v1/hooks/run/'.$mine['token'])->assertAccepted()->json();
    expect(Operation::query()->findOrFail($run['operation_id'])->authorized_permission)->toBe('service.manage');
});

it('refuses on Discord what the map refuses', function () {
    $keypair = sodium_crypto_sign_keypair();
    config()->set('onhost.discord.public_key', bin2hex(sodium_crypto_sign_publickey($keypair)));
    config()->set('onhost.discord.application_id', '123456789');
    $secret = sodium_crypto_sign_secretkey($keypair);
    [$owner, $org] = $this->customerWithOrganization();
    $web = featureWebService($org, 'aapanel');
    sasgPanels();
    $link = DiscordLink::query()->create(['organization_id' => $org->id, 'user_id' => $owner->id, 'discord_user_id' => '4343', 'discord_username' => 'jana', 'state' => 'linked', 'linked_at' => now()]);

    // pushing a staging copy and deleting a backup take a fresh step-up, which a button cannot give — even for the owner
    foreach (['staging.push' => [], 'backup.delete' => ['remote_id' => 'bk-1']] as $action => $params) {
        $reply = sasgDiscordClick($this, $secret, $link, $web, $action, $params);
        expect($reply['data']['content'])->toContain('This action needs the client panel and a second verification.');
    }
    expect(Operation::query()->where('service_id', $web->id)->exists())->toBeFalse();

    // an ordinary backup still runs, and its run asks the same permission again (H315)
    $reply = sasgDiscordClick($this, $secret, $link, $web, 'backup', ['kind' => 'manual']);
    expect($reply['data']['content'])->toContain('Spuštěno');
    $operation = Operation::query()->where('service_id', $web->id)->firstOrFail();
    expect(data_get($operation->desired, 'action'))->toBe('backup')->and($operation->authorized_permission)->toBe('service.manage');
});

it('names no step-up action in the hook and button lists', function () {
    foreach ([ActionHookService::ALLOWED, DiscordService::BUTTON_ACTIONS] as $list) {
        // monitoring.set is the platform's own monitor (UptimeMonitor), not a workflow action: a hook of it is refused as unknown
        foreach (array_diff($list, ['monitoring.set']) as $action) {
            expect(in_array($action, ServiceActionWorkflow::ACTIONS, true))->toBeTrue($action)
                ->and(ServiceActionCommand::needsFreshStepUp($action))->toBeFalse($action);
        }
    }
});

it('takes the SSH keys of somebody who keeps managing the service but lost its console', function () {
    [, $org] = $this->customerWithOrganization();
    $shop = featureWebService($org, 'ispconfig');
    $agency = sasgMember($org, 'svc_manage', [$shop], 'agentura@sasg.test'); // what remains after the console was taken back
    $devops = sasgMember($org, 'svc_console', [$shop], 'devops@sasg.test'); // still holds the console
    $key = fn (User $user, string $account) => SshKeyGrant::query()->create(['organization_id' => $org->id, 'service_id' => $shop->id, 'target_remote_id' => $account, 'target_label' => 'deploy', 'owner_user_id' => $user->id, 'key_type' => 'ssh-ed25519', 'fingerprint' => 'SHA256:'.Str::random(43), 'state' => SshKeyGrant::ACTIVE, 'installed_at' => now()]);
    $agencyKey = $key($agency, '30');
    $devopsKey = $key($devops, '31');

    foreach ([$agency, $devops] as $user) {
        app(OutboxPublisher::class)->publish(GenericEvent::of('service.access.revoked', 'service', $shop->id, ['grant_id' => 'sag-'.$user->id, 'user_id' => $user->id, 'email' => $user->email, 'organization_id' => $org->id, 'service' => 'shop.cz'], $org->id));
    }
    app(OutboxPublisher::class)->relayPending();

    // a key opens a shell, which is the console's: managing alone does not keep it; the console does
    expect($agencyKey->fresh()->state)->toBe(SshKeyGrant::REVOKING)
        ->and($devopsKey->fresh()->state)->toBe(SshKeyGrant::ACTIVE);
});

/** PUT the backup schedule of a service as whoever the test acts as. */
function sasgSchedulePut(TestCase $test, Service $service, array $body, string $key, array $headers = [])
{
    return $test->putJson("/v1/services/{$service->id}/backups/schedule", $body, ['X-Organization' => $service->organization_id, 'Idempotency-Key' => $key] + $headers);
}

it('does not let the backup schedule thin out backups for somebody who may not delete them', function () {
    // review round 2, HIGH: fewer generations or days is a deletion — the next BackupScheduler tick prunes every scheduled
    // backup beyond them (BackupDailyKeepers::beyondGenerations/surplus, deleted_by `retention`) — so lowering either asks what
    // deleting a backup by hand asks (`backup.delete` at the service, fresh step-up); keeping or raising stays managing
    [$owner, $org] = $this->customerWithOrganization();
    $web = featureWebService($org, 'aapanel');
    sasgPanels();
    expect(app(ServiceFeatures::class)->features($web)['backup_schedule']['options'])->toMatchArray(['days' => 7, 'generations' => 7]); // not vacuous
    $developer = sasgMember($org, 'developer', [], 'dev@sasg.test');
    app(StepUpService::class)->grant($developer, 'totp', null, '127.0.0.1'); // a fresh step-up changes nothing about a missing permission
    $this->actingAs($developer, 'sanctum');

    sasgSchedulePut($this, $web, ['frequency' => 'weekly', 'days' => 7, 'generations' => 7], 'sasg-bs-1')->assertOk(); // keeping is managing
    foreach ([['generations' => 1], ['days' => 1], ['days' => 7, 'generations' => 6]] as $i => $lower) {
        $response = sasgSchedulePut($this, $web, $lower, 'sasg-bs-low-'.$i)->assertForbidden()->assertJsonPath('error', 'access_not_approved');
        expect((string) $response->json('message'))->toContain('backup.delete');
    }
    expect(BackupPolicy::query()->where('service_id', $web->id)->sole()->retention)->toBe(['days' => 7, 'generations' => 7]); // what the next prune reads

    // the owner may, with a fresh step-up — and the audit row of the lowering says under which one
    $this->actingAs($owner, 'sanctum');
    sasgSchedulePut($this, $web, ['generations' => 2], 'sasg-bs-2')->assertForbidden()->assertJsonPath('error', 'step_up_required');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    sasgSchedulePut($this, $web, ['days' => 3, 'generations' => 2], 'sasg-bs-3')->assertOk()->assertJsonPath('schedule.generations', 2);
    expect(BackupPolicy::query()->where('service_id', $web->id)->sole()->retention)->toBe(['days' => 3, 'generations' => 2])
        ->and(AuditEvent::query()->where('action', 'service.backup_schedule.set')->where('actor_id', $owner->id)->sole()->step_up_method)->toBe('totp');

    // from the lowered value, raising it again is managing once more
    $this->actingAs($developer, 'sanctum');
    sasgSchedulePut($this, $web, ['days' => 7, 'generations' => 7], 'sasg-bs-4')->assertOk();
    expect(BackupPolicy::query()->where('service_id', $web->id)->sole()->retention)->toBe(['days' => 7, 'generations' => 7]);
});

it('does not let a svc_manage guest lower what the backup schedule keeps', function () {
    [, $org] = $this->customerWithOrganization();
    $web = featureWebService($org, 'aapanel');
    sasgPanels();
    $guest = sasgMember($org, 'svc_manage', [$web], 'agentura@sasg.test');
    app(StepUpService::class)->grant($guest, 'totp', null, '127.0.0.1');
    $this->actingAs($guest, 'sanctum');

    // refused before the handler already: WebToolsController asks `service.manage` at the ORGANIZATION, which a guest holds only
    // on its services (so a guest cannot set the schedule at all). A regression guard — the thinning gate stands behind it.
    $response = sasgSchedulePut($this, $web, ['generations' => 1], 'sasg-bsg-1')->assertForbidden()->assertJsonPath('error', 'access_not_approved');
    expect((string) $response->json('message'))->toContain('service.manage')
        ->and(BackupPolicy::query()->where('service_id', $web->id)->exists())->toBeFalse();
});

it('does not let a power-only API token lower what the backup schedule keeps', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $web = featureWebService($org, 'aapanel');
    sasgPanels();
    $token = $owner->createToken('ci', ['services:read', 'services:power'])->plainTextToken;

    // the schedule PUT is in the token's `services` family; a token has no person to give the step-up that thinning takes
    $this->withToken($token);
    sasgSchedulePut($this, $web, ['generations' => 1], 'sasg-bst-1')->assertForbidden()->assertJsonPath('error', 'step_up_required');
    expect(BackupPolicy::query()->where('service_id', $web->id)->exists())->toBeFalse();
    sasgSchedulePut($this, $web, ['frequency' => 'weekly'], 'sasg-bst-2')->assertOk(); // keeping what is kept is still the token's
});

it('does not run a Discord button twice when the click arrives twice', function () {
    // review round 2, MEDIUM: a double click (or Discord's retry) can read the cached proposal twice before the first request
    // forgets it. "One operation per service at a time" (ServiceService::requestAction) catches a second click only while the
    // first run is still open, and only as a check before the insert; a quick action that already finished (or two requests
    // between the check and the insert) started it twice under two random keys. The key is now the button's own, so the
    // second start finds the first operation (and the unique index on the key stops a true race)
    $keypair = sodium_crypto_sign_keypair();
    config()->set('onhost.discord.public_key', bin2hex(sodium_crypto_sign_publickey($keypair)));
    config()->set('onhost.discord.application_id', '123456789');
    [$owner, $org] = $this->customerWithOrganization();
    $web = featureWebService($org, 'aapanel');
    sasgPanels();
    $link = DiscordLink::query()->create(['organization_id' => $org->id, 'user_id' => $owner->id, 'discord_user_id' => '4344', 'discord_username' => 'jana', 'state' => 'linked', 'linked_at' => now()]);
    $id = 'dblclick00000001';
    $body = json_encode(['type' => 3, 'member' => ['user' => ['id' => '4344', 'username' => 'jana']], 'data' => ['custom_id' => 'act:'.$id]]);

    $replies = [];
    foreach ([1, 2] as $click) {
        cache()->put('discord:proposal:'.$id, ['service_id' => $web->id, 'action' => 'backup', 'params' => ['kind' => 'manual'], 'label' => 'backup', 'link_id' => $link->id], 900); // both requests read it
        $replies[] = (string) $this->call('POST', '/v1/integrations/discord/interactions', [], [], [], sasgDiscordSign(sodium_crypto_sign_secretkey($keypair), $body), $body)->assertOk()->json('data.content');
        Operation::query()->where('service_id', $web->id)->update(['state' => Operation::SUCCEEDED]); // the backup was quick
    }
    $operation = Operation::query()->where('service_id', $web->id)->sole(); // one backup for one click
    expect($replies[0])->toContain('Spuštěno')->toContain(substr($operation->id, -6))
        ->and($replies[1])->toContain('Spuštěno')->toContain(substr($operation->id, -6)); // the repeat names the same operation
});

/** A web plan keeping 60 days and seven generations, its schedule set to `$frequency` (the owner's choice within the plan). */
function sasgScheduleOf(Organization $org, string $frequency): Service
{
    $web = featureWebService($org, 'aapanel');
    $web->forceFill(['entitlements' => array_merge((array) $web->entitlements, ['backup_days' => 60, 'backup_generations' => 7])])->save();
    app(ServiceFeatures::class)->forget($web);
    BackupPolicy::query()->create(['service_id' => $web->id, 'product_key' => $web->product_key, 'schedule' => ['frequency' => $frequency], 'retention' => ['days' => 60, 'generations' => 7], 'offsite' => false]);

    return $web->fresh();
}

it('does not let a more frequent schedule thin out the history for somebody who may not delete backups', function () {
    // security review round 2, MEDIUM (fix round 1): with `backups.as_sold` off the prune keeps the newest N generations
    // (BackupDailyKeepers::beyondGenerations), so the owner's weekly x 7 reaches seven weeks back and daily x 7 one week —
    // switching to daily prunes six weeks of history within a week while days and generations stay the same. A change after
    // which the kept history reaches less far back is a deletion, as lowering days or generations is.
    [$owner, $org] = $this->customerWithOrganization();
    $web = sasgScheduleOf($org, 'weekly');
    sasgPanels();
    expect(app(ServiceFeatures::class)->features($web)['backup_schedule']['options'])->toMatchArray(['frequency' => 'daily', 'days' => 60, 'generations' => 7]); // daily is within the plan
    $unchanged = fn () => expect(BackupPolicy::query()->where('service_id', $web->id)->sole()->schedule)->toBe(['frequency' => 'weekly']);

    $developer = sasgMember($org, 'developer', [], 'dev@sasg.test');
    app(StepUpService::class)->grant($developer, 'totp', null, '127.0.0.1'); // a fresh step-up changes nothing about a missing permission
    $this->actingAs($developer, 'sanctum');
    $response = sasgSchedulePut($this, $web, ['frequency' => 'daily'], 'sasg-bf-1')->assertForbidden()->assertJsonPath('error', 'access_not_approved');
    expect((string) $response->json('message'))->toContain('backup.delete');
    $unchanged();

    // a svc_manage guest: refused before the handler already (WebToolsController asks service.manage at the organization)
    $guest = sasgMember($org, 'svc_manage', [$web], 'agentura@sasg.test');
    app(StepUpService::class)->grant($guest, 'totp', null, '127.0.0.1');
    $this->actingAs($guest, 'sanctum');
    sasgSchedulePut($this, $web, ['frequency' => 'daily'], 'sasg-bf-2')->assertForbidden()->assertJsonPath('error', 'access_not_approved');
    $unchanged();

    // a power-only token of the owner: no person to give the step-up
    $this->app['auth']->forgetGuards();
    $this->withToken($owner->createToken('ci', ['services:read', 'services:power'])->plainTextToken);
    sasgSchedulePut($this, $web, ['frequency' => 'daily'], 'sasg-bf-3')->assertForbidden()->assertJsonPath('error', 'step_up_required');
    $unchanged();
});

it('lets the owner make the backup schedule more frequent with a fresh step-up', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $web = sasgScheduleOf($org, 'weekly');
    sasgPanels();
    $this->actingAs($owner, 'sanctum');

    sasgSchedulePut($this, $web, ['frequency' => 'daily'], 'sasg-bf-4')->assertForbidden()->assertJsonPath('error', 'step_up_required');
    expect(BackupPolicy::query()->where('service_id', $web->id)->sole()->schedule)->toBe(['frequency' => 'weekly']);
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    sasgSchedulePut($this, $web, ['frequency' => 'daily'], 'sasg-bf-5')->assertOk()->assertJsonPath('schedule.frequency', 'daily');
    expect(BackupPolicy::query()->where('service_id', $web->id)->sole()->schedule)->toBe(['frequency' => 'daily'])
        ->and(AuditEvent::query()->where('action', 'service.backup_schedule.set')->where('actor_id', $owner->id)->sole()->step_up_method)->toBe('totp');
});

it('lets somebody who manages make the backup schedule reach further back', function () {
    [, $org] = $this->customerWithOrganization();
    $web = sasgScheduleOf($org, 'daily');
    sasgPanels();
    $developer = sasgMember($org, 'developer', [], 'dev@sasg.test');
    $this->actingAs($developer, 'sanctum');

    // daily x 7 reaches a week back, weekly x 7 seven weeks: nothing kept now is pruned sooner, so it stays managing (no step-up)
    sasgSchedulePut($this, $web, ['frequency' => 'weekly'], 'sasg-bf-up')->assertOk()->assertJsonPath('schedule.frequency', 'weekly');
    expect(BackupPolicy::query()->where('service_id', $web->id)->sole()->schedule)->toBe(['frequency' => 'weekly']);
});

it('reckons the history a schedule keeps as far back as the prune leaves it', function (int $minutes, int $days, int $generations, bool $keepers) {
    // one source of truth: the thinning gate compares BackupDailyKeepers::historyMinutes, so it must say what the prune's own
    // selectors (beyondGenerations / surplus, plus the expiry at retention_until) really leave of a long run of scheduled backups
    [, $org] = $this->customerWithOrganization();
    $web = featureWebService($org, 'aapanel');
    $this->travelTo(now()->startOfDay()->setTime(3, 5));
    for ($k = 0; $k * $minutes <= 100 * 1440; $k++) {
        $at = now()->subMinutes($k * $minutes);
        Backup::query()->create(['service_id' => $web->id, 'organization_id' => $org->id, 'kind' => 'scheduled', 'state' => 'completed', 'protected' => false,
            'started_at' => $at, 'finished_at' => $at->copy()->addMinutes(5), 'retention_until' => $at->copy()->addDays($days), 'size_bytes' => 1000]);
    }
    $pruned = ($keepers ? BackupDailyKeepers::surplus($web, $generations, $days, 10000) : BackupDailyKeepers::beyondGenerations($web, $generations, 10000))->pluck('id')->all();
    $oldest = Backup::query()->where('service_id', $web->id)->whereNotIn('id', $pruned)->where('retention_until', '>=', now())->orderBy('started_at')->firstOrFail()->started_at;
    $observed = (int) round($oldest->diffInMinutes(now(), true));

    $history = BackupDailyKeepers::historyMinutes($minutes, $days, $generations, $keepers);
    $grain = $keepers ? max($minutes, 1440) : $minutes; // a keeper is the newest backup of its calendar day: exact to the day
    expect($observed)->toBeLessThanOrEqual($history)->toBeGreaterThanOrEqual($history - $grain);
})->with([
    'weekly x 7, rule off' => [10080, 60, 7, false],
    'daily x 7, rule off' => [1440, 60, 7, false],
    'six-hourly x 7 inside one day, rule off' => [360, 1, 7, false],
    'six-hourly x 7, daily keepers' => [360, 30, 7, true],
    'weekly x 3, daily keepers' => [10080, 60, 3, true],
]);

/** `driveOperation()` for this file, whose queue is faked: the runner takes each step itself. */
function sasgRunOperation(Operation $operation, int $maxTicks = 20): Operation
{
    for ($i = 0; $i < $maxTicks && ! $operation->refresh()->isTerminal() && $operation->state !== Operation::FAILED; $i++) {
        app(OperationRunner::class)->tick($operation, 60);
    }

    return $operation->refresh();
}

/** The game panel's collaborator list, by reference: listing and deleting a sub-user; everything else answers empty. */
function sasgGamePanel(array &$subusers): void
{
    Http::fake(function (Request $request) use (&$subusers) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $list = fn (array $items) => Http::response(['object' => 'list', 'data' => array_map(fn ($a) => ['object' => 'server_subuser', 'attributes' => $a], $items), 'meta' => ['pagination' => ['total_pages' => 1]]]);
        if (str_starts_with($request->url(), PTERO) && str_ends_with($path, '/users') && $request->method() === 'GET') {
            return $list(array_values($subusers));
        }
        if (str_starts_with($request->url(), PTERO) && preg_match('~/users/(su-\d+)$~', $path, $mm) === 1 && $request->method() === 'DELETE') {
            unset($subusers[$mm[1]]);

            return Http::response('', 204);
        }

        return str_starts_with($request->url(), PTERO) ? $list([]) : Http::response(['status' => true, 'msg' => 'ok', 'data' => []]);
    });
}

/**
 * A guest of the organization with an SSH key on the web and a sub-user on the game server, both services shared with
 * `$capabilities` by the owner.
 *
 * @return array{0:User,1:Service,2:Service,3:SshKeyGrant}
 */
function sasgSharedWithArtefacts(CommandContext $owner, Organization $org, array $capabilities, array &$subusers): array
{
    $web = featureWebService($org, 'ispconfig');
    $game = featureGameService($org, ['subusers' => 3]);
    $guest = sasgMember($org, 'svc_view', [], 'agentura@sasg.test'); // a guest membership, nothing shared yet: the share binds
    foreach ([$web, $game] as $service) {
        expect(app(ServiceAccessService::class)->share($org, $service, $guest->email, $capabilities, $owner)->state)->toBe(ServiceAccessGrant::ACTIVE);
    }
    $key = SshKeyGrant::query()->create(['organization_id' => $org->id, 'service_id' => $web->id, 'target_remote_id' => '30', 'target_label' => 'deploy', 'owner_user_id' => $guest->id, 'key_type' => 'ssh-ed25519', 'fingerprint' => 'SHA256:'.Str::random(43), 'state' => SshKeyGrant::ACTIVE, 'installed_at' => now()]);
    $subusers = ['su-1' => ['uuid' => 'su-1', 'email' => $guest->email, 'permissions' => ['control.console'], 'created_at' => null]];
    app(OutboxPublisher::class)->relayPending();

    return [$guest, $web, $game, $key];
}

it('takes the SSH keys and game sub-users of a guest whose share drops from console to managing', function () {
    // security review round 2, MEDIUM (fix round 1): re-sharing an OPEN grant with fewer capabilities rewrote the bindings and
    // published only service.access.granted — the guest's SSH key and game sub-user, console-level since TASK-0029, stayed on
    // the panels although the console was taken back. The dropped console now takes them as a revocation would.
    [$owner, $org] = $this->customerWithOrganization();
    $subusers = [];
    sasgGamePanel($subusers);
    [$guest, $web, $game, $key] = sasgSharedWithArtefacts($this->contextFor($owner, $org, 'totp'), $org, ['console'], $subusers);
    expect($key->fresh()->state)->toBe(SshKeyGrant::ACTIVE)->and(array_keys($subusers))->toBe(['su-1']); // not vacuous

    foreach ([$web, $game] as $service) {
        app(ServiceAccessService::class)->share($org, $service, $guest->email, ['manage'], $this->contextFor($owner, $org, 'totp'));
    }
    app(OutboxPublisher::class)->relayPending();

    expect($key->fresh()->state)->toBe(SshKeyGrant::REVOKING)
        ->and(OutboxMessage::query()->where('name', 'service.access.reduced')->pluck('payload')->map(fn ($p) => data_get($p, 'dropped'))->all())->toBe([['console'], ['console']])
        ->and(Notification::query()->where('organization_id', $org->id)->where('title', 'like', 'Konzole služby odebrána%')->count())->toBe(2); // the organization hears it
    $removal = Operation::query()->where('service_id', $game->id)->where('idempotency_key', 'like', 'member-removed:%')->sole();
    expect(data_get($removal->desired, 'action'))->toBe('subuser.delete')
        ->and(sasgRunOperation($removal)->state)->toBe(Operation::SUCCEEDED)
        ->and($subusers)->toBe([]);
    // managing stays: only what the console had put on the panels went
    app(Authorizer::class)->forget($guest);
    expect(app(Authorizer::class)->can($guest, 'service.manage', CommandScope::resource($game->id, $org->id, $game->project_id)))->toBeTrue()
        ->and(app(Authorizer::class)->can($guest, 'service.console', CommandScope::resource($game->id, $org->id, $game->project_id)))->toBeFalse();
});

it('takes nothing when a share is raised to the console or given again unchanged', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $subusers = [];
    sasgGamePanel($subusers);
    [$guest, $web, $game, $key] = sasgSharedWithArtefacts($this->contextFor($owner, $org, 'totp'), $org, ['manage'], $subusers);

    foreach ([['console'], ['console'], ['console', 'backups']] as $capabilities) { // raised, the same again, raised further
        foreach ([$web, $game] as $service) {
            app(ServiceAccessService::class)->share($org, $service, $guest->email, $capabilities, $this->contextFor($owner, $org, 'totp'));
        }
        app(OutboxPublisher::class)->relayPending();
    }

    expect($key->fresh()->state)->toBe(SshKeyGrant::ACTIVE)
        ->and(array_keys($subusers))->toBe(['su-1'])
        ->and(Operation::query()->where('idempotency_key', 'like', 'member-removed:%')->exists())->toBeFalse()
        ->and(OutboxMessage::query()->where('name', 'service.access.reduced')->exists())->toBeFalse();
});
