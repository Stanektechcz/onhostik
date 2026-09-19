<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\Models\ProjectMembership;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Services\Models\SshKeyGrant;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Access that ends on a date (Brain card H343): a lecturer for one course, a contractor for one job. The permission
 * stops at that second by itself; the scheduled pass then removes the membership or the project role the way a removal
 * by hand does, which takes the person's panel accounts and SSH keys with it. The owner's access never ends.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** A key the ledger knows as the person's, without a panel round trip: the revocation is what is under test here. */
function expiryKeyFor(string $organizationId, string $serviceId, string $userId, string $account = '30'): SshKeyGrant
{
    return SshKeyGrant::query()->create([
        'organization_id' => $organizationId, 'service_id' => $serviceId, 'target_remote_id' => $account, 'target_label' => 'c1_lektor', 'owner_user_id' => $userId,
        'installed_by_type' => 'user', 'installed_by_id' => $userId, 'key_type' => 'ssh-ed25519', 'fingerprint' => 'SHA256:'.substr(hash('sha256', $userId.$account), 0, 43), 'state' => SshKeyGrant::ACTIVE, 'installed_at' => now(),
    ]);
}

it('ends a membership on its date: the permission stops at once, the clean-up follows and takes the keys along', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $this->actingAs($owner, 'sanctum');
    app(StepUpService::class)->grant($owner, 'password', null, '127.0.0.1');
    $until = now()->addDays(14)->startOfMinute();

    // the API takes the date with the invitation and refuses one in the past or one for the owner role
    $this->postJson("/v1/organizations/{$org->id}/invitations", ['email' => 'lektor@skola.cz', 'role' => 'developer', 'access_until' => now()->subDay()->toIso8601String()])->assertUnprocessable();
    $this->withHeader('Idempotency-Key', 'inv-own')->postJson("/v1/organizations/{$org->id}/invitations", ['email' => 'lektor@skola.cz', 'role' => 'owner', 'access_until' => $until->toIso8601String()])->assertUnprocessable();
    $this->withHeader('Idempotency-Key', 'inv-1')->postJson("/v1/organizations/{$org->id}/invitations", ['email' => 'lektor@skola.cz', 'role' => 'developer', 'access_until' => $until->toIso8601String()])->assertCreated();
    expect($this->getJson("/v1/organizations/{$org->id}")->assertOk()->json('data.invitations.0.access_until'))->toBe($until->toIso8601String());

    // accepted: the membership and the permission carry the same end
    $lecturer = $this->customer(['email' => 'lektor@skola.cz']);
    ['token' => $token] = app(OrganizationService::class)->invite($org, 'lektor@skola.cz', 'developer', $this->contextFor($owner, $org), $until);
    app(OrganizationService::class)->acceptInvitation($token, $lecturer, $this->contextFor($lecturer));
    expect(OrganizationMembership::query()->where('user_id', $lecturer->id)->value('expires_at'))->not->toBeNull()
        ->and(PolicyBinding::query()->where('principal_id', $lecturer->id)->where('scope_type', 'organization')->firstOrFail()->expires_at?->toIso8601String())->toBe($until->toIso8601String());
    expect(collect($this->getJson("/v1/organizations/{$org->id}")->json('data.members'))->firstWhere('user_id', $lecturer->id)['access_until'])->toBe($until->toIso8601String());
    $scope = CommandScope::resource($service->id, $org->id, $service->project_id);
    expect(app(Authorizer::class)->can($lecturer, 'service.manage', $scope))->toBeTrue();

    // a change of role keeps the end; only an explicit null makes the access permanent
    $this->withHeader('Idempotency-Key', 'role-1')->patchJson("/v1/organizations/{$org->id}/members/{$lecturer->id}", ['role' => 'viewer'])->assertOk();
    expect(OrganizationMembership::query()->where('user_id', $lecturer->id)->value('expires_at'))->not->toBeNull();
    $this->withHeader('Idempotency-Key', 'role-2')->patchJson("/v1/organizations/{$org->id}/members/{$lecturer->id}", ['role' => 'developer', 'access_until' => $until->toIso8601String()])->assertOk();
    app(OutboxPublisher::class)->relayPending(); // the role changes are settled before the key exists: what follows is about the date alone
    $key = expiryKeyFor($org->id, $service->id, $lecturer->id);
    // the owner's access never ends
    $this->withHeader('Idempotency-Key', 'role-own')->patchJson("/v1/organizations/{$org->id}/members/{$owner->id}", ['role' => 'owner', 'access_until' => $until->toIso8601String()])->assertUnprocessable()->assertJsonPath('error', 'owner_access_cannot_expire');

    // the date passes: nothing has run yet and the lecturer is already refused
    $this->travelTo($until->copy()->addMinute());
    app(Authorizer::class)->forget($lecturer);
    expect(app(Authorizer::class)->can($lecturer, 'service.manage', $scope))->toBeFalse();
    $this->actingAs($lecturer, 'sanctum');
    $this->getJson("/v1/services/{$service->id}")->assertForbidden();
    expect(OrganizationMembership::query()->where('user_id', $lecturer->id)->exists())->toBeTrue(); // the row is still there, it just grants nothing

    // the pass removes the membership like a removal by hand: the event that revokes panel accounts and keys goes out
    $this->artisan('onhost:access:expire')->assertExitCode(0);
    expect(OrganizationMembership::query()->where('user_id', $lecturer->id)->exists())->toBeFalse()
        ->and(PolicyBinding::query()->where('principal_id', $lecturer->id)->where('organization_id', $org->id)->exists())->toBeFalse()
        ->and(OutboxMessage::query()->where('name', 'organization.member.removed')->count())->toBe(1);
    app(OutboxPublisher::class)->relayPending();
    expect($key->fresh()->state)->toBe(SshKeyGrant::REVOKING)->and($key->fresh()->getAttribute('revoke_reason'))->toBe('member removed'); // asked for; the panel in this test does not answer, so it stays open
    app(OutboxPublisher::class)->relayPending();
    $note = Notification::query()->where('organization_id', $org->id)->where('title', 'Dočasný přístup skončil')->firstOrFail();
    expect($note->body)->toContain($lecturer->name)->not->toContain('projekt');

    // a second pass finds nothing
    $this->artisan('onhost:access:expire')->assertExitCode(0);
    expect(OutboxMessage::query()->where('name', 'organization.member.expired')->count())->toBe(1);
});

it('ends a project role on its date, never later than the membership, and leaves alone what the person still manages', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $this->actingAs($owner, 'sanctum');
    $course = $this->postJson("/v1/organizations/{$org->id}/projects", ['name' => 'Kurz PHP 2026'])->assertCreated()->json('project.id');
    $this->postJson("/v1/services/{$service->id}/project", ['project_id' => $course])->assertOk();
    $service->refresh();
    $organizations = app(OrganizationService::class);
    $lecturer = $this->customer();
    $organizations->attachMember($org, $lecturer, 'viewer', CommandContext::system('test'), true, now()->addDays(30));
    $colleague = $this->customer();
    $organizations->attachMember($org, $colleague, 'developer', CommandContext::system('test'), true);

    // a project role cannot outlive the membership it builds on
    $this->postJson("/v1/organizations/{$org->id}/projects/{$course}/members", ['user_id' => $lecturer->id, 'role' => 'developer', 'access_until' => now()->addDays(90)->toIso8601String()])->assertCreated();
    expect(ProjectMembership::query()->where('user_id', $lecturer->id)->firstOrFail()->expires_at?->toDateString())->toBe(now()->addDays(30)->toDateString());
    // the course itself is shorter
    $end = now()->addDays(7)->startOfMinute();
    $this->withHeader('Idempotency-Key', 'pm-2')->postJson("/v1/organizations/{$org->id}/projects/{$course}/members", ['user_id' => $lecturer->id, 'role' => 'developer', 'access_until' => $end->toIso8601String()])->assertCreated();
    $this->withHeader('Idempotency-Key', 'pm-3')->postJson("/v1/organizations/{$org->id}/projects/{$course}/members", ['user_id' => $colleague->id, 'role' => 'developer', 'access_until' => $end->toIso8601String()])->assertCreated();
    expect($this->getJson("/v1/organizations/{$org->id}/projects/{$course}")->assertOk()->json('data.members.0.access_until'))->not->toBeNull();

    $scope = CommandScope::resource($service->id, $org->id, $course);
    expect(app(Authorizer::class)->can($lecturer, 'service.manage', $scope))->toBeTrue();
    $theirs = expiryKeyFor($org->id, $service->id, $lecturer->id, '30');
    $kept = expiryKeyFor($org->id, $service->id, $colleague->id, '31');

    $this->travelTo($end->copy()->addMinute());
    app(Authorizer::class)->forget($lecturer);
    expect(app(Authorizer::class)->can($lecturer, 'service.manage', $scope))->toBeFalse() // the role ended by itself
        ->and(app(Authorizer::class)->can($lecturer, 'service.read', $scope))->toBeTrue();  // the membership has not

    $this->artisan('onhost:access:expire')->assertExitCode(0);
    app(OutboxPublisher::class)->relayPending();
    expect(ProjectMembership::query()->where('project_id', $course)->count())->toBe(0)
        ->and(OrganizationMembership::query()->where('user_id', $lecturer->id)->exists())->toBeTrue()
        ->and($theirs->fresh()->state)->toBe(SshKeyGrant::REVOKING)->and($theirs->fresh()->getAttribute('revoke_reason'))->toBe('project role ended')
        ->and($kept->fresh()->state)->toBe(SshKeyGrant::ACTIVE); // a developer of the whole organization lost nothing with the project role
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('title', 'Dočasný přístup skončil')->where('body', 'like', '%projekt Kurz PHP 2026%')->count())->toBe(2);
});

it('takes back what a smaller role no longer covers, and nothing from a role that never covered it', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $organizations = app(OrganizationService::class);
    $developer = $this->customer();
    $organizations->attachMember($org, $developer, 'developer', CommandContext::system('test'), true);
    $accountant = $this->customer();
    $organizations->attachMember($org, $accountant, 'viewer', CommandContext::system('test'), true);
    $theirs = expiryKeyFor($org->id, $service->id, $developer->id, '30');
    $given = expiryKeyFor($org->id, $service->id, $accountant->id, '31'); // the owner put a key in a viewer's name on purpose

    $this->actingAs($owner, 'sanctum');
    app(StepUpService::class)->grant($owner, 'password', null, '127.0.0.1');
    $this->withHeader('Idempotency-Key', 'down-1')->patchJson("/v1/organizations/{$org->id}/members/{$developer->id}", ['role' => 'viewer'])->assertOk();
    $this->withHeader('Idempotency-Key', 'side-1')->patchJson("/v1/organizations/{$org->id}/members/{$accountant->id}", ['role' => 'billing_admin'])->assertOk();
    app(OutboxPublisher::class)->relayPending();

    expect($theirs->fresh()->state)->toBe(SshKeyGrant::REVOKING)->and($theirs->fresh()->getAttribute('revoke_reason'))->toBe('role changed')
        ->and($given->fresh()->state)->toBe(SshKeyGrant::ACTIVE);

    // a bigger role loses nothing
    $other = expiryKeyFor($org->id, $service->id, $developer->id, '32');
    $this->withHeader('Idempotency-Key', 'up-1')->patchJson("/v1/organizations/{$org->id}/members/{$developer->id}", ['role' => 'org_admin'])->assertOk();
    app(OutboxPublisher::class)->relayPending();
    expect($other->fresh()->state)->toBe(SshKeyGrant::ACTIVE);
});
