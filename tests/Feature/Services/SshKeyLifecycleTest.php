<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\SshKeyGrant;
use Onhost\Domain\Services\SshKeyLedger;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;
use Tests\TestCase;

/*
 * The life of an SSH key on a shell account (Brain card H185). The panel keeps a key as text and knows no people; the
 * ledger knows whose key it is, by fingerprint. A member who leaves loses their keys on every site of the organization,
 * and a revocation the panel has not taken stays visibly open — the key may still open a session until it is confirmed.
 */

const LIFECYCLE_KEY_A = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIGk0X2p3YkY5cE9xR1pVd1lqU2tXbEt0cUp2ZHVIZlg5 owner@laptop';
const LIFECYCLE_KEY_B = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIHh5ejEyMzQ1Njc4OTBhYmNkZWZnaGlqa2xtbm9wcXJzdHV2 contractor@desk';

beforeEach(fn () => Http::preventStrayRequests());

/** An ISPConfig that keeps shell accounts in `$panel`; `$panel['refuse']` makes it refuse updates. */
function lifecyclePanel(array &$panel): void
{
    $site = ['domain_id' => 7, 'domain' => 'shop.cz', 'sys_groupid' => 3, 'system_user' => 'web7', 'system_group' => 'client3', 'document_root' => '/var/www/clients/client3/web7'];
    Http::fake(function ($request) use (&$panel, $site) {
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $body = $request->data();
        $ok = fn ($response) => Http::response(['code' => 'ok', 'message' => '', 'response' => $response]);
        switch ($function) {
            case 'login': return $ok('sess-1');
            case 'sites_web_domain_get': return $ok(is_array($body['primary_id'] ?? null) ? [$site] : $site);
            case 'monitor_jobqueue_count': return $ok(0);
            case 'sites_shell_user_get': return $ok(array_values($panel['shell']));
            case 'sites_shell_user_add':
                $id = 30 + count($panel['shell']);
                $panel['shell'][] = ['shell_user_id' => $id, 'username' => $body['params']['username'], 'ssh_rsa' => $body['params']['ssh_rsa'], 'chroot' => 'jailkit', 'active' => 'y'];

                return $ok($id);
            case 'sites_shell_user_update':
                foreach ($panel['shell'] as &$row) {
                    if ($row['shell_user_id'] === (int) $body['primary_id']) {
                        $row['ssh_rsa'] = $body['params']['ssh_rsa'];
                    }
                }

                return $ok(true);
        }

        return Http::response(['code' => 'remote_fault', 'message' => "unexpected {$function}", 'response' => false]);
    });
}

/** Post an action and drive its operation to the end (ISPConfig confirms a change only once its job queue is empty). */
function lifecycleRun(TestCase $test, string $serviceId, string $action, array $params, string $key): Operation
{
    $response = $test->withHeader('Idempotency-Key', $key)->postJson("/v1/services/{$serviceId}/actions", ['action' => $action, 'params' => $params])->assertAccepted();
    $operation = driveOperation(Operation::query()->findOrFail($response->json('operation_id')));
    expect($operation->state)->toBe(Operation::SUCCEEDED, "operation {$action}: ".json_encode($operation->error));

    return $operation;
}

it('records whose key sits on a shell account by fingerprint, never the key, and hands it over when it is replaced', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $contractor = $this->customer();
    OrganizationMembership::query()->create(['organization_id' => $org->id, 'user_id' => $contractor->id, 'role_key' => 'developer', 'state' => 'active']);
    $outsider = $this->customer();
    $panel = ['shell' => []];
    lifecyclePanel($panel);
    $this->actingAs($owner, 'sanctum');

    // a key nobody named belongs to the member who installed it
    lifecycleRun($this, $service->id, 'shell.create', ['user' => 'deploy', 'password' => 'Correct-Horse-Battery-9', 'ssh_key' => LIFECYCLE_KEY_A], 'lc-1');
    $grant = SshKeyGrant::query()->where('service_id', $service->id)->firstOrFail();
    $blob = base64_decode(explode(' ', LIFECYCLE_KEY_A)[1], true);
    expect($grant->state)->toBe(SshKeyGrant::ACTIVE)->and($grant->owner_user_id)->toBe($owner->id)->and($grant->target_remote_id)->toBe('30')
        ->and($grant->fingerprint)->toBe('SHA256:'.rtrim(base64_encode(hash('sha256', $blob, true)), '='))
        ->and($grant->getAttribute('comment'))->toBe('owner@laptop');
    // only the fingerprint is ours to keep: the key stays at the panel
    expect(json_encode(DB::table('ssh_key_grants')->get()))->not->toContain(explode(' ', LIFECYCLE_KEY_A)[1]);

    // the customer sees it on the account, and in the history of the service
    $account = $this->getJson("/v1/services/{$service->id}/resources/shell_users?fresh=1")->assertOk()->json('data.0');
    expect($account['has_key'])->toBeTrue()->and($account['key'])->toMatchArray(['known' => true, 'fingerprint' => $grant->fingerprint, 'state' => 'active', 'revocation_pending' => false])
        ->and($account['key']['owner']['user_id'])->toBe($owner->id);

    // a key cannot be put in the name of somebody outside the organization
    $this->withHeader('Idempotency-Key', 'lc-2')->postJson("/v1/services/{$service->id}/actions", ['action' => 'shell.key', 'params' => ['remote_id' => '30', 'ssh_key' => LIFECYCLE_KEY_B, 'owner_user_id' => $outsider->id]])->assertStatus(422)->assertJsonPath('error', 'action_param_invalid');

    // replaced in the name of another member: one account, one key, one owner
    lifecycleRun($this, $service->id, 'shell.key', ['remote_id' => '30', 'ssh_key' => LIFECYCLE_KEY_B, 'owner_user_id' => $contractor->id], 'lc-3');
    $history = $this->getJson("/v1/services/{$service->id}/ssh-keys")->assertOk()->json('data');
    expect($history['pending_revocations'])->toBe(0)->and(collect($history['keys'])->pluck('state')->all())->toBe(['active', 'replaced'])
        ->and($history['keys'][0]['owner'])->toMatchArray(['user_id' => $contractor->id, 'member' => true])->and($history['keys'][0]['account']['user'])->toEndWith('_deploy');
    expect(SshKeyLedger::describe('not a key'))->toBeNull();

    // another organization sees nothing of it
    [$stranger] = $this->customerWithOrganization();
    $this->actingAs($stranger, 'sanctum');
    $this->getJson("/v1/services/{$service->id}/ssh-keys")->assertForbidden();
});

it('takes a removed member\'s keys off every account at once and tells the organization', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $contractor = $this->customer();
    OrganizationMembership::query()->create(['organization_id' => $org->id, 'user_id' => $contractor->id, 'role_key' => 'developer', 'state' => 'active']);
    $panel = ['shell' => []];
    lifecyclePanel($panel);
    $this->actingAs($owner, 'sanctum');
    lifecycleRun($this, $service->id, 'shell.create', ['user' => 'own', 'password' => 'Correct-Horse-Battery-9', 'ssh_key' => LIFECYCLE_KEY_A], 'rm-1');
    lifecycleRun($this, $service->id, 'shell.create', ['user' => 'ext', 'password' => 'Correct-Horse-Battery-9', 'ssh_key' => LIFECYCLE_KEY_B, 'owner_user_id' => $contractor->id], 'rm-2');
    expect(array_column($panel['shell'], 'ssh_rsa'))->toBe([LIFECYCLE_KEY_A, LIFECYCLE_KEY_B]);

    app(OrganizationService::class)->removeMember($org, $contractor, $this->contextFor($owner, $org));
    app(OutboxPublisher::class)->relayPending();

    // the panel has accepted the change but not applied it yet: the revocation is open, not done
    $theirs = SshKeyGrant::query()->where('owner_user_id', $contractor->id)->firstOrFail();
    expect($theirs->state)->toBe(SshKeyGrant::REVOKING)->and($theirs->revoke_operation_id)->not->toBeNull();
    driveOperation(Operation::query()->findOrFail($theirs->revoke_operation_id));
    app(OutboxPublisher::class)->relayPending(); // `operation.succeeded` closes it

    // the contractor's key is gone from the panel, the owner's is untouched
    expect(array_column($panel['shell'], 'ssh_rsa'))->toBe([LIFECYCLE_KEY_A, '']);
    $theirs = SshKeyGrant::query()->where('owner_user_id', $contractor->id)->firstOrFail();
    expect($theirs->state)->toBe(SshKeyGrant::REVOKED)->and($theirs->revoked_at)->not->toBeNull()->and($theirs->getAttribute('revoke_reason'))->toBe('member removed')
        ->and(SshKeyGrant::query()->where('owner_user_id', $owner->id)->value('state'))->toBe(SshKeyGrant::ACTIVE);
    $revocation = Operation::query()->findOrFail($theirs->revoke_operation_id);
    expect($revocation->state)->toBe(Operation::SUCCEEDED)->and($revocation->actor_type)->toBe('system');

    app(OutboxPublisher::class)->relayPending();
    $note = Notification::query()->where('organization_id', $org->id)->where('title', 'Rušíme SSH klíče odebraného člena')->firstOrFail();
    expect($note->body)->toContain('rušíme: 1.')->toContain('změňte ho')->not->toContain('nepotvrdil');
});

it('keeps a revocation the panel has not taken visibly open, repeats it, and puts one that keeps failing in front of staff', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $contractor = $this->customer();
    OrganizationMembership::query()->create(['organization_id' => $org->id, 'user_id' => $contractor->id, 'role_key' => 'developer', 'state' => 'active']);
    $panel = ['shell' => []];
    lifecyclePanel($panel);
    $this->actingAs($owner, 'sanctum');
    lifecycleRun($this, $service->id, 'shell.create', ['user' => 'ext', 'password' => 'Correct-Horse-Battery-9', 'ssh_key' => LIFECYCLE_KEY_B, 'owner_user_id' => $contractor->id], 'st-1');

    // the site is busy with something long (a migration, a restore): the revocation cannot even be queued
    $busy = Operation::query()->where('service_id', $service->id)->firstOrFail()->replicate(['idempotency_key']);
    $busy->forceFill(['idempotency_key' => 'long-running', 'state' => Operation::RUNNING, 'finished_at' => null])->save();

    app(OrganizationService::class)->removeMember($org, $contractor, $this->contextFor($owner, $org));
    app(OutboxPublisher::class)->relayPending();
    $grant = SshKeyGrant::query()->where('owner_user_id', $contractor->id)->firstOrFail();
    expect($grant->state)->toBe(SshKeyGrant::REVOKING)->and($grant->revoke_attempts)->toBe(1)->and($grant->last_error)->toContain('Another operation')
        ->and($panel['shell'][0]['ssh_rsa'])->toBe(LIFECYCLE_KEY_B); // the key still works, and nothing claims otherwise

    // the customer and staff both see it open, with the reason
    $history = $this->getJson("/v1/services/{$service->id}/ssh-keys")->assertOk()->json('data');
    expect($history['pending_revocations'])->toBe(1)->and($history['keys'][0]['state'])->toBe('revoking')->and($history['keys'][0]['owner']['member'])->toBeFalse()
        ->and($history['keys'][0]['revocation'])->toMatchArray(['attempts' => 1, 'reason' => 'member removed']);
    expect($this->getJson("/v1/services/{$service->id}/resources/shell_users?fresh=1")->assertOk()->json('data.0.key.revocation_pending'))->toBeTrue();
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('title', 'Rušíme SSH klíče odebraného člena')->value('body'))->toContain('Panel zatím nepotvrdil: 1.');
    $this->actingAs($this->staff(), 'sanctum');
    $open = $this->getJson('/v1/staff/provisioning/ssh-key-revocations')->assertOk()->json('data');
    expect($open['open'])->toBe(1)->and($open['rows'][0])->toMatchArray(['fingerprint' => $grant->fingerprint, 'stuck' => false])->and($open['rows'][0]['service']['control_plane']['state'])->toBe('available');

    // too early to repeat; then it is repeated and still refused; the third failure is reported to staff once
    $this->artisan('onhost:ssh-keys:settle')->assertExitCode(0);
    expect($grant->fresh()->revoke_attempts)->toBe(1);
    $this->travel(11)->minutes();
    $this->artisan('onhost:ssh-keys:settle')->assertExitCode(0);
    $this->travel(11)->minutes();
    $this->artisan('onhost:ssh-keys:settle')->assertExitCode(0);
    expect($grant->fresh()->revoke_attempts)->toBe(3)->and(OutboxMessage::query()->where('name', 'security.ssh_key.revocation.stuck')->count())->toBe(0);
    $this->travel(11)->minutes();
    $this->artisan('onhost:ssh-keys:settle')->assertExitCode(0);
    $this->travel(11)->minutes();
    $this->artisan('onhost:ssh-keys:settle')->assertExitCode(0);
    expect(OutboxMessage::query()->where('name', 'security.ssh_key.revocation.stuck')->count())->toBe(1)
        ->and($this->getJson('/v1/staff/provisioning/ssh-key-revocations')->assertOk()->json('data.rows.0.stuck'))->toBeTrue();

    // the site is free again: the next pass gets through and the panel drops the key
    $busy->forceFill(['state' => Operation::SUCCEEDED, 'finished_at' => now()])->save();
    $this->travel(11)->minutes();
    $this->artisan('onhost:ssh-keys:settle')->assertExitCode(0);
    driveOperation(Operation::query()->findOrFail($grant->fresh()->revoke_operation_id));
    $this->artisan('onhost:ssh-keys:settle')->assertExitCode(0); // the scheduled pass closes what the panel has applied, with or without the event
    expect($grant->fresh()->state)->toBe(SshKeyGrant::REVOKED)->and($panel['shell'][0]['ssh_rsa'])->toBe('')
        ->and($this->getJson('/v1/staff/provisioning/ssh-key-revocations')->assertOk()->json('data.open'))->toBe(0);
});
