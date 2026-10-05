<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationInvitation;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceAccessGrant;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

require_once __DIR__.'/../../Support/E2E/E2EHelpers.php';

/*
 * E8 — people around one organization, touching only the real HTTP routes.
 *
 * The owner signs up; three colleagues (organization admin, viewer, billing admin) are invited through the invitation API, accept
 * the mailed link and see exactly what their role allows — the sidebar the panel payload offers, a 403 on everything else. Two
 * outsiders get ONE service each (a capability role, the guest membership): they see that service and the actions that were ticked,
 * `svc_manage` has no console, and what is shared for one service says nothing of the other. Nobody hands out more than they hold.
 * Taking access back — a share revoked, a member removed — ends the session, the API token and the open console at once. The
 * ownership moves in two steps, with notifications to both.
 *
 * Only the edges are doubles (the game panel's websocket answer, the mail through Notification::fake). Asserts do not depend on row
 * order, so the flow holds on SQLite and PostgreSQL alike. Helpers: tests/Support/E2E/E2EHelpers.php, the `e2eShare*` ones below.
 */

const E2E_SHARE_RELAY_KEY = 'e2e-share-relay-key';

beforeEach(function () {
    e2eSeedPlatform();
    LaravelNotification::fake(); // the verification mails of the sign-ups (the platform's own notifications are rows, not this facade)
    Http::preventStrayRequests();
    config()->set('onhost.console.relay_url', 'https://relay.onhost.test/');
    config()->set('onhost.console.relay_key', E2E_SHARE_RELAY_KEY);
    Http::fake(function (Request $request) {
        if (str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/api/client/servers/e4c1abcd/websocket')) {
            return Http::response(['data' => ['socket' => 'wss://games01.node.test:8080/api/servers/e4c1-uuid/ws', 'token' => 'eyJ.wings-session-jwt.sig']]);
        }

        return null;
    });
});

/** Everyone who takes part signs up first (each over the real routes); the owner then gets a web hosting and a game server. */
function e2eShareWorld(object $test): array
{
    $world = [];
    $signed = 0;
    foreach (['owner' => 'Firma s.r.o.', 'admin' => 'Admin sole', 'viewer' => 'Viewer sole', 'billing' => 'Billing sole', 'agency' => 'Agentura s.r.o.', 'gamer' => 'Hráč s.r.o.', 'stranger' => 'Cizí s.r.o.'] as $who => $company) {
        if (($signed++) % 4 === 3) {
            $test->travel(61)->seconds(); // sign-up and verification are limited to 10 a minute per address: the next person comes a minute later
        }
        $test->flushSession();
        app('auth')->forgetGuards();
        [$user, $org, $password] = e2eSignUp($test, "{$who}@share.test", $company);
        $world[$who] = ['user' => $user, 'org' => $org, 'password' => $password];
    }
    $org = $world['owner']['org'];
    $world['shop'] = featureWebService($org, 'aapanel');
    $world['games'] = featureGameService($org);

    return $world;
}

/** Switch the test client to another person: fresh session, fresh guards, signed in through the portal's own guard. */
function e2eShareActAs(object $test, User $user): void
{
    $test->flushSession();
    app('auth')->forgetGuards();
    // the browser of the person before is gone: its session cookie (step-up included) must not ride on to the next one
    (function () {
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        $this->withCredentials = false;
    })->call($test);
    $test->actingAs($user);
}

/** A request of the person signed in now: browser headers, a fresh idempotency key, the organization worked in. */
function e2eShareCall(object $test, string $method, string $uri, Organization $org, string $label, array $body = []): TestResponse
{
    return $test->withHeaders(e2eHeaders($label) + ['X-Organization' => $org->id])->json($method, $uri, $body);
}

/** The accept token out of the latest mail of `$template` to `$email` (the link is the only place it travels). */
function e2eShareToken(string $email, string $template): string
{
    $mail = MailOutbox::query()->where('template_key', $template)->where('to', $email)->orderByDesc('created_at')->firstOrFail();
    expect((string) json_encode($mail->vars, JSON_UNESCAPED_SLASHES))->toMatch('/pozvanka=([^&"]+)/');
    preg_match('/pozvanka=([^&"]+)/', (string) json_encode($mail->vars, JSON_UNESCAPED_SLASHES), $m);

    return rawurldecode($m[1]);
}

/** The owner invites `$who` to the organization in `$role`, they accept the mailed link: the accept answer. @param array<string,mixed> $world */
function e2eShareJoin(object $test, array $world, string $who, string $role): array
{
    $owner = $world['owner'];
    e2eShareActAs($test, $owner['user']);
    e2eStepUp($test, $owner['password']);
    e2eShareCall($test, 'POST', "/v1/organizations/{$owner['org']->id}/invitations", $owner['org'], "invite-{$who}", ['email' => "{$who}@share.test", 'role' => $role])->assertCreated();
    $token = e2eShareToken("{$who}@share.test", 'invitation');

    e2eShareActAs($test, $world[$who]['user']);
    $accepted = e2eShareCall($test, 'POST', '/v1/organizations/invitations/accept', $owner['org'], "accept-{$who}", ['token' => $token])->assertOk();

    return $accepted->json('data');
}

/** The sidebar entries the panel payload hides from this person in the organization (`nav.links` + `nav.areas` that are off). @return list<string> */
function e2eShareHiddenNav(object $test, User $user, Organization $org): array
{
    e2eShareActAs($test, $user);
    $seam = $test->get('/surfaces/onhost-panel.js?organization='.$org->id)->assertOk()->getContent();
    $payload = json_decode(substr($seam, strlen('window.ONHOST_PANEL = '), -2), true, 512, JSON_THROW_ON_ERROR);
    $nav = array_merge($payload['nav']['links'], $payload['nav']['areas']);

    return collect($nav)->reject(fn (bool $on) => $on)->keys()->sort()->values()->all();
}

/** The ids of the services the panel payload hands this person. @return list<string> */
function e2eSharePanelServices(object $test, User $user, Organization $org): array
{
    e2eShareActAs($test, $user);
    $seam = $test->get('/surfaces/onhost-panel.js?organization='.$org->id)->assertOk()->getContent();
    $payload = json_decode(substr($seam, strlen('window.ONHOST_PANEL = '), -2), true, 512, JSON_THROW_ON_ERROR);

    $rows = collect($payload['services'])->reject(fn ($list, string $key) => str_starts_with($key, 'state:')) // the state views list a service a second time
        ->flatten(1)->filter(fn ($row) => ($row['type'] ?? '') !== 'domain');

    return $rows->pluck('id')->sort()->values()->all();
}

/** The relay (it holds a shared key) asking whether an open console may stay open. */
function e2eShareRelay(object $test, string $path): TestResponse
{
    return $test->flushHeaders()->withHeader('X-Relay-Key', E2E_SHARE_RELAY_KEY)->getJson($path);
}

function e2eShareRelayOutbox(): void
{
    app(OutboxPublisher::class)->relayPending();
}

it('lets an invited colleague see exactly what the role allows, and nobody hand out more than they hold', function () {
    $world = e2eShareWorld($this);
    $owner = $world['owner'];
    $org = $owner['org'];
    $shop = $world['shop'];

    // ── three roles come in through the invitation API; the mailed link is the only place the token travels ──
    foreach (['admin' => 'org_admin', 'viewer' => 'viewer', 'billing' => 'billing_admin'] as $who => $role) {
        $joined = e2eShareJoin($this, $world, $who, $role);
        expect($joined)->toMatchArray(['organization_id' => $org->id, 'role' => $role]);
        expect(OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $world[$who]['user']->id)->value('role_key'))->toBe($role);
        // a spent link opens nothing a second time
        e2eShareCall($this, 'POST', '/v1/organizations/invitations/accept', $org, "again-{$who}", ['token' => e2eShareToken("{$who}@share.test", 'invitation')])->assertStatus(410);
    }
    e2eShareRelayOutbox();
    expect(OutboxMessage::query()->where('name', 'organization.invitation.created')->where('organization_id', $org->id)->count())->toBe(3)
        ->and(AuditEvent::query()->where('action', 'organization.member.invite')->where('result', 'succeeded')->count())->toBe(3);

    // ── the sidebar the panel offers each role (the entries its endpoints would only answer with a refusal are not listed) ──
    expect(e2eShareHiddenNav($this, $world['admin']['user'], $org))->toBe([])
        ->and(e2eShareHiddenNav($this, $world['billing']['user'], $org))->toBe(['api'])
        ->and(e2eShareHiddenNav($this, $world['viewer']['user'], $org))->toBe(['api', 'order', 'topup']);
    // every role of the three sees both services (they hold service.read for the organization)
    foreach (['admin', 'viewer', 'billing'] as $who) {
        expect(e2eSharePanelServices($this, $world[$who]['user'], $org))->toContain($shop->id, $world['games']->id);
    }

    // ── the viewer reads, and only reads ──
    e2eShareActAs($this, $world['viewer']['user']);
    e2eShareCall($this, 'GET', '/v1/services', $org, 'v-list')->assertOk();
    e2eShareCall($this, 'GET', "/v1/organizations/{$org->id}", $org, 'v-org')->assertOk();
    e2eShareCall($this, 'GET', '/v1/invoices', $org, 'v-inv')->assertOk();
    e2eShareCall($this, 'GET', '/v1/wallet', $org, 'v-wallet')->assertOk();
    e2eShareCall($this, 'POST', "/v1/services/{$shop->id}/actions", $org, 'v-act', ['action' => 'php.set', 'params' => ['version' => '8.4']])->assertForbidden();
    e2eShareCall($this, 'POST', "/v1/organizations/{$org->id}/invitations", $org, 'v-inv-x', ['email' => 'x@share.test', 'role' => 'viewer'])->assertForbidden();
    e2eShareCall($this, 'GET', "/v1/services/{$shop->id}/access", $org, 'v-acc')->assertForbidden();
    e2eShareCall($this, 'POST', "/v1/services/{$shop->id}/access", $org, 'v-share', ['email' => 'agency@share.test', 'capabilities' => ['view']])->assertForbidden();
    e2eShareCall($this, 'GET', '/v1/staff/tickets', $org, 'v-staff')->assertForbidden();
    expect(Operation::query()->where('service_id', $shop->id)->count())->toBe(0);

    // ── the billing admin reads the money and the services, and manages nothing of them ──
    e2eShareActAs($this, $world['billing']['user']);
    e2eShareCall($this, 'GET', '/v1/invoices', $org, 'b-inv')->assertOk();
    e2eShareCall($this, 'POST', "/v1/services/{$shop->id}/actions", $org, 'b-act', ['action' => 'php.set', 'params' => ['version' => '8.4']])->assertForbidden();
    e2eShareCall($this, 'POST', "/v1/organizations/{$org->id}/invitations", $org, 'b-inv-x', ['email' => 'x@share.test', 'role' => 'viewer'])->assertForbidden();

    // ── the organization admin manages the services and the team, but only up to what they hold themselves ──
    e2eShareActAs($this, $world['admin']['user']);
    e2eStepUp($this, $world['admin']['password']);
    e2eShareCall($this, 'POST', "/v1/services/{$shop->id}/actions", $org, 'a-act', ['action' => 'php.set', 'params' => ['version' => '8.4']])->assertAccepted();
    expect(Operation::query()->where('service_id', $shop->id)->where('state', '!=', Operation::SUCCEEDED)->exists())->toBeTrue();
    // an administrator is withheld the credit: they cannot invite somebody who holds it, nor the owner role
    e2eShareCall($this, 'POST', "/v1/organizations/{$org->id}/invitations", $org, 'a-inv-billing', ['email' => 'nova@share.test', 'role' => 'billing_admin'])->assertForbidden();
    e2eShareCall($this, 'POST', "/v1/organizations/{$org->id}/invitations", $org, 'a-inv-owner', ['email' => 'nova@share.test', 'role' => 'owner'])->assertForbidden()->assertJsonPath('error', 'owner_role_locked');
    // … nor lift a colleague above themselves, nor demote or remove somebody above them, nor touch the owner
    e2eShareCall($this, 'PATCH', "/v1/organizations/{$org->id}/members/{$world['viewer']['user']->id}", $org, 'a-up', ['role' => 'billing_admin'])->assertForbidden();
    e2eShareCall($this, 'PATCH', "/v1/organizations/{$org->id}/members/{$world['billing']['user']->id}", $org, 'a-down', ['role' => 'viewer'])->assertForbidden();
    e2eShareCall($this, 'DELETE', "/v1/organizations/{$org->id}/members/{$world['billing']['user']->id}", $org, 'a-rm-billing')->assertForbidden();
    e2eShareCall($this, 'DELETE', "/v1/organizations/{$org->id}/members/{$owner['user']->id}", $org, 'a-rm-owner')->assertStatus(422);
    // a service is shared with somebody else, never with oneself, and never when the share would only hand out what the person is anyway
    $before = ServiceAccessGrant::query()->count();
    e2eShareCall($this, 'POST', "/v1/services/{$shop->id}/access", $org, 'a-share-self', ['email' => 'admin@share.test', 'capabilities' => ['manage']])->assertStatus(422)->assertJsonPath('error', 'cannot_share_with_self');
    e2eShareCall($this, 'POST', "/v1/services/{$shop->id}/access", $org, 'a-share-member', ['email' => 'viewer@share.test', 'capabilities' => ['delete']])->assertStatus(422)->assertJsonPath('error', 'capabilities_invalid');
    expect(ServiceAccessGrant::query()->count())->toBe($before)
        ->and(OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $world['billing']['user']->id)->value('role_key'))->toBe('billing_admin')
        ->and(OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $owner['user']->id)->value('role_key'))->toBe('owner');
    // what an administrator may: let a viewer in, then take the viewer's link back
    e2eShareCall($this, 'POST', "/v1/organizations/{$org->id}/invitations", $org, 'a-inv-viewer', ['email' => 'nova@share.test', 'role' => 'viewer'])->assertCreated();
    $pending = OrganizationInvitation::query()->where('organization_id', $org->id)->where('email', 'nova@share.test')->firstOrFail();
    e2eShareCall($this, 'DELETE', "/v1/organizations/{$org->id}/invitations/{$pending->id}", $org, 'a-cancel')->assertOk();
    e2eShareCall($this, 'POST', '/v1/organizations/invitations/accept', $org, 'a-late', ['token' => e2eShareToken('nova@share.test', 'invitation')])->assertStatus(410);

    // ── the team page shows who is in, in what role; and every write above left its audit row (all of them go through the bus) ──
    e2eShareActAs($this, $owner['user']);
    $members = collect(e2eShareCall($this, 'GET', "/v1/organizations/{$org->id}", $org, 'o-org')->assertOk()->json('data.members'))->pluck('role', 'email')->all();
    expect($members)->toMatchArray(['owner@share.test' => 'owner', 'admin@share.test' => 'org_admin', 'viewer@share.test' => 'viewer', 'billing@share.test' => 'billing_admin']);
    expect(AuditEvent::query()->where('organization_id', $org->id)->where('action', 'organization.member.invite.cancel')->where('result', 'succeeded')->exists())->toBeTrue()
        ->and(AuditEvent::query()->where('organization_id', $org->id)->where('result', 'denied')->count())->toBeGreaterThan(0);
});

/** The owner, signed in with a fresh step-up, shares `$service` with `$email`; the answer. */
function e2eShareGive(object $test, array $world, Service $service, string $email, array $capabilities, string $label): TestResponse
{
    e2eShareActAs($test, $world['owner']['user']);
    e2eStepUp($test, $world['owner']['password']);

    return e2eShareCall($test, 'POST', "/v1/services/{$service->id}/access", $world['owner']['org'], $label, ['email' => $email, 'capabilities' => $capabilities]);
}

/** The outsider accepts the mailed "a service was shared with you" link. */
function e2eShareAcceptShare(object $test, array $world, string $who): array
{
    $token = e2eShareToken("{$who}@share.test", 'service-shared');
    e2eShareActAs($test, $world[$who]['user']);

    return e2eShareCall($test, 'POST', '/v1/organizations/invitations/accept', $world['owner']['org'], "accept-share-{$who}", ['token' => $token])->assertOk()->json('data');
}

it('shares one service with an outsider: they see that service and the ticked actions, and a manager has no console', function () {
    $world = e2eShareWorld($this);
    $org = $world['owner']['org'];
    [$shop, $games] = [$world['shop'], $world['games']];
    $agency = $world['agency']['user'];

    // ── the owner shares the web hosting: nothing works until the mailed link proves the mailbox ──
    $grant = e2eShareGive($this, $world, $shop, 'Agency@Share.test', ['manage'], 'share-shop')->assertCreated()->json('grant');
    expect($grant['state'])->toBe('pending')->and($grant['capabilities'])->toBe(['view', 'manage'])->and($grant['email'])->toBe('agency@share.test');
    e2eShareActAs($this, $agency);
    e2eShareCall($this, 'GET', "/v1/services/{$shop->id}", $org, 'g-early')->assertForbidden();
    $accepted = e2eShareAcceptShare($this, $world, 'agency');
    expect($accepted)->toMatchArray(['organization_id' => $org->id, 'role' => 'guest', 'shared_services' => 1]);

    // ── they see that one service and nothing else of the organization ──
    $listed = e2eShareCall($this, 'GET', '/v1/services', $org, 'g-list')->assertOk()->json('data');
    expect(array_column($listed, 'id'))->toBe([$shop->id]);
    expect(e2eShareCall($this, 'GET', '/v1/me/shared-services', $org, 'g-mine')->assertOk()->json('data.0'))->toMatchArray(['service_id' => $shop->id, 'capabilities' => ['view', 'manage']]);
    $me = collect(e2eShareCall($this, 'GET', '/v1/me', $org, 'g-me')->assertOk()->json('data.organizations'))->firstWhere('id', $org->id);
    expect($me)->toMatchArray(['role' => 'guest', 'guest' => true])->and(array_key_exists('billing', $me))->toBeFalse();
    e2eShareCall($this, 'GET', "/v1/services/{$shop->id}", $org, 'g-one')->assertOk();
    foreach (["/v1/services/{$games->id}", '/v1/invoices', '/v1/wallet', "/v1/organizations/{$org->id}", "/v1/services/{$shop->id}/access", "/v1/organizations/{$org->id}/audit"] as $i => $path) {
        e2eShareCall($this, 'GET', $path, $org, "g-no-{$i}")->assertForbidden();
    }
    expect(e2eSharePanelServices($this, $agency, $org))->toBe([$shop->id])
        ->and(e2eShareHiddenNav($this, $agency, $org))->toBe(['api', 'audit', 'backups', 'billing', 'calendar', 'costs', 'domains', 'monitoring', 'order', 'projects', 'registrars', 'team', 'tickets', 'topup', 'windows']);

    // ── what was ticked works, the rest and the other service do not, and a guest cannot pass the service on ──
    e2eShareCall($this, 'POST', "/v1/services/{$shop->id}/actions", $org, 'g-php', ['action' => 'php.set', 'params' => ['version' => '8.4']])->assertAccepted();
    Operation::query()->where('service_id', $shop->id)->delete(); // one operation per service at a time: the panel's work is not the subject here
    e2eShareCall($this, 'POST', "/v1/services/{$shop->id}/actions", $org, 'g-restore', ['action' => 'restore', 'params' => ['backup_id' => 'bkp_x']])->assertForbidden();
    e2eShareCall($this, 'POST', "/v1/services/{$shop->id}/actions", $org, 'g-term', ['action' => 'terminate'])->assertForbidden();
    e2eShareCall($this, 'POST', "/v1/services/{$games->id}/actions", $org, 'g-games', ['action' => 'backup'])->assertForbidden();
    e2eStepUp($this, $world['agency']['password']);
    e2eShareCall($this, 'POST', "/v1/services/{$shop->id}/access", $org, 'g-pass-on', ['email' => 'stranger@share.test', 'capabilities' => ['view']])->assertForbidden();
    e2eShareCall($this, 'POST', "/v1/organizations/{$org->id}/invitations", $org, 'g-invite', ['email' => 'stranger@share.test', 'role' => 'viewer'])->assertForbidden();
    e2eShareCall($this, 'POST', '/v1/tokens', $org, 'g-token', ['name' => 'ci', 'scopes' => ['services:read']])->assertForbidden(); // a guest holds no key to the organization

    // ── somebody who is no member at all is told nothing: the service is not theirs to ask about ──
    e2eShareActAs($this, $world['stranger']['user']);
    $foreign = $world['stranger']['org'];
    e2eShareCall($this, 'GET', "/v1/services/{$shop->id}", $foreign, 's-own-org')->assertForbidden(); // (answered with a refusal, not a 404: the id is a ULID nobody guesses)
    e2eShareCall($this, 'GET', '/v1/services/srv_01hzzzzzzzzzzzzzzzzzzzzzzz', $foreign, 's-nothing')->assertNotFound();
    e2eShareCall($this, 'GET', "/v1/services/{$shop->id}", $org, 's-their-org')->assertForbidden();
    e2eShareCall($this, 'POST', "/v1/services/{$shop->id}/actions", $foreign, 's-act', ['action' => 'php.set', 'params' => ['version' => '8.4']])->assertForbidden();

    // ── one capability on one service: "manage" is no console; "console" is ──
    $given = e2eShareGive($this, $world, $games, 'gamer@share.test', ['manage'], 'share-games')->assertCreated()->json('grant');
    $gamer = $world['gamer']['user'];
    expect(e2eShareAcceptShare($this, $world, 'gamer'))->toMatchArray(['role' => 'guest', 'shared_services' => 1]);
    e2eShareCall($this, 'POST', "/v1/services/{$games->id}/console-token", $org, 'm-console')->assertForbidden();
    e2eShareCall($this, 'POST', "/v1/services/{$games->id}/actions", $org, 'm-send', ['action' => 'command.send', 'params' => ['command' => 'say hi']])->assertForbidden();
    e2eShareCall($this, 'GET', "/v1/services/{$shop->id}", $org, 'm-other')->assertForbidden(); // the share of the game server says nothing of the web hosting

    // sharing again changes what the person may do instead of adding a record: now with the console
    e2eShareGive($this, $world, $games, 'gamer@share.test', ['console'], 'share-games-2')->assertCreated();
    expect(ServiceAccessGrant::query()->where('service_id', $games->id)->count())->toBe(1);
    e2eShareActAs($this, $gamer);
    $ticket = e2eShareCall($this, 'POST', "/v1/services/{$games->id}/console-token", $org, 'c-console')->assertOk();
    $open = (string) $ticket->json('token');
    expect($open)->toMatch('/^con_[0-9a-z]{26}$/')->and($ticket->json('socket'))->toBe('wss://relay.onhost.test/ws/'.$open);
    $waiting = (string) e2eShareCall($this, 'POST', "/v1/services/{$games->id}/console-token", $org, 'c-console-2')->assertOk()->json('token'); // a second tab in the same second: a ticket of its own (E8 fix)
    expect($waiting)->toMatch('/^con_[0-9a-z]{26}$/')->not->toBe($open);
    e2eShareRelay($this, "/console/ws/{$open}")->assertOk()->assertJsonPath('data.kind', 'wings_ws');
    e2eShareRelay($this, "/console/ws/{$open}/alive")->assertOk()->assertJsonPath('data.alive', true);

    // ── the owner takes the share of the game server back: the console, the open ticket and the membership go at once ──
    e2eShareActAs($this, $world['owner']['user']);
    e2eStepUp($this, $world['owner']['password']);
    $grantId = ServiceAccessGrant::query()->where('service_id', $games->id)->value('id');
    expect($grantId)->toBe($given['id']);
    e2eShareCall($this, 'DELETE', "/v1/services/{$games->id}/access/{$grantId}", $org, 'revoke-games')->assertOk()->assertJsonPath('grant.state', 'revoked');
    e2eShareRelay($this, "/console/ws/{$open}/alive")->assertStatus(410);
    e2eShareRelay($this, "/console/ws/{$waiting}")->assertStatus(410);
    e2eShareActAs($this, $gamer);
    e2eShareCall($this, 'GET', "/v1/services/{$games->id}", $org, 'after-revoke')->assertForbidden();
    e2eShareCall($this, 'POST', "/v1/services/{$games->id}/console-token", $org, 'after-revoke-console')->assertForbidden();
    expect(OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $gamer->id)->exists())->toBeFalse()
        ->and(OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $agency->id)->exists())->toBeTrue(); // the other guest keeps theirs

    // ── what is left behind: audit rows for every write, the events, and the owner's notification ──
    e2eShareRelayOutbox();
    // every write went through the bus (its own row names the permission the person held) and the service's own row says what changed
    expect(AuditEvent::query()->where('action', 'service.access.share')->where('result', 'succeeded')->count())->toBe(3)
        ->and(AuditEvent::query()->where('action', 'service.access.grant')->where('result', 'succeeded')->count())->toBe(3)
        ->and(AuditEvent::query()->where('action', 'service.access.revoke')->where('result', 'succeeded')->count())->toBe(2)
        ->and(AuditEvent::query()->where('action', 'service.access.revoke')->get()->filter(fn (AuditEvent $e) => (((array) $e->detail)['permission'] ?? null) === 'organization.members.manage')->count())->toBe(1)
        ->and(OutboxMessage::query()->where('name', 'service.access.granted')->where('organization_id', $org->id)->count())->toBe(3)
        ->and(OutboxMessage::query()->where('name', 'service.access.revoked')->where('organization_id', $org->id)->count())->toBe(1)
        ->and(Notification::query()->where('organization_id', $org->id)->where('title', 'like', 'Sdílení služby ukončeno%')->exists())->toBeTrue();
});

/** A request carrying only an API token: no session, no cookie, no signed-in person of the test client. */
function e2eShareBearer(object $test, string $token, Organization $org, string $path): TestResponse
{
    $test->flushSession();
    app('auth')->forgetGuards();
    (function () {
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        $this->withCredentials = false;
    })->call($test);

    return $test->flushHeaders()->withToken($token)->getJson($path, ['X-Organization' => $org->id]);
}

it('ends every side door when a member is removed: session, API token, open console and what they sent in', function () {
    $world = e2eShareWorld($this);
    $org = $world['owner']['org'];
    [$shop, $games] = [$world['shop'], $world['games']];
    $admin = $world['admin'];
    e2eShareJoin($this, $world, 'admin', 'org_admin');

    // ── the administrator has the doors a member has: a key for scripts, an open console, a share and an invitation they sent ──
    e2eShareActAs($this, $admin['user']);
    e2eStepUp($this, $admin['password']);
    $created = e2eShareCall($this, 'POST', '/v1/tokens', $org, 'a-token', ['name' => 'ci', 'scopes' => ['services:read']])->assertCreated();
    $token = (string) $created->json('token');
    e2eShareBearer($this, $token, $org, '/v1/services')->assertOk();
    e2eShareActAs($this, $admin['user']);
    $open = (string) e2eShareCall($this, 'POST', "/v1/services/{$games->id}/console-token", $org, 'a-console')->assertOk()->json('token');
    $waiting = (string) e2eShareCall($this, 'POST', "/v1/services/{$games->id}/console-token", $org, 'a-console-2')->assertOk()->json('token');
    e2eShareRelay($this, "/console/ws/{$open}")->assertOk();
    e2eShareRelay($this, "/console/ws/{$open}/alive")->assertOk();
    e2eShareActAs($this, $admin['user']);
    e2eStepUp($this, $admin['password']);
    e2eShareCall($this, 'POST', "/v1/services/{$shop->id}/access", $org, 'a-share', ['email' => 'gamer@share.test', 'capabilities' => ['view']])->assertCreated();
    e2eShareCall($this, 'POST', "/v1/organizations/{$org->id}/invitations", $org, 'a-invite', ['email' => 'stranger@share.test', 'role' => 'viewer'])->assertCreated();
    $shareLink = e2eShareToken('gamer@share.test', 'service-shared');
    $inviteLink = e2eShareToken('stranger@share.test', 'invitation');

    // ── the owner removes the administrator ──
    e2eShareActAs($this, $world['owner']['user']);
    e2eStepUp($this, $world['owner']['password']);
    e2eShareCall($this, 'DELETE', "/v1/organizations/{$org->id}/members/{$admin['user']->id}", $org, 'remove-admin')->assertOk();
    e2eShareRelayOutbox();
    expect(OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $admin['user']->id)->exists())->toBeFalse();

    // the browser session, the API key and the open console end at once; the ticket that was still waiting opens nothing
    e2eShareActAs($this, $admin['user']);
    e2eShareCall($this, 'GET', '/v1/services', $org, 'gone-list')->assertForbidden();
    e2eShareCall($this, 'GET', "/v1/organizations/{$org->id}", $org, 'gone-org')->assertForbidden();
    e2eShareCall($this, 'POST', "/v1/services/{$games->id}/console-token", $org, 'gone-console')->assertForbidden();
    expect(collect(e2eShareCall($this, 'GET', '/v1/me', $admin['org'], 'gone-me')->assertOk()->json('data.organizations'))->pluck('id')->all())->not->toContain($org->id);
    e2eShareBearer($this, $token, $org, '/v1/services')->assertUnauthorized();
    e2eShareRelay($this, "/console/ws/{$open}/alive")->assertStatus(410);
    e2eShareRelay($this, "/console/ws/{$waiting}")->assertStatus(410);

    // what they sent in goes with them: the share they offered and the invitation they mailed do not open the door any more
    e2eShareActAs($this, $world['gamer']['user']);
    e2eShareCall($this, 'POST', '/v1/organizations/invitations/accept', $org, 'late-share', ['token' => $shareLink])->assertStatus(410)->assertJsonPath('error', 'invitation_invalid');
    expect(ServiceAccessGrant::query()->where('service_id', $shop->id)->where('state', 'active')->exists())->toBeFalse();
    e2eShareActAs($this, $world['stranger']['user']);
    e2eShareCall($this, 'POST', '/v1/organizations/invitations/accept', $org, 'late-invite', ['token' => $inviteLink])->assertStatus(410)->assertJsonPath('error', 'invitation_invalid');
    expect(OrganizationMembership::query()->where('organization_id', $org->id)->whereIn('user_id', [$world['gamer']['user']->id, $world['stranger']['user']->id])->exists())->toBeFalse();
    e2eShareActAs($this, $world['gamer']['user']);
    e2eShareCall($this, 'GET', "/v1/services/{$shop->id}", $org, 'late-share-read')->assertForbidden();

    // ── the owner can give back what the removal took, and the key stays dead ──
    e2eShareActAs($this, $world['owner']['user']);
    e2eStepUp($this, $world['owner']['password']);
    $snapshot = collect(e2eShareCall($this, 'GET', "/v1/organizations/{$org->id}/access-snapshots", $org, 'snapshots')->assertOk()->json('data'))->firstWhere('user_id', $admin['user']->id);
    expect($snapshot)->not->toBeNull();
    e2eShareCall($this, 'POST', "/v1/organizations/{$org->id}/access-snapshots/restore", $org, 'restore-admin', ['snapshot_id' => $snapshot['id']])->assertOk();
    expect(OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $admin['user']->id)->value('role_key'))->toBe('org_admin');
    e2eShareBearer($this, $token, $org, '/v1/services')->assertUnauthorized();
    e2eShareRelay($this, "/console/ws/{$open}/alive")->assertStatus(410);

    // ── the trail: the removal on the bus, the events, the audit row of the person who did it ──
    e2eShareRelayOutbox();
    expect(OutboxMessage::query()->where('name', 'organization.member.removed')->where('organization_id', $org->id)->count())->toBe(1)
        ->and(AuditEvent::query()->where('organization_id', $org->id)->where('action', 'like', 'organization.member.remove%')->where('result', 'succeeded')->exists())->toBeTrue()
        ->and(AuditEvent::query()->where('organization_id', $org->id)->where('action', 'like', 'api_token%')->exists())->toBeTrue();
});

it('moves the ownership in two steps: an offer, the heir saying yes with a fresh proof, both sides told', function () {
    $world = e2eShareWorld($this);
    $org = $world['owner']['org'];
    [$owner, $heir] = [$world['owner'], $world['admin']];
    e2eShareJoin($this, $world, 'admin', 'org_admin');
    e2eShareJoin($this, $world, 'viewer', 'viewer');

    // ── the owner offers; nothing moves until the heir answers ──
    e2eShareActAs($this, $owner['user']);
    e2eStepUp($this, $owner['password']);
    e2eShareCall($this, 'POST', "/v1/organizations/{$org->id}/ownership-transfer", $org, 'offer', ['user_id' => $world['stranger']['user']->id])->assertStatus(404); // somebody who is no member cannot inherit
    e2eShareCall($this, 'POST', "/v1/organizations/{$org->id}/ownership-transfer", $org, 'offer', ['user_id' => $heir['user']->id])->assertCreated()->assertJsonPath('transfer.state', 'pending');
    e2eShareRelayOutbox();
    expect($org->fresh()->owner_user_id)->toBe($owner['user']->id)
        ->and(MailOutbox::query()->where('template_key', 'ownership-offered')->pluck('to')->map(fn ($to) => mb_strtolower((string) $to))->all())->toBe(['admin@share.test'])
        ->and(OutboxMessage::query()->where('name', 'organization.ownership.offered')->where('organization_id', $org->id)->count())->toBe(1);
    // everybody in the organization can see the offer; only the owner can make or withdraw one
    expect(e2eShareCall($this, 'GET', "/v1/organizations/{$org->id}", $org, 'o-see')->json('data.ownership_transfer.state'))->toBe('pending');
    e2eShareActAs($this, $world['viewer']['user']);
    expect(e2eShareCall($this, 'GET', "/v1/organizations/{$org->id}", $org, 'v-see')->json('data.ownership_transfer.state'))->toBe('pending');
    e2eShareCall($this, 'DELETE', "/v1/organizations/{$org->id}/ownership-transfer", $org, 'v-cancel')->assertForbidden();
    e2eStepUp($this, $world['viewer']['password']);
    e2eShareCall($this, 'POST', "/v1/organizations/{$org->id}/ownership-transfer/accept", $org, 'v-accept')->assertForbidden()->assertJsonPath('error', 'owner_transfer_heir_only'); // the offer is not theirs

    // ── the heir needs a fresh proof of identity, then it moves ──
    e2eShareActAs($this, $heir['user']);
    e2eShareCall($this, 'POST', "/v1/organizations/{$org->id}/ownership-transfer/accept", $org, 'h-accept-1')->assertForbidden()->assertJsonPath('error', 'step_up_required');
    expect($org->fresh()->owner_user_id)->toBe($owner['user']->id);
    e2eStepUp($this, $heir['password']);
    e2eShareCall($this, 'POST', "/v1/organizations/{$org->id}/ownership-transfer/accept", $org, 'h-accept-2')->assertOk()->assertJsonPath('transfer.state', 'accepted');
    e2eShareRelayOutbox();
    expect($org->fresh()->owner_user_id)->toBe($heir['user']->id)
        ->and(OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $heir['user']->id)->value('role_key'))->toBe('owner')
        ->and(OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $owner['user']->id)->value('role_key'))->toBe('org_admin')
        ->and(MailOutbox::query()->where('template_key', 'ownership-transferred')->pluck('to')->map(fn ($to) => mb_strtolower((string) $to))->all())->toBe(['owner@share.test'])
        ->and(OutboxMessage::query()->where('name', 'organization.ownership.accepted')->where('organization_id', $org->id)->count())->toBe(1)
        ->and(Notification::query()->where('organization_id', $org->id)->exists())->toBeTrue();

    // ── the previous owner is an administrator now: no closing, no offers, no touching the new owner ──
    e2eShareActAs($this, $owner['user']);
    e2eStepUp($this, $owner['password']);
    e2eShareCall($this, 'POST', "/v1/organizations/{$org->id}/ownership-transfer", $org, 'old-offer', ['user_id' => $world['viewer']['user']->id])->assertForbidden();
    e2eShareCall($this, 'PATCH', "/v1/organizations/{$org->id}/members/{$heir['user']->id}", $org, 'old-demote', ['role' => 'viewer'])->assertForbidden();
    e2eShareCall($this, 'DELETE', "/v1/organizations/{$org->id}/members/{$heir['user']->id}", $org, 'old-remove')->assertStatus(422);
    expect($org->fresh()->owner_user_id)->toBe($heir['user']->id);

    // ── the new owner offers it back; the previous one says no, and the organization stays as it is ──
    e2eShareActAs($this, $heir['user']);
    e2eStepUp($this, $heir['password']);
    e2eShareCall($this, 'POST', "/v1/organizations/{$org->id}/ownership-transfer", $org, 'back-offer', ['user_id' => $owner['user']->id])->assertCreated();
    e2eShareActAs($this, $owner['user']);
    e2eShareCall($this, 'POST', "/v1/organizations/{$org->id}/ownership-transfer/decline", $org, 'back-decline')->assertOk()->assertJsonPath('transfer.state', 'declined');
    e2eShareRelayOutbox();
    expect($org->fresh()->owner_user_id)->toBe($heir['user']->id)
        ->and(OutboxMessage::query()->where('name', 'organization.ownership.declined')->where('organization_id', $org->id)->count())->toBe(1);

    // ── every step is on the bus and in the audit trail ──
    $actions = AuditEvent::query()->where('organization_id', $org->id)->where('action', 'like', 'organization.ownership.%')->where('result', 'succeeded')->pluck('action')->unique()->sort()->values()->all();
    expect($actions)->toContain('organization.ownership.offer', 'organization.ownership.accept', 'organization.ownership.decline');
});
