<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Tax\VatHealth;
use Onhost\Domain\Tax\VatNumberChecks;

/**
 * The rule `tax.vies_recheck` (TASK-0031, D31.3c): a VIES answer counts for reverse charge 30 days, so the VIES-valid
 * numbers are asked again after 25. Off by default — it changes how existing customers are charged when a number lapses.
 * It never touches a number that was never checked or is unknown now, nor a row from before the check; those go through
 * `onhost:vat:verify --apply`, where the operator sees them first.
 */
final class VatRecheck extends Command
{
    public const RULE = 'tax.vies_recheck';

    protected $signature = 'onhost:vat:recheck {--limit=200} {--pause-ms=500 : pause between two VIES calls}';

    protected $description = 'Re-check in VIES the VAT numbers whose valid answer is about to stop counting (rule tax.vies_recheck, off by default)';

    public function handle(AutomationLedger $ledger, VatHealth $health, VatNumberChecks $checks): int
    {
        if ($ledger->off(self::RULE)) {
            $this->warn('switched off (console → automation: '.self::RULE.')');

            return self::SUCCESS;
        }
        $stats = ['checked' => 0, 'valid' => 0, 'invalid' => 0, 'unknown' => 0, 'skipped' => 0];
        $pause = max(0, (int) $this->option('pause-ms'));
        foreach ($health->staleValid()->orderBy('vat_checked_at')->limit(max(1, (int) $this->option('limit')))->get() as $organization) {
            if ($stats['checked'] > 0 && $pause > 0) {
                usleep($pause * 1000);
            }
            $outcome = $checks->check($organization, 'recheck', (int) config('onhost.vies.timeout_seconds', 8));
            $stats['checked']++;
            $stats[VatNumberChecks::tally($outcome)]++;
        }
        $ledger->record(self::RULE, $stats);
        $this->table(array_keys($stats), [array_values($stats)]);

        return self::SUCCESS;
    }
}
