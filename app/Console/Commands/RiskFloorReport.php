<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Models\PersonalAccessToken;
use Onhost\Domain\Organizations\Models\Organization;

/**
 * Read-only look before the risk floor goes live (TASK-0037, permission program IF-13): which calls in the last N days
 * succeeded WITHOUT a step-up under a permission whose risk is HIGH or more — they ran below the catalogue because the command
 * declared itself lower, and from now on they ask for a fresh step-up.
 *
 * An API token or a service account cannot step up at all (StepUpService refuses `token:*` sessions), so their calls will be
 * refused: those are listed one by one, to tell the owners before the release. People in the portal will see the step-up
 * dialog: counted per action. It writes nothing.
 */
final class RiskFloorReport extends Command
{
    protected $signature = 'onhost:iam:risk-floor-report {--days=30 : how far back to look}';

    protected $description = 'Read-only: tokens, service accounts and people that ran an operation below its catalogue risk (will need a step-up)';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $raised = array_values(array_filter(PermissionCatalog::keys(), fn (string $key) => PermissionCatalog::floor($key) !== PermissionCatalog::NORMAL));
        $rows = DB::table('audit_events')
            ->where('result', 'succeeded')->where('created_at', '>=', now()->subDays($days))
            ->whereIn('permission', $raised)->whereNull('step_up_method')
            ->whereIn('actor_type', ['user', 'service_account'])
            ->where('action', 'not like', 'iam.approval.%') // opening a request names the permission it is for; it is not the action
            ->groupBy('actor_type', 'actor_id', 'session_id', 'organization_id', 'action', 'permission')
            ->orderBy('organization_id')->orderBy('action')
            ->get(['actor_type', 'actor_id', 'session_id', 'organization_id', 'action', 'permission', DB::raw('count(*) as uses'), DB::raw('max(created_at) as last_used')]);

        $automated = $rows->filter(fn ($r) => $r->actor_type === 'service_account' || str_starts_with((string) $r->session_id, 'token:'));
        $people = $rows->reject(fn ($r) => $r->actor_type === 'service_account' || str_starts_with((string) $r->session_id, 'token:'));
        $tokenIds = $automated->map(fn ($r) => str_starts_with((string) $r->session_id, 'token:') ? substr((string) $r->session_id, 6) : null)->filter()->unique()->values()->all();
        $tokens = PersonalAccessToken::query()->whereIn('id', $tokenIds)->pluck('name', 'id');
        $organizations = Organization::query()->whereIn('id', $rows->pluck('organization_id')->filter()->unique()->values()->all())->pluck('name', 'id');

        $this->line("Operations that succeeded without a step-up in the last {$days} days under a permission rated HIGH or more (read-only).");
        $this->newLine();
        $this->info('API tokens and service accounts — they cannot step up; these calls will be refused (step_up_required). Tell the owners first ('.$automated->count().'):');
        $this->table(['Token / account', 'Name', 'Organization', 'Actor', 'Action', 'Permission', 'Uses', 'Last used'], $automated->map(fn ($r) => [
            $r->actor_type === 'service_account' ? 'service_account:'.$r->actor_id : (string) $r->session_id,
            (string) ($tokens[substr((string) $r->session_id, 6)] ?? ''),
            trim(($organizations[$r->organization_id] ?? '').' '.$r->organization_id),
            (string) $r->actor_id, (string) $r->action, (string) $r->permission, (int) $r->uses, (string) $r->last_used,
        ])->values()->all());

        $byAction = $people->groupBy('action')->map(fn ($group, $action) => [
            (string) $action, (string) $group->first()->permission, (int) $group->sum('uses'), $group->pluck('actor_id')->unique()->count(), $group->pluck('organization_id')->filter()->unique()->count(),
        ])->values()->all();
        $this->info('People in the portal or console — they will see the step-up dialog ('.count($byAction).' actions):');
        $this->table(['Action', 'Permission', 'Uses', 'People', 'Organizations'], $byAction);

        return self::SUCCESS;
    }
}
