<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Integrations\ActionHookService;
use Onhost\Domain\Integrations\DiscordService;
use Onhost\Domain\Integrations\Models\ActionHook;
use Onhost\Domain\Integrations\Models\DiscordLink;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\SshKeyGrant;
use Onhost\Domain\Services\ServiceSpecService;
use Onhost\Platform\Events\GenericEvent;
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
    $result = $specs->apply($game, sasgSchedules(), $this->contextFor($owner, $org), 'spec-2');
    expect($result['skipped'])->toBe([])
        ->and(array_column($result['operations'], 'action'))->toBe(['schedule.create', 'schedule.create'])
        ->and(sasgOperation($result, 0)->authorized_permission)->toBe('service.console')
        ->and(sasgOperation($result, 1)->authorized_permission)->toBe('service.manage');
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
