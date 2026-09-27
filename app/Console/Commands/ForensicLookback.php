<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Forensics\AaPanelTraces;
use App\Console\Commands\Forensics\GrantTraces;
use App\Console\Commands\Forensics\MembershipHistory;
use App\Console\Commands\Forensics\PayoutChecks;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Did anybody use the permission holes before Phase 0 closed them? (TASK-0038 — permission program P0-01 / IF-0,
 * decision D16; runbook docs/runbooks/breach-register.md)
 *
 * For every verified exploit of the program (§1/§9: TD-1, TD-2, TD-3, PA-01, G1, PA-02, PA-04, SS-1/SS-5 with IF-8, P1/P2) it
 * reads what the platform already stored and reports the tables it read, the rows that match the exploit's trace and what
 * the data cannot tell. Strictly read-only: SELECTs only, in the database's own read-only mode (pgsql, sqlite) inside a
 * transaction that is always rolled back, no CommandBus, no
 * provider adapter, no panel call, no file — the report goes to stdout (a table, or JSON with --json). It prints ids,
 * never e-mail addresses, full IBANs or customer text beyond the offending path. Exit 1 when any source has a hit,
 * 2 for bad options. Whether a hit is a personal-data breach, and whether anybody is notified, is the owner's
 * decision (program §10 O3): this command decides nothing and sends nothing.
 */
final class ForensicLookback extends Command
{
    /** key => [exploit ids of the program §9, what the hit means, what the stored data can never show] */
    private const SOURCES = [
        'owner_demotion' => ['TD-1', 'The owner accepted an invitation to their own organization at a lower role',
            'Only the owner\'s current e-mail is known: an owner who changed the address after accepting is found by the audit row alone. Who owned the organization at a moment is read from the ownership transfers in the audit.'],
        // review round 2 (HIGH): program §1/§9 list TD-2 and TD-3 as exploitable today (IF-2, IF-3); D16's five were not all
        'member_without_invitation' => ['TD-2', 'A person was made a member of an organization without accepting an invitation (change_role with any user_id), and the organization saw their e-mail',
            'An attach is explained only by the closest event within 60 s of the same role: an accepted invitation to the person\'s current e-mail, the organization\'s creation (owner), an ownership transfer (owner for the new, org_admin for the previous owner). An invitation accepted under an address the account no longer carries makes a hit possible. A transfer to a non-member counts as explained (consent to a transfer is TD-9). Before an organization older than the audit, membership cannot be told.'],
        'project_grant_bypass' => ['TD-3', 'A project role granted past mayGrant: to oneself, or beyond the organization and project role the granting person held',
            'The role of a moment is read from the attach and project.member.add rows; where none precedes the grant, today\'s role stands in (possible). Permissions are today\'s RoleCatalog. Staff are not bound by mayGrant by design: only their self-grants are hits. A removal discloses nothing and is not judged.'],
        'game_panel_identity' => ['PA-01', 'A game service runs on a panel user that was created for another organization',
            'A panel user\'s external_id lives on the panel; the database knows it only for users the platform recorded creating. Standing state, not limited by the window.'],
        'discord_after_removal' => ['G1', 'Discord /onhost or an action hook was used after the person left the organization',
            'Reads (services, status) leave no row; `ask` leaves its assistant run. last_used_at keeps only the latest use and is written before any authorization (a use or a refused attempt). An action hook URL is a bearer credential: the audit names the hook\'s creator, not whoever held the URL; a refused hook run leaves no audit row.'],
        // review round 3 (HIGH): every operation the code lets write on an aaPanel node, not only files, cron and the terminal (AaPanelTraces)
        'aapanel_outside_root' => ['PA-02', 'An operation that made an aaPanel node write (file operations, terminal and scheduled commands, FTP homes, directives, restores, imports, deployments …) reached outside the site root',
            'A symlink planted inside the site root makes an in-root path land elsewhere; symlinks are not in the database, only on the node. A restore, an import or a deployment stores no path of its own: it is counted, not judged. A deploy source keeps only today\'s build command and hooks. SFTP, SSH, the site\'s own code and file reads leave no operation row.'],
        'token_cross_org' => ['PA-04', 'An API token of one organization wrote in another organization',
            'Only writes are audited; reads with a token leave no row. Before TASK-0030 a bearer request carrying a stateful Origin or Referer was audited under the web session Sanctum started for it, not token:<id>: such rows name no token. They are looked for by person and organization (rows of a token holder in an organization none of their live tokens was bound to) and listed as unknowns — the person\'s own browser writes look the same.'],
        'staff_own_org' => ['SS-1, SS-5 (EXPL-1..3), IF-8', 'A staff user used an is_staff shortcut in an organization they belong to (force purge, hold lift, a grant to themselves, a reinstatement), or any purge skipped the final archive',
            'The route (customer or staff API) is not recorded; the reason given is in the audit row. is_staff is read as it is now: a person who was staff then and is not any more is judged as a customer. The role a member held at a moment is not stored, only that they were a member.'],
        'partner_payouts' => ['P1, P2', 'A partner payout above the commissions allocated to it, or to an IBAN no earlier paid payout used',
            'A changed IBAN may be the partner\'s own new account: only the partner\'s confirmation proves it. partners.iban keeps only the latest IBAN: the payouts are its history.'],
    ];

    /**
     * Review round 1 (HIGH, D16): PA-01, TD-1's current roles and the G1 open links are read as they stand NOW, and the Phase
     * 0 fixes and their operator commands may change that state. A run after them cannot see what they changed, so the
     * reference run is the one before them; every report carries the rule (runbook breach-register.md, "The baseline run").
     */
    private const STANDING_STATE = 'Standing state (PA-01 panel users, TD-1 current roles, the G1 open links and hooks) is read as it is now: a Phase 0 repair can change it. The baseline is a run on production before the first Phase 0 deploy, or on a restore of the last backup taken before it; later runs are compared with it, never replace it.';

    private const LIST_LIMIT = 25;

    protected $signature = 'onhost:forensics:lookback
        {--since= : only events from this moment (ISO date); default: the whole history}
        {--until= : only events up to this moment (ISO date); default: now}
        {--source=* : one or more sources (default: all): owner_demotion, member_without_invitation, project_grant_bypass, game_panel_identity, discord_after_removal, aapanel_outside_root, token_cross_org, staff_own_org, partner_payouts}
        {--json : print the report as JSON instead of a table}';

    protected $description = 'Read-only forensic look-back: did anybody use the permission holes of Phase 0 (TASK-0038)';

    private ?CarbonImmutable $since = null;

    private CarbonImmutable $until;

    private MembershipHistory $history;

    private GrantTraces $grants;

    public function handle(): int
    {
        $this->history = new MembershipHistory;
        $problem = $this->readOptions();
        if ($problem !== null) {
            $this->error($problem);

            return self::INVALID;
        }
        $this->grants = new GrantTraces($this->history, $this->window());
        $selected = array_values(array_unique(array_map('strval', (array) $this->option('source')))) ?: array_keys(self::SOURCES);

        DB::beginTransaction(); // nothing below writes; rolling back anyway means no later change can make a look-back leave a trace
        $driver = DB::getDriverName();
        try { // review round 2 (MEDIUM): the database itself refuses a write, not only the code. PostgreSQL takes READ ONLY at any point of a
            // transaction (only READ WRITE must precede the first query) and a savepoint's rollback ends it; SQLite has a connection flag.
            // MySQL takes the mode only before BEGIN: there the SELECT-only code and the runbook's read-only database role stand.
            if ($driver === 'pgsql' || $driver === 'sqlite') {
                DB::statement($driver === 'pgsql' ? 'SET TRANSACTION READ ONLY' : 'PRAGMA query_only = ON');
            }
            $sources = array_map(fn (string $key) => $this->source($key), $selected);
            $coverage = $this->coverage();
        } finally {
            DB::rollBack();
            if ($driver === 'sqlite') {
                DB::statement('PRAGMA query_only = OFF'); // a connection flag, not part of the transaction
            }
        }
        $hits = array_sum(array_map(fn (array $s) => count($s['hits']), $sources));
        $report = [
            'command' => 'onhost:forensics:lookback', 'generated_at' => CarbonImmutable::now()->toIso8601String(), 'read_only' => true,
            'window' => ['since' => $this->since?->toIso8601String(), 'until' => $this->until->toIso8601String()], 'standing_state' => self::STANDING_STATE,
            'summary' => ['sources' => count($sources), 'hits' => $hits, 'unknowns' => array_sum(array_map(fn (array $s) => count($s['unknowns']), $sources)),
                'sources_with_hits' => array_values(array_map(fn (array $s) => $s['key'], array_filter($sources, fn (array $s) => $s['hits'] !== [])))],
            'coverage' => $coverage,
            'sources' => $sources,
        ];
        $this->option('json')
            ? $this->output->writeln((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), OutputInterface::OUTPUT_RAW)
            : $this->printTable($report);

        return $hits > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function readOptions(): ?string
    {
        $unknown = array_diff(array_map('strval', (array) $this->option('source')), array_keys(self::SOURCES));
        if ($unknown !== []) {
            return 'Unknown source: '.implode(', ', $unknown).'. Available: '.implode(', ', array_keys(self::SOURCES)).'.';
        }
        try {
            $this->until = $this->option('until') ? CarbonImmutable::parse((string) $this->option('until')) : CarbonImmutable::now();
            $this->since = $this->option('since') ? CarbonImmutable::parse((string) $this->option('since')) : null;
        } catch (Throwable) {
            return '--since and --until must be ISO dates.';
        }

        return $this->since !== null && ! $this->since->lessThan($this->until) ? '--since must lie before --until.' : null;
    }

    /** @return array<string,mixed> */
    private function source(string $key): array
    {
        [$exploit, $title, $limit] = self::SOURCES[$key];
        $this->history->resetJudged();
        $found = match ($key) {
            'owner_demotion' => $this->ownerDemotion(),
            'member_without_invitation' => $this->grants->withoutInvitation(),
            'project_grant_bypass' => $this->grants->projectGrants(),
            'game_panel_identity' => $this->gamePanelIdentity(),
            'discord_after_removal' => $this->discordAfterRemoval(),
            'aapanel_outside_root' => (new AaPanelTraces($this->window()))->outsideRoot(),
            'token_cross_org' => $this->tokenCrossOrg(),
            'staff_own_org' => $this->staffOwnOrg(),
            default => $this->partnerPayouts(),
        };
        $verdict = $found['hits'] !== [] ? 'HITS' : ($found['unknowns'] !== [] ? 'UNKNOWNS' : 'CLEAN');

        return ['key' => $key, 'exploit' => $exploit, 'title' => $title, 'verdict' => $verdict, 'checked' => $found['checked'],
            'hits' => $found['hits'], 'unknowns' => $found['unknowns'], 'notes' => $found['notes'] ?? [], 'limits' => $limit];
    }

    // ── TD-1: the owner demoted by accepting their own invitation ────────────────────────────────────────────

    /** @return array{checked:array<string,int>, hits:list<array<string,mixed>>, unknowns:list<string>} */
    private function ownerDemotion(): array
    {
        $organizations = DB::table('organizations')->get(['id', 'owner_user_id', 'created_at'])->keyBy('id');
        // Review round 1 (QA): an owner who demoted themselves and later handed the organization on by a legitimate transfer
        // is not today's owner — judged against the current owner, the demotion vanished (and the successor's own earlier
        // acceptance looked like one). Every event is judged against the owner OF ITS MOMENT, read from the transfers.
        $transfers = DB::table('audit_events')->where('action', 'organization.ownership.transfer')->orderBy('created_at')->orderBy('id')->get(['organization_id', 'detail', 'created_at'])
            ->map(fn ($t) => ['organization_id' => (string) $t->organization_id, 'from' => (string) ($this->json($t->detail)['from'] ?? ''), 'to' => (string) ($this->json($t->detail)['to'] ?? ''), 'at' => CarbonImmutable::parse($t->created_at)])
            ->groupBy('organization_id');
        $invitations = DB::table('organization_invitations')->whereNotNull('accepted_at')->where('role_key', '!=', 'owner')->whereBetween('accepted_at', $this->window())->orderBy('accepted_at')->get();
        $hits = [];
        $unmatched = [];
        $ownerGone = 0;
        foreach ($invitations as $invitation) {
            $organization = $organizations[$invitation->organization_id] ?? null;
            $owner = $this->history->ownerAt($organization, $transfers->get((string) $invitation->organization_id, collect()), CarbonImmutable::parse($invitation->accepted_at));
            $email = $owner === '' ? null : $this->history->emailOf($owner);
            if ($owner !== '' && $email === null) { // the owner of that moment has no account any more: their address cannot be compared
                $ownerGone++;
            } elseif ($email !== null && $email === mb_strtolower((string) $invitation->email)) {
                $hits[] = ['kind' => 'owner_accepted_lower_role', 'confidence' => 'confirmed', 'organization_id' => $invitation->organization_id, 'user_id' => $owner,
                    'invitation_id' => $invitation->id, 'role' => $invitation->role_key, 'at' => $this->iso($invitation->accepted_at), 'audit_event_id' => null,
                    'current_role' => $this->history->currentMembership($invitation->organization_id, $owner)['role'] ?? null, 'owner_now' => $owner === (string) ($organization->owner_user_id ?? '')];
            } else {
                $unmatched[] = mb_strtolower((string) $invitation->email);
            }
        }
        $nobody = $this->history->addressesWithoutAccount(array_values(array_unique($unmatched)));
        $attaches = 0;
        foreach ($this->auditRows(['organization.member.attach']) as $row) {
            $attaches++;
            $detail = $this->json($row->detail);
            $organization = $organizations[$row->organization_id] ?? null;
            $orgTransfers = $transfers->get((string) $row->organization_id, collect());
            $user = (string) ($detail['user_id'] ?? '');
            $at = CarbonImmutable::parse($row->created_at);
            if ($organization === null || $user === '' || (string) ($detail['role'] ?? 'owner') === 'owner' || $user !== $this->history->ownerAt($organization, $orgTransfers, $at)) {
                continue; // an attach of somebody who was not the owner at that moment is history, not a demotion
            }
            if ((string) ($detail['role'] ?? '') === 'org_admin' && $orgTransfers->contains(fn (array $t) => $t['from'] === $user && abs($t['at']->diffInSeconds($at)) <= 60)) {
                continue; // the transfer's own step: the previous owner becomes org_admin (review round 2: that role only)
            }
            foreach ($hits as $i => $hit) { // the same acceptance, seen twice: the audit row is its evidence
                if ($hit['organization_id'] === $row->organization_id && $hit['user_id'] === $user && $hit['audit_event_id'] === null && abs(CarbonImmutable::parse($hit['at'])->diffInSeconds($at)) <= 60) {
                    $hits[$i]['audit_event_id'] = $row->id;

                    continue 2;
                }
            }
            $hits[] = ['kind' => 'owner_attached_lower_role', 'confidence' => 'confirmed', 'organization_id' => $row->organization_id, 'user_id' => $user,
                'role' => (string) ($detail['role'] ?? ''), 'at' => $at->toIso8601String(), 'audit_event_id' => $row->id,
                'current_role' => $this->history->currentMembership($row->organization_id, $user)['role'] ?? null, 'owner_now' => $user === (string) $organization->owner_user_id];
        }
        $explained = array_map(fn (array $h) => $h['organization_id'].'#'.$h['user_id'], $hits);
        foreach ($organizations as $organization) { // what the owner holds today, whatever put it there
            $role = $this->history->currentMembership((string) $organization->id, (string) $organization->owner_user_id)['role'] ?? null;
            if ($role !== 'owner' && ! in_array($organization->id.'#'.$organization->owner_user_id, $explained, true)) {
                $hits[] = ['kind' => 'owner_membership_not_owner', 'confidence' => 'confirmed', 'organization_id' => $organization->id, 'user_id' => $organization->owner_user_id, 'current_role' => $role];
            }
        }
        $unknowns = array_values(array_filter([
            $nobody > 0 ? "{$nobody} accepted invitation".($nobody === 1 ? '' : 's').' went to an address that belongs to no account any more: who accepted cannot be told.' : null,
            $ownerGone > 0 ? "{$ownerGone} accepted invitation".($ownerGone === 1 ? '' : 's').' fall in a time whose owner has no account any more: whether the owner accepted cannot be told.' : null,
        ]));

        return ['checked' => ['organization_invitations' => $invitations->count(), 'audit_events' => $attaches + $transfers->flatten(1)->count(), 'organizations' => $organizations->count(), 'organization_memberships' => $organizations->count()],
            'hits' => $hits, 'unknowns' => $unknowns];
    }

    // ── PA-01: a game server on another organization's panel user ────────────────────────────────────────────

    /** @return array{checked:array<string,int>, hits:list<array<string,mixed>>, unknowns:list<string>} */
    private function gamePanelIdentity(): array
    {
        $instances = DB::table('provider_instances')->where('provider', 'pterodactyl')->pluck('key', 'id');
        $services = DB::table('services')->where('family', 'game')->get(['id', 'organization_id', 'provider_instance_id']);
        $bindings = DB::table('provider_bindings')->where('remote_type', 'server')->whereIn('provider_instance_id', $instances->keys()->all())->get(['service_id', 'provider_instance_id', 'meta'])->keyBy('service_id');
        $gameServers = DB::table('game_servers')->pluck('ptero_user_id', 'service_id');
        $operations = DB::table('operations')->whereIn('kind', ['provision.game', 'game.migrate'])->get(['organization_id', 'service_id', 'provider_instance_id', 'context']);
        $serviceInstance = $services->pluck('provider_instance_id', 'id');
        $creators = [];
        foreach ($operations as $operation) { // the platform created this panel user for the operation's organization: its external_id is that organization
            $context = $this->json($operation->context);
            if (($context['ptero_user_created'] ?? false) === true && (int) ($context['ptero_user_id'] ?? 0) > 0) {
                $creators[($operation->provider_instance_id ?? $serviceInstance[$operation->service_id] ?? '').'#'.(int) $context['ptero_user_id']] = (string) $operation->organization_id;
            }
        }
        $groups = [];
        $noUser = [];
        foreach ($services as $service) {
            $binding = $bindings[$service->id] ?? null;
            $instance = (string) ($binding->provider_instance_id ?? $service->provider_instance_id ?? '');
            $user = (int) ($this->json($binding?->meta)['user_id'] ?? $gameServers[$service->id] ?? 0);
            if ($user <= 0 || ! isset($instances[$instance])) {
                $noUser[] = $service->id;

                continue;
            }
            $groups["{$instance}#{$user}"][] = $service;
        }
        $hits = [];
        $unrecorded = [];
        foreach ($groups as $key => $members) {
            [$instance, $user] = explode('#', $key);
            $creator = $creators[$key] ?? null;
            $organizations = array_values(array_unique(array_map(fn ($s) => (string) $s->organization_id, $members)));
            foreach ($members as $service) {
                $base = ['service_id' => $service->id, 'organization_id' => $service->organization_id, 'panel_user_id' => (int) $user, 'instance' => (string) $instances[$instance]];
                if ($creator !== null && $creator !== (string) $service->organization_id) {
                    $hits[] = ['kind' => 'panel_user_of_other_org', 'confidence' => 'confirmed'] + $base + ['panel_user_organization_id' => $creator];
                } elseif ($creator === null && count($organizations) > 1) {
                    $hits[] = ['kind' => 'panel_user_shared_across_orgs', 'confidence' => 'possible'] + $base + ['sharing_organization_ids' => array_values(array_diff($organizations, [(string) $service->organization_id]))];
                } elseif ($creator === null) {
                    $unrecorded[] = $service->id;
                }
            }
        }
        $unknowns = [];
        if ($unrecorded !== []) {
            $unknowns[] = count($unrecorded).' game service(s) run on a panel user the platform did not record creating (found by e-mail, or older than the record): its external_id is only on the panel — '.$this->ids($unrecorded).'. The Pterodactyl identity dry-run of TASK-0033 reads it.';
        }
        if ($noUser !== []) {
            $unknowns[] = count($noUser).' game service(s) have no panel user stored: '.$this->ids($noUser).'.';
        }

        return ['checked' => ['services' => $services->count(), 'provider_bindings' => $bindings->count(), 'game_servers' => $gameServers->count(), 'operations' => $operations->count()], 'hits' => $hits, 'unknowns' => $unknowns];
    }

    // ── G1: Discord after the member was removed ─────────────────────────────────────────────────────────────

    /** @return array{checked:array<string,int>, hits:list<array<string,mixed>>, unknowns:list<string>, notes:list<string>} */
    private function discordAfterRemoval(): array
    {
        $links = DB::table('discord_links')->whereNotNull('linked_at')->get(['id', 'organization_id', 'user_id', 'state', 'linked_at', 'last_used_at', 'commands']);
        $hits = [];
        $unknown = 0;
        $open = [];
        $gaps = [];
        foreach ($links as $link) {
            if ($link->state === 'linked' && $this->history->membershipAt((string) $link->organization_id, (string) $link->user_id, CarbonImmutable::now())[0] === false) {
                $open[] = $link->id;
            }
            // review round 1: last_used_at is overwritten by every use — remove, use, re-admit, use again hides the middle one
            if ($link->last_used_at !== null && $this->history->readmittedSince((string) $link->organization_id, (string) $link->user_id, CarbonImmutable::parse($link->linked_at))) {
                $gaps[] = $link->id;
            }
            if ($link->last_used_at === null || ! $this->inWindow($link->last_used_at)) {
                continue;
            }
            [$member, $ended] = $this->history->membershipAt((string) $link->organization_id, (string) $link->user_id, CarbonImmutable::parse($link->last_used_at));
            if ($member === false) { // last_used_at is written before any authorization (DiscordService): a use after the removal, or a refused attempt
                $hits[] = ['kind' => 'discord_used_after_removal', 'confidence' => 'use_or_attempt', 'link_id' => $link->id, 'organization_id' => $link->organization_id, 'user_id' => $link->user_id,
                    'removed_at' => $ended?->toIso8601String(), 'last_used_at' => $this->iso($link->last_used_at), 'commands' => (int) $link->commands, 'link_state' => $link->state];
            } elseif ($member === null) {
                $unknown++;
            }
        }
        [$audit, $askAudited, $hookRuns] = $this->sessionRowsAfterRemoval();
        [$ask, $runs] = $this->askRunsAfterRemoval($askAudited);
        [$openHooks, $hookCount] = $this->hooksOfFormerMembers();
        $unknown += $audit['unknown'] + $ask['unknown'];

        return ['checked' => ['discord_links' => $links->count(), 'audit_events' => $audit['rows'], 'support_ai_runs' => $runs, 'action_hooks' => $hookCount, 'organization_memberships' => $this->history->judgedCount()],
            'hits' => [...$hits, ...$audit['hits'], ...$ask['hits']],
            'unknowns' => array_values(array_filter([
                $unknown > 0 ? "{$unknown} Discord or hook use(s) by a person whose membership history is not recorded: cannot say whether they were still a member." : null,
                $gaps !== [] ? count($gaps).' Discord link(s) whose person was removed and re-admitted while linked: last_used_at keeps only the latest use, so a read in the gap leaves nothing — '.$this->ids($gaps).'.' : null,
                $hookRuns > 0 ? "{$hookRuns} action hook run".($hookRuns === 1 ? '' : 's').' after somebody left the organization: the hook URL is a bearer credential, and whether the one who left still held it cannot be told.' : null,
            ])),
            'notes' => array_values(array_filter([
                $open !== [] ? count($open).' Discord link(s) are still linked for people who are no longer members (an open door, no use after the removal seen): '.$this->ids($open).' — TASK-0035 revokes them.' : null,
                $openHooks !== [] ? count($openHooks).' action hook(s) are still enabled although their creator is no longer a member: '.$this->ids($openHooks).' — TASK-0035 revokes them.' : null,
            ]))];
    }

    /**
     * Audit rows written through Discord (`discord:<uid>`) and through an action hook (`hook:<id>`, ActionHookService) by a
     * person who was no longer a member.
     *
     * @return array{0: array{rows:int, unknown:int, hits:list<array<string,mixed>>}, 1: array<string,true>, 2: int} [result, assistant runs these rows already cover, hook runs after somebody's removal]
     */
    private function sessionRowsAfterRemoval(): array
    {
        $out = ['rows' => 0, 'unknown' => 0, 'hits' => []];
        $askAudited = [];
        $hookRuns = 0;
        $rows = DB::table('audit_events')->where(fn ($q) => $q->where('session_id', 'like', 'discord:%')->orWhere('session_id', 'like', 'hook:%'))
            ->whereBetween('created_at', $this->window())->whereNotNull('organization_id')->lazyById(1000, 'id');
        foreach ($rows as $row) {
            $out['rows']++;
            $viaHook = str_starts_with((string) $row->session_id, 'hook:');
            if (! $viaHook && $row->action === 'assistant.chat' && $row->resource_id !== null) {
                $askAudited[(string) $row->resource_id] = true;
            }
            $at = CarbonImmutable::parse($row->created_at);
            [$member, $ended] = $this->history->membershipAt((string) $row->organization_id, (string) $row->actor_id, $at);
            if ($member === false) {
                $out['hits'][] = ['kind' => $viaHook ? 'hook_run_after_removal' : 'discord_command_after_removal', 'confidence' => 'confirmed', 'audit_event_id' => $row->id]
                    + ($viaHook ? ['hook_id' => substr((string) $row->session_id, 5)] : [])
                    + ['organization_id' => $row->organization_id, 'user_id' => $row->actor_id, 'action' => (string) ($this->json($row->detail)['action'] ?? $row->action), 'at' => $at->toIso8601String(), 'removed_at' => $ended?->toIso8601String()];
            } elseif ($member === null) {
                $out['unknown']++;
            } elseif ($viaHook && $this->history->removedBefore((string) $row->organization_id, $at)) {
                $hookRuns++;
            }
        }

        return [$out, $askAudited, $hookRuns];
    }

    /**
     * `/onhost ask` leaves one assistant run per question (session `discord:<uid>`, DiscordService::askReply) even where no
     * audit row was written; a run the audit already judged is not counted twice.
     *
     * @param  array<string,true>  $askAudited
     * @return array{0: array{unknown:int, hits:list<array<string,mixed>>}, 1: int}
     */
    private function askRunsAfterRemoval(array $askAudited): array
    {
        $out = ['unknown' => 0, 'hits' => []];
        $runs = 0;
        $rows = DB::table('support_ai_runs')->where('session_id', 'like', 'discord:%')->whereNotNull('organization_id')->whereNotNull('user_id')
            ->whereBetween('created_at', $this->window())->lazyById(1000, 'id');
        foreach ($rows as $run) {
            $runs++;
            if (isset($askAudited[(string) $run->id])) {
                continue;
            }
            [$member, $ended] = $this->history->membershipAt((string) $run->organization_id, (string) $run->user_id, CarbonImmutable::parse($run->created_at));
            if ($member === false) {
                $out['hits'][] = ['kind' => 'discord_ask_after_removal', 'confidence' => 'confirmed', 'ai_run_id' => $run->id, 'organization_id' => $run->organization_id, 'user_id' => $run->user_id,
                    'outcome' => $run->outcome, 'at' => $this->iso($run->created_at), 'removed_at' => $ended?->toIso8601String()];
            } elseif ($member === null) {
                $out['unknown']++;
            }
        }

        return [$out, $runs];
    }

    /** @return array{0: list<string>, 1: int} [enabled hooks whose creator is no longer a member, hooks read] */
    private function hooksOfFormerMembers(): array
    {
        $hooks = DB::table('action_hooks')->where('enabled', true)->whereNotNull('created_by')->get(['id', 'organization_id', 'created_by']);
        $open = $hooks->filter(fn ($h) => $this->history->membershipAt((string) $h->organization_id, (string) $h->created_by, CarbonImmutable::now())[0] === false)->pluck('id')->map(fn ($id) => (string) $id)->values()->all();

        return [$open, $hooks->count()];
    }

    // ── PA-04: a token of one organization used in another ──────────────────────────────────────────────────

    /** @return array{checked:array<string,int>, hits:list<array<string,mixed>>, unknowns:list<string>} */
    private function tokenCrossOrg(): array
    {
        $tokens = [];
        $groups = [];
        $missing = 0;
        $unbound = 0;
        $rows = 0;
        foreach (DB::table('audit_events')->where('session_id', 'like', 'token:%')->whereNotNull('organization_id')->whereBetween('created_at', $this->window())->lazyById(1000, 'id') as $row) {
            $rows++;
            $id = substr((string) $row->session_id, 6);
            if (! ctype_digit($id)) { // TASK-0029: a session id that is not a number named no token
                $missing++;

                continue;
            }
            if (! array_key_exists($id, $tokens)) {
                $token = DB::table('personal_access_tokens')->where('id', (int) $id)->first(['organization_id']);
                $tokens[$id] = $token === null ? false : (string) ($token->organization_id ?? '');
            }
            if ($tokens[$id] === false) {
                $missing++;
            } elseif ($tokens[$id] === '') {
                $unbound++;
            } elseif ($tokens[$id] !== (string) $row->organization_id) {
                $key = "{$id}#{$row->organization_id}";
                $at = CarbonImmutable::parse($row->created_at);
                $group = $groups[$key] ?? ['kind' => 'token_used_on_other_org', 'confidence' => 'confirmed', 'token_id' => (int) $id, 'token_organization_id' => $tokens[$id],
                    'organization_id' => $row->organization_id, 'user_id' => $row->actor_id, 'rows' => 0, 'first_at' => $at, 'last_at' => $at, 'first_audit_event_id' => $row->id, 'actions' => []];
                if ($at->lessThan($group['first_at'])) {
                    [$group['first_at'], $group['first_audit_event_id']] = [$at, $row->id];
                }
                $group['last_at'] = $at->greaterThan($group['last_at']) ? $at : $group['last_at'];
                $group['rows']++;
                $group['actions'] = array_slice(array_values(array_unique([...$group['actions'], (string) $row->action])), 0, 10);
                $groups[$key] = $group;
            }
        }
        $hits = array_values(array_map(fn (array $g) => ['first_at' => $g['first_at']->toIso8601String(), 'last_at' => $g['last_at']->toIso8601String()] + $g, $groups));
        [$sessionRows, $holders, $sessionRead] = $this->tokenHoldersUnderSession();
        $unknowns = array_values(array_filter([
            $missing > 0 ? "{$missing} audit row".($missing === 1 ? '' : 's').' name a token that no longer exists: its organization cannot be read.' : null,
            $unbound > 0 ? "{$unbound} audit row".($unbound === 1 ? '' : 's').' were written with a token that carries no organization (made before tokens were bound to one).' : null,
            $sessionRows !== [] ? count($sessionRows).' audit row'.(count($sessionRows) === 1 ? '' : 's')." by {$holders} person(s) holding a live token bound to another organization were written in that other organization under a session, not token:<id>. Before TASK-0030 a bearer request carrying a stateful Origin or Referer was audited under the web session Sanctum started for it, so a token write there cannot be told from the person's own browser — compare the ip, user agent and request id with the token's use: "
                .$this->ids($sessionRows).'.' : null,
        ]));

        return ['checked' => ['audit_events' => $rows + $sessionRead, 'personal_access_tokens' => count($tokens)], 'hits' => $hits, 'unknowns' => $unknowns];
    }

    /**
     * Review round 3 (MEDIUM): until TASK-0030 ApiContext::sessionId preferred a started session to the token, and Sanctum
     * starts one for a bearer request that carries a stateful Origin or Referer — that write was audited under a web session
     * id and names no token. What remains is the person and the organization: a row of somebody holding a token that was
     * live at that moment and bound to another organization, in an organization none of their live tokens was bound to.
     *
     * @return array{0: list<string>, 1: int, 2: int} [audit ids, people, rows read]
     */
    private function tokenHoldersUnderSession(): array
    {
        $holders = DB::table('personal_access_tokens')->whereNotNull('organization_id')->get(['tokenable_id', 'organization_id', 'created_at', 'revoked_at', 'expires_at'])->groupBy('tokenable_id');
        $found = [];
        $people = [];
        $read = 0;
        foreach ($holders->keys()->chunk(500) as $chunk) {
            $rows = DB::table('audit_events')->where('actor_type', 'user')->whereIn('actor_id', $chunk->map(fn ($id) => (string) $id)->all())->whereNotNull('organization_id')
                ->whereBetween('created_at', $this->window())
                ->where(fn ($q) => $q->whereNull('session_id')->orWhere(fn ($s) => $s->where('session_id', 'not like', 'token:%')->where('session_id', 'not like', 'discord:%')->where('session_id', 'not like', 'hook:%')))
                ->lazyById(1000, 'id');
            foreach ($rows as $row) {
                $read++;
                $at = CarbonImmutable::parse($row->created_at);
                $live = $holders[$row->actor_id]->filter(fn ($t) => ! $at->lessThan(CarbonImmutable::parse($t->created_at))
                    && ($t->revoked_at === null || ! $at->greaterThan(CarbonImmutable::parse($t->revoked_at))) && ($t->expires_at === null || ! $at->greaterThan(CarbonImmutable::parse($t->expires_at))));
                if ($live->isNotEmpty() && ! $live->contains(fn ($t) => (string) $t->organization_id === (string) $row->organization_id)) {
                    $found[] = (string) $row->id;
                    $people[(string) $row->actor_id] = true;
                }
            }
        }

        return [$found, count($people), $read];
    }

    // ── SS-1 / SS-5: staff acting on their own organization through the is_staff shortcuts ─────────────────

    /** @return array{checked:array<string,int>, hits:list<array<string,mixed>>, unknowns:list<string>} */
    private function staffOwnOrg(): array
    {
        $hits = [];
        $unknown = 0;
        $lifts = 0;
        foreach ($this->auditRows(['service.hold.lift']) as $row) {
            $lifts++;
            $detail = $this->json($row->detail);
            // a customer lifts the one hold it names (a string); the staff path lifts them all (a list) — ServiceService::liftHolds
            if ($row->actor_type !== 'user' || $row->organization_id === null || ! (is_array($detail['lifted'] ?? null) || $this->history->isStaff((string) $row->actor_id))) {
                continue;
            }
            $member = $this->memberOrUnknown((string) $row->organization_id, (string) $row->actor_id, CarbonImmutable::parse($row->created_at), $unknown);
            if ($member) {
                $hits[] = ['kind' => 'staff_hold_lift_own_org', 'confidence' => 'confirmed', 'audit_event_id' => $row->id, 'user_id' => $row->actor_id, 'organization_id' => $row->organization_id,
                    'service_id' => $row->resource_id, 'lifted' => $detail['lifted'] ?? null, 'at' => $this->iso($row->created_at)];
            }
        }
        $purges = DB::table('operations')->where('kind', 'service.action')->whereIn('desired->action', ['purge', 'terminate'])
            ->whereBetween('created_at', $this->window())->orderBy('created_at')->get(['id', 'organization_id', 'service_id', 'actor_type', 'actor_id', 'state', 'desired', 'context', 'created_at']);
        foreach ($purges as $operation) {
            $desired = $this->json($operation->desired);
            // the step records the skip too (ServiceActionWorkflow::finalArchiveStep): either trace is enough
            $skipped = ($desired['archive_before_delete'] ?? true) === false || array_key_exists('final_archive_skipped', $this->json($operation->context));
            $forced = (($desired['action'] ?? '') === 'purge' && ! empty($desired['force'])) || $skipped;
            $user = $operation->actor_type === 'user';
            $own = $user && $forced && $operation->organization_id !== null && $this->memberOrUnknown((string) $operation->organization_id, (string) $operation->actor_id, CarbonImmutable::parse($operation->created_at), $unknown);
            // review round 3 (MEDIUM): a purge that skipped the final archive destroyed a customer's data beyond recovery in ANY
            // organization — a rogue staff purge of a stranger is the worse case, and it was reported only inside the actor's own
            if ($own || $skipped) {
                $hits[] = ['kind' => $own ? 'staff_force_purge_own_org' : 'purge_without_archive', 'confidence' => 'confirmed', 'operation_id' => $operation->id, 'user_id' => $operation->actor_id,
                    'actor_type' => $operation->actor_type, 'organization_id' => $operation->organization_id, 'service_id' => $operation->service_id, 'action' => $desired['action'] ?? null,
                    'archive_skipped' => $skipped, 'own_org' => $own, 'state' => $operation->state,
                    'staff_now' => $user && $this->history->isStaff((string) $operation->actor_id), 'at' => $this->iso($operation->created_at)];
            }
        }
        [$grants, $grantRows] = $this->staffSelfGrants();
        [$reinstates, $reinstateRows] = $this->staffReinstatements($unknown);

        return ['checked' => ['audit_events' => $lifts + $grantRows + $reinstateRows, 'operations' => $purges->count(), 'organization_memberships' => $this->history->judgedCount()],
            'hits' => [...$hits, ...$grants, ...$reinstates],
            'unknowns' => $unknown > 0 ? ["{$unknown} staff action(s) in an organization older than the audit, by a staff user with no recorded membership there: whether they were a member then cannot be told."] : []];
    }

    /**
     * IF-8 (review round 1): OrganizationsCommandHandler::mayGrant returns early for staff, so self_membership_locked and
     * role_above_own never stop a staff user raising their own role. The trace is an attach row whose actor is its own
     * target — once the attaches an invitation acceptance, the creation of the organization or an ownership transfer
     * write for the actor themselves are set aside, each only for its own role (review round 2, GrantTraces::explained).
     *
     * @return array{0: list<array<string,mixed>>, 1: int}
     */
    private function staffSelfGrants(): array
    {
        $hits = [];
        $rows = 0;
        foreach ($this->auditRows(['organization.member.attach']) as $row) {
            $rows++;
            $user = (string) ($this->json($row->detail)['user_id'] ?? '');
            if ($row->actor_type !== 'user' || $row->organization_id === null || $user === '' || $user !== (string) $row->actor_id || ! $this->history->isStaff($user)) {
                continue;
            }
            $at = CarbonImmutable::parse($row->created_at);
            if ($this->grants->explained((string) $row->organization_id, $user, (string) ($this->json($row->detail)['role'] ?? ''), $at)) {
                continue;
            }
            $hits[] = ['kind' => 'staff_self_grant', 'confidence' => 'confirmed', 'audit_event_id' => $row->id, 'user_id' => $user, 'organization_id' => $row->organization_id,
                'role' => (string) ($this->json($row->detail)['role'] ?? ''), 'member_before' => $this->history->membershipAt((string) $row->organization_id, $user, $at->subSecond())[0], 'at' => $at->toIso8601String()];
        }

        return [$hits, $rows];
    }

    /**
     * IF-8 (review round 1): ServiceReinstatement::actorMay answers true for any staff user, so a staff member asks or pays
     * a reinstatement in their own organization without the role that permits it. `possible`: a member whose role holds
     * the permission may have done the same legitimately — the role of that moment is not stored.
     *
     * @return array{0: list<array<string,mixed>>, 1: int}
     */
    private function staffReinstatements(int &$unknown): array
    {
        $hits = [];
        $rows = 0;
        foreach ($this->auditRows(['service.reinstate', 'service.reinstate.requested']) as $row) {
            $rows++;
            if ($row->actor_type !== 'user' || $row->organization_id === null || ! $this->history->isStaff((string) $row->actor_id)) {
                continue;
            }
            if ($this->memberOrUnknown((string) $row->organization_id, (string) $row->actor_id, CarbonImmutable::parse($row->created_at), $unknown)) {
                $hits[] = ['kind' => 'staff_reinstate_own_org', 'confidence' => 'possible', 'audit_event_id' => $row->id, 'action' => $row->action, 'user_id' => $row->actor_id,
                    'organization_id' => $row->organization_id, 'service_id' => $row->resource_id, 'current_role' => $this->history->currentMembership((string) $row->organization_id, (string) $row->actor_id)['role'] ?? null,
                    'at' => $this->iso($row->created_at)];
            }
        }

        return [$hits, $rows];
    }

    /**
     * Was the actor a member? A membership nothing recorded counts as "not a member" only where the audit reaches back to
     * the organization's creation — every membership since then left an attach row. Otherwise it is counted as unknown
     * (review round 1: a null membership was silently read as "not a member").
     */
    private function memberOrUnknown(string $organizationId, string $userId, CarbonImmutable $at, int &$unknown): bool
    {
        $member = $this->history->membershipAt($organizationId, $userId, $at)[0];
        if ($member === null && ! $this->history->historyCovered($organizationId)) {
            $unknown++;
        }

        return $member === true;
    }

    // ── P1 / P2: partner payouts ─────────────────────────────────────────────────────────────────────────────

    /** @return array{checked:array<string,int>, hits:list<array<string,mixed>>, unknowns:list<string>} */
    private function partnerPayouts(): array
    {
        $payouts = DB::table('partner_payouts')->whereBetween('requested_at', $this->window())->orderBy('requested_at')->get();
        $paid = DB::table('partner_payouts')->where('state', 'paid')->whereNotNull('iban')->get(['id', 'partner_id', 'iban', 'requested_at'])->groupBy('partner_id');
        $allocated = DB::table('partner_commissions')->whereIn('payout_id', $payouts->pluck('id')->all())->groupBy('payout_id', 'currency')
            ->selectRaw('payout_id, currency, sum(amount_minor) as total')->get()->groupBy('payout_id');
        $partners = DB::table('partners')->get(['id', 'organization_id'])->keyBy('id');
        $partnerOwners = DB::table('organizations')->whereIn('id', $partners->pluck('organization_id')->all())->pluck('owner_user_id', 'id');
        $requesters = DB::table('audit_events')->where('action', 'partner.payout.request')->whereIn('resource_id', $payouts->pluck('id')->all())->pluck('actor_id', 'resource_id');
        $hits = [];
        $unknown = 0;
        $first = [];
        foreach ($payouts as $payout) {
            // a rejection hands the commissions back, so its amount took nothing — but requestPayout had already written its IBAN
            // into partners.iban, and the automatic payouts pay there: the IBAN of a rejected request is still read (review round 1)
            $excess = $payout->state === 'rejected' ? null : PayoutChecks::excess($payout, $allocated[$payout->id] ?? new Collection);
            if ($excess === false) {
                $unknown++;
            } elseif ($excess !== null) {
                $hits[] = ['kind' => 'payout_above_allocated', 'confidence' => 'confirmed', 'payout_id' => $payout->id, 'number' => $payout->number, 'partner_id' => $payout->partner_id,
                    'amount_minor' => $excess['amount']->minor, 'allocated_minor' => $excess['allocated']->minor, 'excess_minor' => $excess['excess']->minor, 'currency' => $excess['amount']->currency->value,
                    'state' => $payout->state, 'requested_at' => $this->iso($payout->requested_at)];
            }
            $confirmed = collect($paid[$payout->partner_id] ?? [])->filter(fn ($p) => $p->id !== $payout->id && $p->requested_at < $payout->requested_at)
                ->map(fn ($p) => PayoutChecks::normalIban((string) $p->iban))->unique()->values();
            $iban = PayoutChecks::normalIban((string) $payout->iban);
            if ($payout->method === 'bank_transfer' && $iban !== '' && $confirmed->isEmpty()) { // nothing earlier to compare with: neither a hit nor clean
                $first[] = (string) $payout->id;
            }
            if ($payout->method === 'bank_transfer' && $iban !== '' && $confirmed->isNotEmpty() && ! $confirmed->contains($iban)) {
                $requester = $requesters[$payout->id] ?? null;
                $hits[] = ['kind' => 'payout_iban_changed', 'confidence' => 'possible', 'payout_id' => $payout->id, 'number' => $payout->number, 'partner_id' => $payout->partner_id,
                    'iban' => PayoutChecks::maskIban($iban), 'confirmed_ibans' => $confirmed->map(fn (string $i) => PayoutChecks::maskIban($i))->all(), 'state' => $payout->state,
                    'requested_by' => $requester, 'requested_by_owner' => $requester === null ? null : $requester === ($partnerOwners[$partners[$payout->partner_id]->organization_id ?? ''] ?? null),
                    'requested_at' => $this->iso($payout->requested_at)];
            }
        }

        return ['checked' => ['partner_payouts' => $payouts->count(), 'partner_commissions' => $allocated->count(), 'audit_events' => $requesters->count()], 'hits' => $hits,
            'unknowns' => array_values(array_filter([
                $unknown > 0 ? "{$unknown} payout(s) mix currencies with their commissions: the allocated sum cannot be compared." : null,
                $first !== [] ? count($first).' bank-transfer payout(s) have no earlier paid payout of the partner to compare the IBAN with (the first one, or all earlier ones unpaid): only the partner\'s confirmation clears them — '.$this->ids($first).'.' : null,
            ]))];
    }

    // ── helpers ──────────────────────────────────────────────────────────────────────────────────────────────

    /** @param list<string> $actions */
    private function auditRows(array $actions): LazyCollection
    {
        return DB::table('audit_events')->whereIn('action', $actions)->whereBetween('created_at', $this->window())->lazyById(1000, 'id');
    }

    /** @return array{0:CarbonImmutable, 1:CarbonImmutable} */
    private function window(): array
    {
        return [$this->since ?? CarbonImmutable::createFromTimestampUTC(0), $this->until];
    }

    private function inWindow(mixed $moment): bool
    {
        $at = CarbonImmutable::parse((string) $moment);

        return ! $at->lessThan($this->window()[0]) && ! $at->greaterThan($this->until);
    }

    /** How far back the evidence reaches: events older than the oldest row cannot be seen by any source. @return array<string,mixed> */
    private function coverage(): array
    {
        $out = [];
        foreach (['audit_events' => 'created_at', 'operations' => 'created_at'] as $table => $column) {
            $oldest = DB::table($table)->min($column);
            $out[$table] = ['rows' => DB::table($table)->count(), 'oldest' => $oldest === null ? null : $this->iso($oldest)];
            if ($oldest !== null && $this->since !== null && $this->since->lessThan(CarbonImmutable::parse((string) $oldest))) {
                $out[$table]['warning'] = 'the window starts before the oldest row: the start of the window is not covered';
            }
        }

        return $out;
    }

    /** @return array<string,mixed> */
    private function json(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    private function iso(mixed $moment): ?string
    {
        return $moment === null ? null : CarbonImmutable::parse((string) $moment)->toIso8601String();
    }

    /** @param list<string> $ids */
    private function ids(array $ids): string
    {
        return implode(', ', array_slice($ids, 0, self::LIST_LIMIT)).(count($ids) > self::LIST_LIMIT ? ' … (+'.(count($ids) - self::LIST_LIMIT).')' : '');
    }

    /** @param array<string,mixed> $report */
    private function printTable(array $report): void
    {
        $this->line('Forensic look-back (read-only) · window '.($report['window']['since'] ?? 'whole history').' → '.$report['window']['until']);
        $this->line('BASELINE '.$report['standing_state']);
        $this->table(['Source', 'Exploit', 'Checked (rows)', 'Hits', 'Unknowns', 'Verdict'], array_map(fn (array $s) => [
            $s['key'], $s['exploit'], implode(', ', array_map(fn ($t, $n) => "{$t} {$n}", array_keys($s['checked']), $s['checked'])), count($s['hits']), count($s['unknowns']), $s['verdict'],
        ], $report['sources']));
        foreach ($report['sources'] as $source) {
            if ($source['hits'] === [] && $source['unknowns'] === [] && $source['notes'] === []) {
                continue;
            }
            $lines = ["{$source['key']} ({$source['exploit']}): {$source['title']}"];
            foreach ($source['hits'] as $hit) {
                $lines[] = '  HIT '.implode(' ', array_map(fn ($k, $v) => $k.'='.(is_scalar($v) || $v === null ? var_export($v, true) : json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), array_keys($hit), $hit));
            }
            foreach ($source['unknowns'] as $unknown) {
                $lines[] = '  UNKNOWN '.$unknown;
            }
            foreach ($source['notes'] as $note) {
                $lines[] = '  NOTE '.$note;
            }
            $lines[] = '  LIMIT '.$source['limits'];
            $this->output->writeln($lines, OutputInterface::OUTPUT_RAW); // the values as they are: no console markup is read into them
        }
        $this->line('Hits are evidence to assess, not a verdict on a breach: record them in the breach register (docs/runbooks/breach-register.md). Nothing is sent to anybody.');
    }
}
