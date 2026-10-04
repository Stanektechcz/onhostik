<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Models\ServiceAccount;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\SessionKill;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Organizations\OwnerRecoveries;
use Onhost\Domain\Services\Console\ConsoleSessions;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxMessage;

/*
 * TASK-0067 — follow-ups of the PR #53 (TASK-0044) review: what was ended stays ended. The kill marks of web sessions and consoles
 * lived only in the cache (a flush, an eviction or a Redis restart revived every session and console they had ended); a console
 * whose live record expired closed without an audit row, and a close named no service; a service account's console was never
 * judged; the relay endpoints took unlimited guesses; the owner stopping an MFA reset of their own account alerted nobody.
 */

const ESSE_RELAY_KEY = 'esse-relay-key';

function esseMember(Organization $org, string $role): User
{
    $user = User::factory()->create();
    app(OrganizationService::class)->attachMember($org, $user, $role, CommandContext::system('esse fixture'), true);

    return $user;
}

function esseService(Organization $org): Service
{
    return Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'compute', 'name' => 'VPS', 'hostname' => 'esse-'.Str::lower(Str::random(8)).'.cz',
        'state' => ServiceStateMachine::ACTIVE, 'desired_spec' => [], 'entitlements' => [], 'tags' => [], 'health' => []]);
}

/** @param array<string,mixed> $issuer who the ticket is for (`issued_to` or `issued_to_account`) */
function esseTicket(Service $service, array $issuer, ?string $issuedAt = null): string
{
    $token = 'con_'.strtolower((string) Str::ulid());
    Cache::put("onhost:console:{$token}", ['kind' => 'pve_vnc', 'upstream' => 'https://pve.lab/x', 'port' => 5900, 'vncticket' => 'PVEVNC:secret', 'service_id' => $service->id,
        'organization_id' => $service->organization_id, 'issued_at' => $issuedAt ?? now()->toIso8601String()] + $issuer, 120);

    return $token;
}

function esseRelay(string $path, string $key = ESSE_RELAY_KEY)
{
    return test()->flushHeaders()->withHeader('X-Relay-Key', $key)->getJson($path);
}

/** A web request of `$person` with a session that signed in now (the Login listener stamps it). */
function esseSignIn(User $person): void
{
    $request = Request::create('/panel');
    $request->setLaravelSession(app('session.store'));
    app()->instance('request', $request);
    Auth::guard('web')->logout();
    event(new Login('web', $person, false));
}

beforeEach(function () {
    Http::fake();
    config(['onhost.console.relay_key' => ESSE_RELAY_KEY]);
});

// ── 1 (MEDIUM): a kill outlives the cache ─────────────────────────────────────────────────────────────────────────────────

it('keeps a web session ended by SessionKill ended after the cache is flushed, and a later sign-in working', function () {
    $person = User::factory()->create();
    esseSignIn($person);

    $this->travel(1)->seconds();
    app(SessionKill::class)->end($person, 'mfa_reset', null, CommandContext::system('esse'));
    expect(DB::table('session_ends')->where('user_id', $person->id)->where('kind', 'web')->exists())->toBeTrue();

    Cache::flush(); // a Redis restart, an eviction, `cache:clear` on deploy
    Auth::guard('web')->setUser($person); // the next request loads the person from the session (SessionGuard fires Authenticated)
    expect(Auth::guard('web')->user())->toBeNull();

    $this->travel(1)->seconds();
    esseSignIn($person);
    Cache::flush();
    Auth::guard('web')->setUser($person);
    expect(Auth::guard('web')->user()?->id)->toBe($person->id);
});

it('refuses a console ticket issued before the kill after the cache is flushed, in that organization and everywhere', function () {
    [, $org] = $this->customerWithOrganization();
    [, $other] = $this->customerWithOrganization();
    $developer = esseMember($org, 'developer');
    app(OrganizationService::class)->attachMember($other, $developer, 'developer', CommandContext::system('esse fixture'), true);
    $service = esseService($org);
    $elsewhere = esseService($other);
    $before = now()->toIso8601String();

    $this->travel(1)->seconds();
    app(ConsoleSessions::class)->endFor($developer->id, $org->id);
    Cache::flush();

    // a ticket the flush did not take (or one stored elsewhere) and issued before the kill opens nothing
    esseRelay('/console/ws/'.esseTicket($service, ['issued_to' => $developer->id], $before))->assertStatus(410)->assertJsonPath('reason', 'session_ended');
    // the kill was for that organization only
    esseRelay('/console/ws/'.esseTicket($elsewhere, ['issued_to' => $developer->id], $before))->assertOk();
    // and a ticket issued after the kill works
    $this->travel(1)->seconds();
    esseRelay('/console/ws/'.esseTicket($service, ['issued_to' => $developer->id]))->assertOk();

    // an everywhere kill reaches the other organization too, flush or not
    $this->travel(1)->seconds();
    $stale = now()->toIso8601String();
    $this->travel(1)->seconds();
    app(ConsoleSessions::class)->endFor($developer->id);
    Cache::flush();
    esseRelay('/console/ws/'.esseTicket($elsewhere, ['issued_to' => $developer->id], $stale))->assertStatus(410)->assertJsonPath('reason', 'session_ended');
    expect(DB::table('session_ends')->where('user_id', $developer->id)->where('kind', 'console')->count())->toBe(2);
});

// ── 3 (MEDIUM): every close is audited, with its service ──────────────────────────────────────────────────────────────────

it('audits the close of a console whose live record expired, and names the service of every close', function () {
    [, $org] = $this->customerWithOrganization();
    $developer = esseMember($org, 'developer');
    $service = esseService($org);

    $expiring = esseTicket($service, ['issued_to' => $developer->id]);
    esseRelay("/console/ws/{$expiring}")->assertOk();
    $this->travel(2)->hours();
    $this->travel(6)->minutes(); // past the live record
    esseRelay("/console/ws/{$expiring}/alive")->assertStatus(410)->assertJsonPath('reason', 'session_unknown');
    $unknown = AuditEvent::query()->where('action', 'service.console.closed')->get()->first(fn (AuditEvent $e) => ($e->detail['reason'] ?? null) === 'session_unknown');
    expect($unknown)->not->toBeNull();

    $ended = esseTicket($service, ['issued_to' => $developer->id]);
    esseRelay("/console/ws/{$ended}")->assertOk();
    $developer->forceFill(['state' => 'disabled'])->save();
    esseRelay("/console/ws/{$ended}/alive")->assertStatus(410)->assertJsonPath('reason', 'account_inactive');
    $closed = AuditEvent::query()->where('action', 'service.console.closed')->get()->first(fn (AuditEvent $e) => ($e->detail['reason'] ?? null) === 'account_inactive');
    expect($closed?->resource_id)->toBe($service->id)->and($closed?->detail['service_id'] ?? null)->toBe($service->id);
});

// ── 4 (LOW): the owner stopping an MFA reset of their own account tells staff at once ────────────────────────────────────

it('alerts staff on the first stop when the owner being recovered cancels an MFA reset, and not when somebody else cancels', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $iam = $this->steppedUpStaff('iam_admin');
    $staff = new CommandContext('user', $iam->id, null, null, '127.0.0.1', 'pest', 'esse-staff');
    $ticket = fn () => (string) Ticket::query()->create(['number' => 'TK-2026-'.random_int(10000, 99999), 'organization_id' => $org->id, 'email' => 'owner@example.test', 'subject' => 'Ztracený přístup'])->number;
    $alerts = fn () => OutboxMessage::query()->where('name', 'organization.owner_recovery.cancels_repeated')->where('organization_id', $org->id)->count();

    app(OwnerRecoveries::class)->open($org, 'mfa_reset', null, 'Vlastník ztratil telefon, ověřeno dokladem', $ticket(), $staff);
    app(OwnerRecoveries::class)->cancel($org, new CommandContext('user', $iam->id, null, null, '127.0.0.1', 'pest', 'esse-staff'), 'Zákazník se ozval, přístup má');
    expect($alerts())->toBe(0); // one stop by support is no pattern yet

    $this->travel(31)->days(); // outside the window of repeats
    app(OwnerRecoveries::class)->open($org, 'mfa_reset', null, 'Vlastník ztratil telefon, ověřeno dokladem', $ticket(), $staff);
    app(OwnerRecoveries::class)->cancel($org, new CommandContext('user', $owner->id, $org->id, null, '127.0.0.1', 'pest', 'esse-owner'));
    expect($alerts())->toBe(1);
    $payload = OutboxMessage::query()->where('name', 'organization.owner_recovery.cancels_repeated')->where('organization_id', $org->id)->firstOrFail()->payload;
    expect($payload['count'] ?? null)->toBe(1);
});

// ── 5 (LOW): a service account's console is judged by the account ────────────────────────────────────────────────────────

it('refuses and closes the console of a service account that was disabled or deleted, and of another organization', function () {
    [, $org] = $this->customerWithOrganization();
    [, $other] = $this->customerWithOrganization();
    $service = esseService($org);
    $account = ServiceAccount::query()->create(['organization_id' => $org->id, 'name' => 'ci', 'state' => 'active']);
    $foreign = ServiceAccount::query()->create(['organization_id' => $other->id, 'name' => 'ci', 'state' => 'active']);

    $open = esseTicket($service, ['issued_to_account' => $account->id]);
    esseRelay("/console/ws/{$open}")->assertOk();
    esseRelay("/console/ws/{$open}/alive")->assertOk();
    $account->forceFill(['state' => 'disabled'])->save();
    esseRelay("/console/ws/{$open}/alive")->assertStatus(410)->assertJsonPath('reason', 'account_inactive');
    esseRelay('/console/ws/'.esseTicket($service, ['issued_to_account' => $account->id]))->assertStatus(410);

    esseRelay('/console/ws/'.esseTicket($service, ['issued_to_account' => $foreign->id]))->assertStatus(410)->assertJsonPath('reason', 'access_ended');
    $account->forceFill(['state' => 'active'])->save();
    $account->delete();
    esseRelay('/console/ws/'.esseTicket($service, ['issued_to_account' => $account->id]))->assertStatus(410)->assertJsonPath('reason', 'account_inactive');
});

it('records the service account a console ticket was issued to', function () {
    [, $org] = $this->customerWithOrganization();
    $service = esseService($org);
    $account = ServiceAccount::query()->create(['organization_id' => $org->id, 'name' => 'ci', 'state' => 'active']);
    $token = 'con_'.strtolower((string) Str::ulid());
    Cache::put("onhost:console:{$token}", ['kind' => 'pve_vnc', 'service_id' => $service->id], 120);

    $record = new ReflectionMethod(ServiceService::class, 'recordConsoleIssuer');
    $record->invoke(app(ServiceService::class), $token, $service, new CommandContext('service_account', $account->id, $org->id, null, '127.0.0.1', 'pest', 'esse-sa'));

    expect(Cache::get("onhost:console:{$token}"))->toMatchArray(['issued_to' => null, 'issued_to_account' => $account->id, 'organization_id' => $org->id]);
});

// ── 6 (LOW): every web sign-in stamps the session ────────────────────────────────────────────────────────────────────────

it('stamps the session on every web sign-in path, so a kill ends it and a sign-in after it does not', function () {
    $person = User::factory()->create(['email' => 'esse-login@example.cz', 'password' => 'Correct-Horse-Battery-9-Staple']);
    $browser = ['Referer' => 'http://localhost'];

    $this->withHeaders($browser)->postJson('/v1/auth/login', ['email' => 'esse-login@example.cz', 'password' => 'Correct-Horse-Battery-9-Staple'])->assertOk();
    expect(app('session.store')->get('onhost_signed_in_at'))->toBeString();
    Auth::forgetGuards(); // a new request: the guard loads the person from the session again
    $this->withHeaders($browser)->getJson('/v1/me')->assertOk();

    $this->travel(1)->seconds();
    app(SessionKill::class)->end($person, 'mfa_reset', null, CommandContext::system('esse'));
    Cache::flush();
    Auth::forgetGuards();
    $this->withHeaders($browser)->getJson('/v1/me')->assertUnauthorized();

    $this->travel(1)->seconds();
    $this->withHeaders($browser)->postJson('/v1/auth/login', ['email' => 'esse-login@example.cz', 'password' => 'Correct-Horse-Battery-9-Staple'])->assertOk();
    Auth::forgetGuards();
    $this->withHeaders($browser)->getJson('/v1/me')->assertOk();
});

// ── 7 (LOW): the relay endpoints take no unlimited guesses ───────────────────────────────────────────────────────────────

it('rate limits wrong relay keys per address and the alive check per console', function () {
    [, $org] = $this->customerWithOrganization();
    $developer = esseMember($org, 'developer');
    $service = esseService($org);
    $open = esseTicket($service, ['issued_to' => $developer->id]);
    esseRelay("/console/ws/{$open}")->assertOk();

    // the relay asks every 15 s (never more often than every 5 s): far below the limit
    for ($i = 0; $i < 12; $i++) {
        esseRelay("/console/ws/{$open}/alive")->assertOk();
    }
    $statuses = [];
    for ($i = 0; $i < 30; $i++) {
        $statuses[] = esseRelay("/console/ws/{$open}/alive")->status();
    }
    expect($statuses)->toContain(429);

    // somebody guessing the relay key is stopped, and stays stopped for a while even with a right guess
    $guess = 'con_'.strtolower((string) Str::ulid());
    $wrong = [];
    for ($i = 0; $i < 12; $i++) {
        $wrong[] = esseRelay("/console/ws/{$guess}", 'guess-'.$i)->status();
    }
    expect($wrong)->toContain(401)->toContain(429)
        ->and(esseRelay('/console/ws/'.esseTicket($service, ['issued_to' => $developer->id]))->status())->toBe(429);
    $this->travel(2)->minutes();
    esseRelay('/console/ws/'.esseTicket($service, ['issued_to' => $developer->id]))->assertOk();
});
