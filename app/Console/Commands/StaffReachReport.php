<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Authorization\Authorizer;

/**
 * TASK-0039 (permission program P0-08, IF-4): the shadow log of staff reach, and whether enforcing it is due.
 *
 * Every allow a staff role or a JIT elevation gave on a CUSTOMER permission is written to security_events `authz.staff_reach`
 * (Authorizer::shadow, one row per person, key, organization and day). Each row is a staff workflow that still leans on a
 * customer key and would break once `ONHOST_STAFF_REACH_ENFORCED=true`: it needs a staff key of its own first (P0-15). The
 * release switches enforcement on after this report has stayed empty for 7 days. Read-only.
 */
final class StaffReachReport extends Command
{
    protected $signature = 'operator:authz:staff-reach {--days=7 : the window that has to be empty before enforcement} {--dry-run : accepted for the runbook\'s uniform syntax; the command never changes anything}';

    protected $description = 'Report staff roles and JIT elevations reaching customer permissions (the P0-08 shadow log) and whether enforcing is due';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $rows = DB::table('security_events')->where('kind', Authorizer::SHADOW_KIND)->where('created_at', '>=', now()->subDays($days))->orderByDesc('created_at')->get();
        $grouped = [];
        foreach ($rows as $row) {
            $detail = json_decode((string) $row->detail, true);
            $detail = is_array($detail) ? $detail : [];
            $key = implode('|', [(string) ($detail['permission'] ?? '?'), implode(',', (array) ($detail['roles'] ?? [])), (string) ($detail['route'] ?? '—')]);
            $grouped[$key] ??= [(string) ($detail['permission'] ?? '?'), implode(', ', (array) ($detail['roles'] ?? [])), (string) ($detail['route'] ?? '—'), 0, []];
            $grouped[$key][3]++;
            $grouped[$key][4][(string) ($row->user_id ?? $detail['principal_id'] ?? '?')] = true;
        }
        $this->table(['customer permission', 'staff roles', 'route', 'days × people × orgs', 'people'], array_map(fn (array $g) => [$g[0], $g[1], $g[2], (string) $g[3], (string) count($g[4])], array_values($grouped)));
        $enforced = Authorizer::enforced();
        if ($rows->isEmpty()) {
            $this->info($enforced ? "Enforced; nothing was refused-in-shadow in the last {$days} days." : "Empty for {$days} days: ONHOST_STAFF_REACH_ENFORCED=true is due (program P0-08/P0-15).");

            return self::SUCCESS;
        }
        $this->warn(sprintf('%d entries in the last %d days: give these workflows staff keys (P0-15) before ONHOST_STAFF_REACH_ENFORCED=true.%s', $rows->count(), $days, $enforced ? ' Enforcement is ON — these are from before it.' : ''));

        return self::SUCCESS;
    }
}
