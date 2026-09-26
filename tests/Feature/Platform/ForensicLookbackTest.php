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

    expect(collect($statements)->reject(fn (string $sql) => preg_match('/^\s*select\b/i', $sql) === 1)->all())->toBe([]);
    expect(collect(['audit_events', 'operations', 'organization_memberships', 'discord_links', 'outbox_messages', 'provider_calls'])->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()]))->toEqual($before);
    Http::assertNothingSent();
    expect($code)->toBe(0)
        ->and($table)->toContain('owner_demotion')->toContain('game_panel_identity')->toContain('discord_after_removal')->toContain('aapanel_outside_root')
        ->toContain('token_cross_org')->toContain('staff_own_org')->toContain('partner_payouts')->toContain('Verdict');

    [, $report] = flbRun();
    expect($report['read_only'])->toBeTrue()
        ->and(collect($report['sources'])->pluck('verdict')->unique()->diff(['CLEAN', 'UNKNOWNS'])->all())->toBe([])
        ->and($report['summary']['hits'])->toBe(0)
        ->and(flbSource($report, 'game_panel_identity')['verdict'])->toBe('UNKNOWNS'); // a panel user nobody recorded creating is not cleared
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
