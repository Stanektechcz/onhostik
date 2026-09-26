<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Onhost\Platform\Money\Money;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Did anybody use the permission holes before Phase 0 closed them? (TASK-0038 — permission program P0-01 / IF-0,
 * decision D16; runbook docs/runbooks/breach-register.md)
 *
 * For every verified exploit of the program (§9: TD-1, PA-01, G1, PA-02, PA-04, SS-1/SS-5, P1/P2) it reads what the
 * platform already stored and reports the tables it read, the rows that match the exploit's trace and what the data
 * cannot tell. Strictly read-only: SELECTs only, inside a transaction that is always rolled back, no CommandBus, no
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
            'Only the owner\'s current e-mail is known: an owner who changed the address after accepting is found by the audit row alone.'],
        'game_panel_identity' => ['PA-01', 'A game service runs on a panel user that was created for another organization',
            'A panel user\'s external_id lives on the panel; the database knows it only for users the platform recorded creating. Standing state, not limited by the window.'],
        'discord_after_removal' => ['G1', 'Discord /onhost was used after the person left the organization',
            'Reads (services, status, ask) leave no audit row: the link\'s last_used_at proves a use after the removal, not what was read.'],
        'aapanel_outside_root' => ['PA-02', 'An aaPanel file operation or scheduled command reached outside the site root',
            'A symlink planted inside the site root makes an in-root path land elsewhere; symlinks are not in the database, only on the node. File reads leave no operation row.'],
        'token_cross_org' => ['PA-04', 'An API token of one organization wrote in another organization',
            'Only writes are audited; reads with a token leave no row.'],
        'staff_own_org' => ['SS-1, SS-5 (EXPL-1..3)', 'A staff user force-purged or lifted a hold on a service of an organization they belong to',
            'The route (customer or staff API) is not recorded; the reason given is in the audit row.'],
        'partner_payouts' => ['P1, P2', 'A partner payout above the commissions allocated to it, or to an IBAN no earlier paid payout used',
            'A changed IBAN may be the partner\'s own new account: only the partner\'s confirmation proves it.'],
    ];

    private const FILE_ACTIONS = ['file.save', 'file.mkdir', 'file.delete', 'file.rename', 'file.copy', 'file.chmod', 'file.archive', 'file.extract'];

    private const CRON_ACTIONS = ['cron.create', 'cron.update'];

    /** Absolute paths a site's scheduled command may name without reaching another tenant: binaries, PHP builds, null device. */
    private const SYSTEM_PREFIXES = ['/usr/', '/bin/', '/sbin/', '/opt/', '/www/server/php/', '/www/server/onhost/', '/tmp/', '/dev/null', '/dev/stdout', '/dev/stderr'];

    private const LIST_LIMIT = 25;

    protected $signature = 'onhost:forensics:lookback
        {--since= : only events from this moment (ISO date); default: the whole history}
        {--until= : only events up to this moment (ISO date); default: now}
        {--source=* : one or more sources (default: all): owner_demotion, game_panel_identity, discord_after_removal, aapanel_outside_root, token_cross_org, staff_own_org, partner_payouts}
        {--json : print the report as JSON instead of a table}';

    protected $description = 'Read-only forensic look-back: did anybody use the permission holes of Phase 0 (TASK-0038)';

    private ?CarbonImmutable $since = null;

    private CarbonImmutable $until;

    /** @var array<string, list<array{kind:string, user:string, at:CarbonImmutable}>> */
    private array $membershipEvents = [];

    /** @var array<string, ?array{created_at:CarbonImmutable, expires_at:?CarbonImmutable, role:string}> */
    private array $memberships = [];

    /** @var array<string, bool> */
    private array $staff = [];

    /** @var array<string, true> the (organization, person) pairs the current source judged a membership for */
    private array $judged = [];

    public function handle(): int
    {
        $problem = $this->readOptions();
        if ($problem !== null) {
            $this->error($problem);

            return self::INVALID;
        }
        $selected = array_values(array_unique(array_map('strval', (array) $this->option('source')))) ?: array_keys(self::SOURCES);

        DB::beginTransaction(); // nothing below writes; rolling back anyway means no later change can make a look-back leave a trace
        try {
            $sources = array_map(fn (string $key) => $this->source($key), $selected);
            $coverage = $this->coverage();
        } finally {
            DB::rollBack();
        }
        $hits = array_sum(array_map(fn (array $s) => count($s['hits']), $sources));
        $report = [
            'command' => 'onhost:forensics:lookback', 'generated_at' => CarbonImmutable::now()->toIso8601String(), 'read_only' => true,
            'window' => ['since' => $this->since?->toIso8601String(), 'until' => $this->until->toIso8601String()],
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
        $this->judged = [];
        $found = match ($key) {
            'owner_demotion' => $this->ownerDemotion(),
            'game_panel_identity' => $this->gamePanelIdentity(),
            'discord_after_removal' => $this->discordAfterRemoval(),
            'aapanel_outside_root' => $this->aaPanelOutsideRoot(),
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
        $owners = DB::table('users')->whereIn('id', $organizations->pluck('owner_user_id')->filter()->unique()->values()->all())->pluck('email', 'id')->map(fn ($e) => mb_strtolower((string) $e));
        $invitations = DB::table('organization_invitations')->whereNotNull('accepted_at')->where('role_key', '!=', 'owner')->whereBetween('accepted_at', $this->window())->orderBy('accepted_at')->get();
        $hits = [];
        $unmatched = [];
        foreach ($invitations as $invitation) {
            $owner = (string) ($organizations[$invitation->organization_id]->owner_user_id ?? '');
            if ($owner !== '' && ($owners[$owner] ?? null) === mb_strtolower((string) $invitation->email)) {
                $hits[] = ['kind' => 'owner_accepted_lower_role', 'confidence' => 'confirmed', 'organization_id' => $invitation->organization_id, 'user_id' => $owner,
                    'invitation_id' => $invitation->id, 'role' => $invitation->role_key, 'at' => $this->iso($invitation->accepted_at), 'audit_event_id' => null,
                    'current_role' => $this->currentMembership($invitation->organization_id, $owner)['role'] ?? null];
            } else {
                $unmatched[] = mb_strtolower((string) $invitation->email);
            }
        }
        $nobody = $this->addressesWithoutAccount(array_values(array_unique($unmatched)));
        $transfers = DB::table('audit_events')->where('action', 'organization.ownership.transfer')->orderBy('created_at')->get(['organization_id', 'detail', 'created_at']);
        $attaches = 0;
        foreach ($this->auditRows(['organization.member.attach']) as $row) {
            $attaches++;
            $detail = $this->json($row->detail);
            $organization = $organizations[$row->organization_id] ?? null;
            if ($organization === null || (string) ($detail['user_id'] ?? '') !== (string) $organization->owner_user_id || (string) ($detail['role'] ?? 'owner') === 'owner') {
                continue;
            }
            $at = CarbonImmutable::parse($row->created_at);
            foreach ($hits as $i => $hit) { // the same acceptance, seen twice: the audit row is its evidence
                if ($hit['organization_id'] === $row->organization_id && $hit['audit_event_id'] === null && abs(CarbonImmutable::parse($hit['at'])->diffInSeconds($at)) <= 60) {
                    $hits[$i]['audit_event_id'] = $row->id;

                    continue 2;
                }
            }
            $since = $transfers->where('organization_id', $row->organization_id)->filter(fn ($t) => ($this->json($t->detail)['to'] ?? null) === $organization->owner_user_id)->last()->created_at ?? $organization->created_at;
            if ($since === null || $at->greaterThanOrEqualTo(CarbonImmutable::parse($since))) { // an attach before they became the owner is history, not a demotion
                $hits[] = ['kind' => 'owner_attached_lower_role', 'confidence' => 'confirmed', 'organization_id' => $row->organization_id, 'user_id' => $organization->owner_user_id,
                    'role' => (string) ($detail['role'] ?? ''), 'at' => $at->toIso8601String(), 'audit_event_id' => $row->id,
                    'current_role' => $this->currentMembership($row->organization_id, (string) $organization->owner_user_id)['role'] ?? null];
            }
        }
        $explained = array_column($hits, 'organization_id');
        foreach ($organizations as $organization) { // what the owner holds today, whatever put it there
            $role = $this->currentMembership((string) $organization->id, (string) $organization->owner_user_id)['role'] ?? null;
            if ($role !== 'owner' && ! in_array($organization->id, $explained, true)) {
                $hits[] = ['kind' => 'owner_membership_not_owner', 'confidence' => 'confirmed', 'organization_id' => $organization->id, 'user_id' => $organization->owner_user_id, 'current_role' => $role];
            }
        }
        $unknowns = $nobody > 0 ? ["{$nobody} accepted invitation".($nobody === 1 ? '' : 's').' went to an address that belongs to no account any more: who accepted cannot be told.'] : [];

        return ['checked' => ['organization_invitations' => $invitations->count(), 'audit_events' => $attaches + $transfers->count(), 'organizations' => $organizations->count(), 'organization_memberships' => $organizations->count()],
            'hits' => $hits, 'unknowns' => $unknowns];
    }

    /** @param list<string> $emails */
    private function addressesWithoutAccount(array $emails): int
    {
        $known = [];
        foreach (array_chunk($emails, 500) as $chunk) {
            $known = array_merge($known, DB::table('users')->whereIn(DB::raw('lower(email)'), $chunk)->selectRaw('lower(email) as e')->pluck('e')->map(fn ($e) => (string) $e)->all());
        }

        return count(array_diff($emails, $known));
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
        $links = DB::table('discord_links')->whereNotNull('linked_at')->get(['id', 'organization_id', 'user_id', 'state', 'last_used_at', 'commands']);
        $hits = [];
        $unknown = 0;
        $open = [];
        foreach ($links as $link) {
            if ($link->state === 'linked' && $this->membershipAt((string) $link->organization_id, (string) $link->user_id, CarbonImmutable::now())[0] === false) {
                $open[] = $link->id;
            }
            if ($link->last_used_at === null || ! $this->inWindow($link->last_used_at)) {
                continue;
            }
            [$member, $ended] = $this->membershipAt((string) $link->organization_id, (string) $link->user_id, CarbonImmutable::parse($link->last_used_at));
            if ($member === false) { // last_used_at is the latest use: when it lies after the removal, the link was used after it
                $hits[] = ['kind' => 'discord_used_after_removal', 'confidence' => 'confirmed', 'link_id' => $link->id, 'organization_id' => $link->organization_id, 'user_id' => $link->user_id,
                    'removed_at' => $ended?->toIso8601String(), 'last_used_at' => $this->iso($link->last_used_at), 'commands' => (int) $link->commands, 'link_state' => $link->state];
            } elseif ($member === null) {
                $unknown++;
            }
        }
        $audited = 0;
        foreach (DB::table('audit_events')->where('session_id', 'like', 'discord:%')->whereBetween('created_at', $this->window())->whereNotNull('organization_id')->lazyById(1000, 'id') as $row) {
            $audited++;
            [$member, $ended] = $this->membershipAt((string) $row->organization_id, (string) $row->actor_id, CarbonImmutable::parse($row->created_at));
            if ($member === false) {
                $hits[] = ['kind' => 'discord_command_after_removal', 'confidence' => 'confirmed', 'audit_event_id' => $row->id, 'organization_id' => $row->organization_id, 'user_id' => $row->actor_id,
                    'action' => (string) ($this->json($row->detail)['action'] ?? $row->action), 'at' => $this->iso($row->created_at), 'removed_at' => $ended?->toIso8601String()];
            } elseif ($member === null) {
                $unknown++;
            }
        }

        return ['checked' => ['discord_links' => $links->count(), 'audit_events' => $audited, 'organization_memberships' => count($this->judged)], 'hits' => $hits,
            'unknowns' => $unknown > 0 ? ["{$unknown} Discord use(s) by a person whose membership history is not recorded: cannot say whether they were still a member."] : [],
            'notes' => $open !== [] ? [count($open).' Discord link(s) are still linked for people who are no longer members (an open door, no use after the removal seen): '.$this->ids($open).' — TASK-0035 revokes them.'] : []];
    }

    // ── PA-02: aaPanel file operations outside the site root ─────────────────────────────────────────────────

    /** @return array{checked:array<string,int>, hits:list<array<string,mixed>>, unknowns:list<string>} */
    private function aaPanelOutsideRoot(): array
    {
        $instances = DB::table('provider_instances')->where('provider', 'aapanel')->pluck('id')->all();
        $services = DB::table('services')->whereIn('provider_instance_id', $instances)->pluck('id')->all();
        $roots = DB::table('provider_bindings')->where('remote_type', 'site')->whereIn('provider_instance_id', $instances)->get(['service_id', 'meta'])
            ->mapWithKeys(fn ($b) => [(string) $b->service_id => (string) ($this->json($b->meta)['path'] ?? '')])->filter();
        $operations = DB::table('operations')->where('kind', 'service.action')->whereIn('desired->action', [...self::FILE_ACTIONS, ...self::CRON_ACTIONS])
            ->whereBetween('created_at', $this->window())->where(fn ($q) => $q->whereIn('provider_instance_id', $instances)->orWhereIn('service_id', $services))
            ->orderBy('created_at')->get(['id', 'organization_id', 'service_id', 'state', 'actor_id', 'desired', 'created_at']);
        $hits = [];
        $extractions = 0;
        $noRoot = 0;
        foreach ($operations as $operation) {
            $desired = $this->json($operation->desired);
            $action = (string) ($desired['action'] ?? '');
            $root = $roots[$operation->service_id] ?? null;
            $base = ['operation_id' => $operation->id, 'service_id' => $operation->service_id, 'organization_id' => $operation->organization_id, 'action' => $action,
                'state' => $operation->state, 'actor_id' => $operation->actor_id, 'at' => $this->iso($operation->created_at)];
            if (in_array($action, self::CRON_ACTIONS, true)) {
                $command = (string) ($desired['command'] ?? '');
                if ($root === null && str_contains($command, '/')) {
                    $noRoot++;
                }
                $outside = array_values(array_filter($this->absolutePaths($command), fn (string $p) => $root !== null && ! $this->systemPath($p) && $this->outsideRoot($p, $root)));
                $climbs = preg_match('~(^|[\s/=\'"])\.\.(/|\s|$)~', $command) === 1;
                $symlink = preg_match('~(^|[\s;&|(])ln\s+(-\w*s\w*|--symbolic)\b~', $command) === 1;
                if ($outside !== [] || $climbs || $symlink) { // only the offending paths leave the report: a command may carry keys in a URL
                    $hits[] = ['kind' => 'cron_reaches_outside_root', 'confidence' => 'possible'] + $base + ['paths' => array_map(fn ($p) => $this->clean($p), $outside), 'symlink' => $symlink, 'climbs' => $climbs];
                }

                continue;
            }
            if ($action === 'file.extract' && $operation->state === 'SUCCEEDED') {
                $extractions++;
            }
            $outside = array_values(array_filter($this->filePaths($desired), fn (string $p) => $this->outsideRoot($p, $root)));
            if ($outside !== []) {
                $hits[] = ['kind' => 'path_outside_root', 'confidence' => $operation->state === 'SUCCEEDED' ? 'confirmed' : 'attempt'] + $base + ['paths' => array_map(fn ($p) => $this->clean($p), $outside)];
            }
        }
        $uploads = 0;
        foreach ($services === [] ? [] : $this->auditRows(['service.file.upload']) as $row) {
            if (! in_array($row->resource_id, $services, true)) {
                continue;
            }
            $uploads++;
            $path = (string) ($this->json($row->detail)['path'] ?? '');
            if ($path !== '' && $this->outsideRoot($path, $roots[$row->resource_id] ?? null)) {
                $hits[] = ['kind' => 'path_outside_root', 'confidence' => 'confirmed', 'audit_event_id' => $row->id, 'service_id' => $row->resource_id, 'organization_id' => $row->organization_id,
                    'action' => 'file.upload', 'actor_id' => $row->actor_id, 'at' => $this->iso($row->created_at), 'paths' => [$this->clean($path)]];
            }
        }
        $unknowns = [];
        if ($extractions > 0) {
            $unknowns[] = "{$extractions} archive extraction".($extractions === 1 ? '' : 's').' ran on aaPanel: what the archive held (a symlink, an absolute or ../ entry) is not stored — only the node shows it.';
        }
        if ($noRoot > 0) {
            $unknowns[] = "{$noRoot} scheduled command(s) belong to a service whose site root is not stored: their absolute paths cannot be judged.";
        }

        return ['checked' => ['operations' => $operations->count(), 'audit_events' => $uploads, 'provider_bindings' => $roots->count()], 'hits' => $hits, 'unknowns' => $unknowns];
    }

    /** @param array<string,mixed> $desired @return list<string> */
    private function filePaths(array $desired): array
    {
        $paths = array_merge(array_map(fn ($k) => $desired[$k] ?? null, ['path', 'from', 'to', 'target']), (array) ($desired['paths'] ?? []));

        return array_values(array_filter($paths, fn ($p) => is_string($p) && $p !== ''));
    }

    /** Lexically: a NUL, a `..` that climbs above the root, or an absolute path not under the root (stored paths are relative). */
    private function outsideRoot(string $path, ?string $root): bool
    {
        $path = str_replace('\\', '/', $path);
        if (str_contains($path, "\0")) {
            return true;
        }
        if (str_starts_with($path, '/')) {
            $normal = $this->normalize($path);

            return $root === null || $normal === null || ($normal !== rtrim($root, '/') && ! str_starts_with($normal, rtrim($root, '/').'/'));
        }

        return $this->normalize('/'.$path) === null;
    }

    /** `/a/b/../c` → `/a/c`; null when `..` climbs above `/`. */
    private function normalize(string $absolute): ?string
    {
        $stack = [];
        foreach (explode('/', $absolute) as $segment) {
            if ($segment === '..') {
                if ($stack === []) {
                    return null;
                }
                array_pop($stack);
            } elseif ($segment !== '' && $segment !== '.') {
                $stack[] = $segment;
            }
        }

        return '/'.implode('/', $stack);
    }

    /** @return list<string> absolute paths named in a shell command (not the `//` of a URL) */
    private function absolutePaths(string $command): array
    {
        preg_match_all('~(?<![^\s\'"=<>])/[^\s\'"`;|&<>()]*~', $command, $matches);

        return array_values(array_unique($matches[0]));
    }

    private function systemPath(string $path): bool
    {
        foreach (self::SYSTEM_PREFIXES as $prefix) {
            if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) {
                return ! str_contains($path, '..');
            }
        }

        return false;
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
        $unknowns = array_values(array_filter([
            $missing > 0 ? "{$missing} audit row".($missing === 1 ? '' : 's').' name a token that no longer exists: its organization cannot be read.' : null,
            $unbound > 0 ? "{$unbound} audit row".($unbound === 1 ? '' : 's').' were written with a token that carries no organization (made before tokens were bound to one).' : null,
        ]));

        return ['checked' => ['audit_events' => $rows, 'personal_access_tokens' => count($tokens)], 'hits' => $hits, 'unknowns' => $unknowns];
    }

    // ── SS-1 / SS-5: staff acting on their own organization through the is_staff shortcuts ─────────────────

    /** @return array{checked:array<string,int>, hits:list<array<string,mixed>>, unknowns:list<string>} */
    private function staffOwnOrg(): array
    {
        $hits = [];
        $lifts = 0;
        foreach ($this->auditRows(['service.hold.lift']) as $row) {
            $lifts++;
            $detail = $this->json($row->detail);
            // a customer lifts the one hold it names (a string); the staff path lifts them all (a list) — ServiceService::liftHolds
            if ($row->actor_type !== 'user' || $row->organization_id === null || ! (is_array($detail['lifted'] ?? null) || $this->isStaff((string) $row->actor_id))) {
                continue;
            }
            if ($this->membershipAt((string) $row->organization_id, (string) $row->actor_id, CarbonImmutable::parse($row->created_at))[0] === true) {
                $hits[] = ['kind' => 'staff_hold_lift_own_org', 'confidence' => 'confirmed', 'audit_event_id' => $row->id, 'user_id' => $row->actor_id, 'organization_id' => $row->organization_id,
                    'service_id' => $row->resource_id, 'lifted' => $detail['lifted'] ?? null, 'at' => $this->iso($row->created_at)];
            }
        }
        $purges = DB::table('operations')->where('kind', 'service.action')->whereIn('desired->action', ['purge', 'terminate'])->where('actor_type', 'user')
            ->whereBetween('created_at', $this->window())->orderBy('created_at')->get(['id', 'organization_id', 'service_id', 'actor_id', 'state', 'desired', 'created_at']);
        foreach ($purges as $operation) {
            $desired = $this->json($operation->desired);
            $forced = (($desired['action'] ?? '') === 'purge' && ! empty($desired['force'])) || ($desired['archive_before_delete'] ?? true) === false;
            if ($forced && $operation->organization_id !== null && $this->membershipAt((string) $operation->organization_id, (string) $operation->actor_id, CarbonImmutable::parse($operation->created_at))[0] === true) {
                $hits[] = ['kind' => 'staff_force_purge_own_org', 'confidence' => 'confirmed', 'operation_id' => $operation->id, 'user_id' => $operation->actor_id, 'organization_id' => $operation->organization_id,
                    'service_id' => $operation->service_id, 'action' => $desired['action'] ?? null, 'archive_skipped' => ($desired['archive_before_delete'] ?? true) === false,
                    'state' => $operation->state, 'staff_now' => $this->isStaff((string) $operation->actor_id), 'at' => $this->iso($operation->created_at)];
            }
        }

        return ['checked' => ['audit_events' => $lifts, 'operations' => $purges->count(), 'organization_memberships' => count($this->judged)], 'hits' => $hits, 'unknowns' => []];
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
        foreach ($payouts as $payout) {
            if ($payout->state === 'rejected') { // a rejection hands the commissions back: nothing left the platform
                continue;
            }
            $rows = $allocated[$payout->id] ?? new Collection;
            $amount = $sum = null;
            try {
                $amount = Money::minor((int) $payout->amount_minor, (string) $payout->currency);
                $sum = $rows->reduce(fn (Money $carry, $r) => $carry->add(Money::minor((int) $r->total, (string) $r->currency)), Money::zero($amount->currency));
            } catch (Throwable) { // another currency among its commissions, or one the platform does not know
                $unknown++;
            }
            if ($amount !== null && $sum !== null && $amount->greaterThan($sum)) {
                $hits[] = ['kind' => 'payout_above_allocated', 'confidence' => 'confirmed', 'payout_id' => $payout->id, 'number' => $payout->number, 'partner_id' => $payout->partner_id,
                    'amount_minor' => $amount->minor, 'allocated_minor' => $sum->minor, 'excess_minor' => $amount->subtract($sum)->minor, 'currency' => $amount->currency->value,
                    'state' => $payout->state, 'requested_at' => $this->iso($payout->requested_at)];
            }
            $confirmed = collect($paid[$payout->partner_id] ?? [])->filter(fn ($p) => $p->id !== $payout->id && $p->requested_at < $payout->requested_at)
                ->map(fn ($p) => $this->normalIban((string) $p->iban))->unique()->values();
            $iban = $this->normalIban((string) $payout->iban);
            if ($payout->method === 'bank_transfer' && $iban !== '' && $confirmed->isNotEmpty() && ! $confirmed->contains($iban)) {
                $requester = $requesters[$payout->id] ?? null;
                $hits[] = ['kind' => 'payout_iban_changed', 'confidence' => 'possible', 'payout_id' => $payout->id, 'number' => $payout->number, 'partner_id' => $payout->partner_id,
                    'iban' => $this->maskIban($iban), 'confirmed_ibans' => $confirmed->map(fn (string $i) => $this->maskIban($i))->all(), 'state' => $payout->state,
                    'requested_by' => $requester, 'requested_by_owner' => $requester === null ? null : $requester === ($partnerOwners[$partners[$payout->partner_id]->organization_id ?? ''] ?? null),
                    'requested_at' => $this->iso($payout->requested_at)];
            }
        }

        return ['checked' => ['partner_payouts' => $payouts->count(), 'partner_commissions' => $allocated->count(), 'audit_events' => $requesters->count()], 'hits' => $hits,
            'unknowns' => $unknown > 0 ? ["{$unknown} payout(s) mix currencies with their commissions: the allocated sum cannot be compared."] : []];
    }

    private function normalIban(string $iban): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', $iban));
    }

    /** The country and the last four characters: enough to tell accounts apart in a report, not enough to use one. */
    private function maskIban(string $iban): string
    {
        return strlen($iban) <= 8 ? '…' : substr($iban, 0, 4).'…'.substr($iban, -4);
    }

    // ── membership history: the audit's attach/remove rows, the current membership as its last word ─────────

    /**
     * Was the person a member of the organization at that moment? null when nothing recorded says either way.
     *
     * @return array{0:?bool, 1:?CarbonImmutable} [member, when the membership had ended]
     */
    private function membershipAt(string $organizationId, string $userId, CarbonImmutable $at): array
    {
        $this->judged["{$organizationId}#{$userId}"] = true;
        $last = null;
        foreach ($this->membershipEvents($organizationId) as $event) {
            if ($event['user'] === $userId && $event['at']->lessThanOrEqualTo($at)) {
                $last = $event;
            }
        }
        $current = $this->currentMembership($organizationId, $userId);
        if ($last !== null && $last['kind'] === 'remove') {
            return [false, $last['at']];
        }
        if ($current !== null && $current['expires_at'] !== null && $current['expires_at']->lessThan($at)) { // access that ended on its date (H343)
            return [false, $current['expires_at']];
        }
        if ($last !== null) {
            return [true, null];
        }

        return $current === null ? [null, null] : [$current['created_at']->lessThanOrEqualTo($at), null];
    }

    /** @return list<array{kind:string, user:string, at:CarbonImmutable}> */
    private function membershipEvents(string $organizationId): array
    {
        return $this->membershipEvents[$organizationId] ??= DB::table('audit_events')->where('organization_id', $organizationId)
            ->whereIn('action', ['organization.member.attach', 'organization.member.remove'])->orderBy('created_at')->orderBy('id')->get(['action', 'detail', 'created_at'])
            ->map(fn ($row) => ['kind' => $row->action === 'organization.member.remove' ? 'remove' : 'attach', 'user' => (string) ($this->json($row->detail)['user_id'] ?? ''), 'at' => CarbonImmutable::parse($row->created_at)])
            ->all();
    }

    /** @return ?array{created_at:CarbonImmutable, expires_at:?CarbonImmutable, role:string} */
    private function currentMembership(string $organizationId, string $userId): ?array
    {
        $key = "{$organizationId}#{$userId}";
        if (! array_key_exists($key, $this->memberships)) {
            $row = DB::table('organization_memberships')->where('organization_id', $organizationId)->where('user_id', $userId)->first(['created_at', 'expires_at', 'role_key']);
            $this->memberships[$key] = $row === null ? null : ['created_at' => CarbonImmutable::parse($row->created_at ?? '1970-01-01'),
                'expires_at' => $row->expires_at === null ? null : CarbonImmutable::parse($row->expires_at), 'role' => (string) $row->role_key];
        }

        return $this->memberships[$key];
    }

    private function isStaff(string $userId): bool
    {
        return $this->staff[$userId] ??= (bool) DB::table('users')->where('id', $userId)->value('is_staff');
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

    /** Customer text in the report loses control and bidi characters and stays short: it cannot drive the operator's terminal. */
    private function clean(string $text): string
    {
        return mb_substr((string) (preg_replace('/[\p{Cc}\p{Cf}]/u', '?', $text) ?? '?'), 0, 200);
    }

    /** @param array<string,mixed> $report */
    private function printTable(array $report): void
    {
        $this->line('Forensic look-back (read-only) · window '.($report['window']['since'] ?? 'whole history').' → '.$report['window']['until']);
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
