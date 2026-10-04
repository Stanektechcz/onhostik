<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Commands\MfaResetCommandHandler;
use Onhost\Domain\Identity\Models\StepUpGrant;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Organizations\Commands\OrganizationCommand;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;

/*
 * TASK-0044 — permission program D20 / S1-08: access that ends ends what is already open. A console ticket was a bearer value for
 * its two minutes and an open console was never asked about again for two hours; an MFA reset left every web session, step-up
 * and token of the person in place.
 */

const SEWA_RELAY_KEY = 'sewa-relay-key';

function sewaMember(Organization $org, string $role): User
{
    $user = User::factory()->create();
    app(OrganizationService::class)->attachMember($org, $user, $role, CommandContext::system('sewa fixture'), true);

    return $user;
}

function sewaService(Organization $org): Service
{
    return Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'compute', 'name' => 'VPS', 'hostname' => 'sewa-'.Str::lower(Str::random(8)).'.cz',
        'state' => ServiceStateMachine::ACTIVE, 'desired_spec' => [], 'entitlements' => [], 'tags' => [], 'health' => []]);
}

/** A console ticket as ServiceService::consoleAccess leaves it in the cache: the adapter's descriptor plus who it was issued to. */
function sewaTicket(Service $service, User $person): string
{
    $token = 'con_'.strtolower((string) Str::ulid());
    Cache::put("onhost:console:{$token}", ['kind' => 'pve_vnc', 'upstream' => 'https://pve.lab/x', 'port' => 5900, 'vncticket' => 'PVEVNC:secret', 'service_id' => $service->id,
        'organization_id' => $service->organization_id, 'issued_to' => $person->id, 'issued_at' => now()->toIso8601String()], 120);

    return $token;
}

function sewaRelay(string $path)
{
    return test()->flushHeaders()->withHeader('X-Relay-Key', SEWA_RELAY_KEY)->getJson($path);
}

beforeEach(function () {
    Http::fake();
    config(['onhost.console.relay_key' => SEWA_RELAY_KEY]);
});

it('closes the open console of a removed member at the next alive check, and opens no ticket issued before the removal', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $developer = sewaMember($org, 'developer');
    $service = sewaService($org);
    $open = sewaTicket($service, $developer);
    $waiting = sewaTicket($service, $developer);

    sewaRelay("/console/ws/{$open}")->assertOk()->assertJsonPath('data.alive_every', 15);
    sewaRelay("/console/ws/{$open}/alive")->assertOk()->assertJsonPath('data.alive', true);

    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    app(CommandBus::class)->dispatch(new OrganizationCommand($org->id, 'sewa-'.Str::ulid(), ['op' => 'remove_member', 'user_id' => $developer->id]), new CommandContext('user', $owner->id, $org->id, null, '127.0.0.1', 'pest', 'sewa-owner'));

    sewaRelay("/console/ws/{$open}/alive")->assertStatus(410)->assertJsonPath('error', 'console_session_ended');
    sewaRelay("/console/ws/{$open}/alive")->assertStatus(410); // and stays closed: the live record is gone
    sewaRelay("/console/ws/{$waiting}")->assertStatus(410)->assertJsonPath('error', 'console_session_ended');
    expect(AuditEvent::query()->where('action', 'service.console.relay')->where('result', 'denied')->exists())->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'service.console.closed')->exists())->toBeTrue();

    // the relay's own key is still asked first
    $this->flushHeaders()->getJson("/console/ws/{$open}/alive")->assertStatus(401);
    config(['onhost.console.alive_check_seconds' => 1]);
    expect(Onhost\Domain\Services\Console\ConsoleSessions::aliveSeconds())->toBe(5); // never below 5
});

it('ends every web session, step-up, token and console of a person whose MFA support reset, and lets them start again', function () {
    config(['session.driver' => 'database']);
    [, $org] = $this->customerWithOrganization();
    $developer = sewaMember($org, 'developer');
    $service = sewaService($org);
    DB::table('sessions')->insert([
        ['id' => 'sewa-'.Str::random(20), 'user_id' => $developer->id, 'ip_address' => '198.51.100.7', 'user_agent' => 'pest', 'payload' => '', 'last_activity' => now()->timestamp],
        ['id' => 'sewa-'.Str::random(20), 'user_id' => $developer->id, 'ip_address' => '198.51.100.8', 'user_agent' => 'pest', 'payload' => '', 'last_activity' => now()->timestamp],
    ]);
    $bystander = User::factory()->create();
    DB::table('sessions')->insert(['id' => 'sewa-'.Str::random(20), 'user_id' => $bystander->id, 'ip_address' => '198.51.100.9', 'user_agent' => 'pest', 'payload' => '', 'last_activity' => now()->timestamp]);
    $developer->forceFill(['remember_token' => 'remembered-browser'])->save();
    app(StepUpService::class)->grant($developer, 'totp', 'portal', '127.0.0.1');
    $token = $developer->createToken('ci', ['services:read', 'org:'.$org->id])->accessToken;
    $open = sewaTicket($service, $developer);
    sewaRelay("/console/ws/{$open}")->assertOk();

    $this->travel(1)->seconds();
    app(MfaResetCommandHandler::class)->reset($developer, CommandContext::system('sewa: support reset'), 'Ztracený telefon, ověřeno dokladem');

    expect(DB::table('sessions')->where('user_id', $developer->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $bystander->id)->count())->toBe(1)
        ->and($developer->fresh()->remember_token)->not->toBe('remembered-browser')
        ->and(StepUpGrant::query()->where('user_id', $developer->id)->whereNull('revoked_at')->exists())->toBeFalse()
        ->and($token->fresh()->revoked_at)->not->toBeNull()
        ->and(AuditEvent::query()->where('action', 'identity.sessions.end')->where('resource_id', $developer->id)->exists())->toBeTrue();
    sewaRelay("/console/ws/{$open}/alive")->assertStatus(410)->assertJsonPath('reason', 'session_ended');

    // the person signs in again: a console they open after the reset works
    $this->travel(1)->seconds();
    $fresh = sewaTicket($service, $developer);
    sewaRelay("/console/ws/{$fresh}")->assertOk();
    sewaRelay("/console/ws/{$fresh}/alive")->assertOk();
});

it('opens no console for a disabled account or a service that is gone, and keeps a ticket with no person working as before', function () {
    [, $org] = $this->customerWithOrganization();
    $developer = sewaMember($org, 'developer');
    $service = sewaService($org);

    $disabled = sewaTicket($service, $developer);
    $developer->forceFill(['state' => 'disabled'])->save();
    sewaRelay("/console/ws/{$disabled}")->assertStatus(410);

    $system = 'con_'.strtolower((string) Str::ulid());
    Cache::put("onhost:console:{$system}", ['kind' => 'pve_vnc', 'upstream' => 'https://pve.lab/x', 'port' => 5900, 'vncticket' => 'PVEVNC:s', 'service_id' => $service->id], 120);
    sewaRelay("/console/ws/{$system}")->assertOk();
    $service->delete();
    sewaRelay("/console/ws/{$system}/alive")->assertStatus(410);
});
