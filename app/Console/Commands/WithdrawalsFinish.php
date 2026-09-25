<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Billing\WithdrawalPolicy;
use Onhost\Domain\Billing\WithdrawalService;
use Onhost\Domain\Provisioning\AutomationLedger;

/**
 * Finishes the consumer withdrawals still being unwound (TASK-0025): a suspension or cancellation a panel, a legal hold or
 * a frozen provisioning refused is asked for again, a refund waiting for the service to go off is made once it is off. It
 * never creates a withdrawal and never touches a service nobody withdrew from; it runs even while the rule is switched off,
 * because a withdrawal already accepted has to be completed within the statutory period. `--dry-run` lists only.
 */
final class WithdrawalsFinish extends Command
{
    protected $signature = 'onhost:withdrawals:finish {--dry-run : list the open withdrawals, change nothing} {--limit=200}';

    protected $description = 'Finish accepted consumer withdrawals: switch the service off, refund the unused part to the credit, cancel the service (retries refused steps)';

    public function handle(WithdrawalService $withdrawals, AutomationLedger $ledger): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $stats = $withdrawals->finishPending($dryRun, (int) $this->option('limit'));
        if (! $dryRun) {
            $ledger->record(WithdrawalPolicy::RULE, ['open' => $stats['open'], 'moved' => $stats['moved'], 'completed' => $stats['completed']]);
        }
        $this->table(['open', 'moved', 'completed'], [[$stats['open'], $stats['moved'], $stats['completed']]]);
        if ($stats['waiting'] !== []) {
            $this->table(['withdrawal', 'state', 'refused because'], array_map(fn (array $w) => [$w['id'], $w['state'], $w['error'] ?? '—'], $stats['waiting']));
        }
        $this->line($dryRun ? 'dry run: nothing was changed' : 'done');

        return self::SUCCESS;
    }
}
