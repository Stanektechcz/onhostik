<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Integrations\ActionHookService;
use Onhost\Domain\Integrations\DiscordService;
use Onhost\Domain\Integrations\Models\ActionHook;
use Onhost\Domain\Integrations\Models\DiscordLink;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * TASK-0035 (permission program IF-15 / P0-04, audit G1 + G11): a person who left the organization — or was moved to a role
 * that could not have made them — keeps no side door into it. A Discord link answered `/onhost services` and `/onhost status`
 * with nobody asking whether the person was still a member, and an action hook of a removed member stayed enabled, waiting
 * for the day its creator is let back in. The removal and the role change take them back (a listener on the outbox events),
 * every Discord read asks the person's CURRENT membership and what AssistantScope lets them see, and the links and hooks left
 * over from before are listed — and only on --apply revoked — by operator:integrations:orphan-links.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('onhost.discord.public_key', str_repeat('ab', 32));
    config()->set('onhost.discord.application_id', '123456789');
});

/** A second person in the organization with one role, the way an accepted invitation leaves them. */
function orphanMember(Organization $org, string $role, string $email): User
{
    $user = User::factory()->create(['email' => $email, 'name' => $role]);
    app(OrganizationService::class)->attachMember($org, $user, $role, CommandContext::system('test'), joinedNow: true);

    return $user;
}

/** The person links their Discord account the way the panel does it: a one-time code, then `/onhost link`. */
function orphanLinkDiscord(Organization $org, User $user, string $discordId): DiscordLink
{
    $discord = app(DiscordService::class);
    $code = $discord->createLinkCode($org, $user, CommandContext::system('test'))['code'];
    $discord->handleInteraction(orphanSlash('link', ['code' => $code], $discordId));

    return DiscordLink::query()->where('discord_user_id', $discordId)->where('state', 'linked')->sole();
}

function orphanSlash(string $sub, array $options, string $discordId): array
{
    return ['type' => 2, 'member' => ['user' => ['id' => $discordId, 'username' => 'u'.$discordId]], 'data' => ['name' => 'onhost', 'options' => [['name' => $sub, 'type' => 1, 'options' => array_map(fn ($k, $v) => ['name' => $k, 'value' => $v], array_keys($options), $options)]]]];
}

function orphanSays(string $sub, array $options, string $discordId): string
{
    return (string) (app(DiscordService::class)->handleInteraction(orphanSlash($sub, $options, $discordId))['data']['content'] ?? '');
}

function orphanHook(Organization $org, User $creator, Service $service): ActionHook
{
    $created = app(ActionHookService::class)->create($org, $creator, $service, 'Záloha z CI', 'backup', ['kind' => 'manual'], CommandContext::system('test')->withScope($org->id));

    return ActionHook::query()->findOrFail($created['hook']['id']);
}

function orphanRelay(): void
{
    app(OutboxPublisher::class)->relayPending();
}

it('gives a removed member nothing through Discord and disables the hooks they made', function () {
    [, $org] = $this->customerWithOrganization();
    featureWebService($org, 'aapanel');
    $service = Service::query()->where('organization_id', $org->id)->sole();
    $jana = orphanMember($org, 'org_admin', 'jana@orphan.test');
    $link = orphanLinkDiscord($org, $jana, '5001');
    $hook = orphanHook($org, $jana, $service);
    expect(orphanSays('services', [], '5001'))->toContain('shop.cz'); // while she is a member she sees the services

    app(OrganizationService::class)->removeMember($org, $jana, CommandContext::system('test'));
    orphanRelay();

    // the link is gone at once, with an audit record; the next command says so and names nothing of the organization
    expect($link->fresh()->state)->toBe('revoked')
        ->and(AuditEvent::query()->where('action', 'integration.discord.revoke')->where('resource_id', $link->id)->exists())->toBeTrue();
    expect(orphanSays('services', [], '5001'))->not->toContain('shop.cz');
    expect(orphanSays('status', ['service' => 'shop.cz'], '5001'))->not->toContain('shop.cz');
    // the hook is switched off, says why, and the URL runs nothing
    expect($hook->fresh()->enabled)->toBeFalse()->and($hook->fresh()->last_result)->toBe('member_removed')
        ->and(AuditEvent::query()->where('action', 'integration.hook.disable')->where('resource_id', $hook->id)->exists())->toBeTrue();
    $token = 'ahk_'.str_repeat('A', 40);
    $hook->forceFill(['token_hash' => hash('sha256', $token)])->save();
    expect(app(ActionHookService::class)->trigger($token, '127.0.0.1'))->toMatchArray(['accepted' => false, 'reason' => 'hook_disabled']);

    // let back in later as a viewer: the old hook stays off — it was hers, it is not re-armed by a new membership
    app(OrganizationService::class)->attachMember($org, $jana, 'viewer', CommandContext::system('test'), joinedNow: true);
    expect($hook->fresh()->enabled)->toBeFalse();
});

it('answers a Discord command with the current membership even before the listener ran', function () {
    [, $org] = $this->customerWithOrganization();
    featureWebService($org, 'aapanel');
    $petr = orphanMember($org, 'org_admin', 'petr@orphan.test');
    $link = orphanLinkDiscord($org, $petr, '5002');

    // the access ended on its date (H343): the binding stops at that second, the sweep that removes the membership comes later
    OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $petr->id)->update(['expires_at' => now()->subMinute()]);
    PolicyBinding::query()->where('principal_id', $petr->id)->where('organization_id', $org->id)->update(['expires_at' => now()->subMinute()]);

    expect(orphanSays('status', ['service' => 'shop.cz'], '5002'))->not->toContain('shop.cz');
    expect(orphanSays('services', [], '5002'))->not->toContain('shop.cz');
    expect($link->fresh()->state)->toBe('revoked'); // a link of somebody who is no member is taken back the moment it is used
});

it('shows a member through Discord only the services their own role lets them see', function () {
    [, $org] = $this->customerWithOrganization();
    featureWebService($org, 'aapanel');
    $shared = Service::query()->where('organization_id', $org->id)->sole();
    $hidden = $shared->replicate()->fill(['hostname' => 'skryty.cz', 'name' => 'Skrytý web', 'name_prefix' => null]);
    $hidden->save();
    // a guest with one shared service and a link left over from before (made while they were an administrator)
    $guest = orphanMember($org, 'guest', 'guest@orphan.test');
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $guest->id, 'role_key' => 'svc_view', 'scope_type' => 'resource', 'scope_id' => $shared->id, 'organization_id' => $org->id]);
    DiscordLink::query()->create(['organization_id' => $org->id, 'user_id' => $guest->id, 'discord_user_id' => '5003', 'discord_username' => 'guest', 'state' => 'linked', 'linked_at' => now(), 'locale' => 'cs']);

    $list = orphanSays('services', [], '5003');
    expect($list)->toContain('shop.cz')->not->toContain('skryty.cz');
    expect(orphanSays('status', ['service' => 'skryty.cz'], '5003'))->toContain('nenašel');
});

it('takes back what a smaller role could not have made, and leaves what it still could', function () {
    [, $org] = $this->customerWithOrganization();
    featureWebService($org, 'aapanel');
    $service = Service::query()->where('organization_id', $org->id)->sole();
    $olga = orphanMember($org, 'org_admin', 'olga@orphan.test');
    $link = orphanLinkDiscord($org, $olga, '5004');
    $hook = orphanHook($org, $olga, $service);

    // an administrator who became a developer still runs backups: the hook stays; a Discord link is an administrator's (organization.manage) and goes
    app(OrganizationService::class)->changeRole($org, $olga, 'developer', CommandContext::system('test'));
    orphanRelay();
    expect($hook->fresh()->enabled)->toBeTrue()
        ->and($link->fresh()->state)->toBe('revoked');

    // a developer who became a viewer may not run the backup any more: now the hook goes too
    app(OrganizationService::class)->changeRole($org, $olga, 'viewer', CommandContext::system('test'));
    orphanRelay();
    expect($hook->fresh()->enabled)->toBeFalse()->and($hook->fresh()->last_result)->toBe('role_changed');
});

it('lists orphaned links and hooks without touching them, and revokes them only on --apply', function () {
    [$owner, $org] = $this->customerWithOrganization();
    featureWebService($org, 'aapanel');
    $service = Service::query()->where('organization_id', $org->id)->sole();
    $gone = orphanMember($org, 'org_admin', 'gone@orphan.test');
    $demoted = orphanMember($org, 'org_admin', 'demoted@orphan.test');
    $goneLink = orphanLinkDiscord($org, $gone, '5005');
    $goneHook = orphanHook($org, $gone, $service);
    $demotedLink = orphanLinkDiscord($org, $demoted, '5006');
    $ownerLink = orphanLinkDiscord($org, $owner, '5007');
    $ownerHook = orphanHook($org, $owner, $service);
    // what the release before this one left behind: memberships ended and roles changed with nobody taking the side doors back
    OrganizationMembership::query()->where('user_id', $gone->id)->delete();
    PolicyBinding::query()->where('principal_id', $gone->id)->delete();
    OrganizationMembership::query()->where('user_id', $demoted->id)->update(['role_key' => 'viewer']);
    PolicyBinding::query()->where('principal_id', $demoted->id)->update(['role_key' => 'viewer']);

    $this->artisan('operator:integrations:orphan-links')->expectsOutputToContain($goneLink->id)->expectsOutputToContain($goneHook->id)->expectsOutputToContain($demotedLink->id)->assertSuccessful();
    expect($goneLink->fresh()->state)->toBe('linked')->and($goneHook->fresh()->enabled)->toBeTrue()->and($demotedLink->fresh()->state)->toBe('linked'); // a dry run changes nothing

    $this->artisan('operator:integrations:orphan-links', ['--apply' => true])->assertSuccessful();
    expect($goneLink->fresh()->state)->toBe('revoked')->and($goneHook->fresh()->enabled)->toBeFalse()->and($demotedLink->fresh()->state)->toBe('revoked')
        ->and($ownerLink->fresh()->state)->toBe('linked')->and($ownerHook->fresh()->enabled)->toBeTrue(); // the owner's own are no orphans
    expect(AuditEvent::query()->where('action', 'integration.discord.revoke')->count())->toBe(2)
        ->and(AuditEvent::query()->where('action', 'integration.hook.disable')->count())->toBe(1);
});
