<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Partner\Enums\CommissionStatus;
use App\Domains\Partner\Models\PartnerCommission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Moves pending commissions to approved once their hold period (eligible_at) has elapsed.
 *
 * Excluded:
 *  - invoices with status cancelled (refunded/cancelled orders)
 *  - already-approved, paid, rejected or cancelled commissions
 *
 * Idempotent: re-running the command for the same set of commissions is a no-op.
 */
class ApproveEligibleCommissionsCommand extends Command
{
    protected $signature   = 'partner:approve-eligible-commissions {--dry-run : Preview without making changes}';
    protected $description = 'Auto-approve pending partner commissions that have passed their hold period.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $now    = now();

        // Find pending commissions past eligible_at, excluding those tied to cancelled invoices
        $query = PartnerCommission::query()
            ->where('status', CommissionStatus::Pending->value)
            ->where('eligible_at', '<=', $now)
            ->where(function ($q): void {
                // Include commissions without an invoice link, or with non-cancelled invoices
                $q->whereNull('invoice_id')
                  ->orWhereHas('invoice', fn ($iq) => $iq->whereNotIn('status', [
                      InvoiceStatus::Cancelled->value,
                  ]));
            });

        $count = $query->count();

        if ($count === 0) {
            $this->info('No eligible commissions to approve.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("DRY RUN: Would approve {$count} commission(s). No changes made.");
            return self::SUCCESS;
        }

        $approved = 0;
        $query->chunkById(100, function ($commissions) use (&$approved, $now): void {
            foreach ($commissions as $commission) {
                DB::transaction(function () use ($commission, $now): void {
                    $commission->update([
                        'status'      => CommissionStatus::Approved,
                        'approved_at' => $now,
                        'notes'       => ($commission->notes ? $commission->notes . '; ' : '') . 'Auto-approved by scheduler',
                    ]);

                    activity('partner')
                        ->performedOn($commission)
                        ->withProperties([
                            'auto_approved_at' => $now->toIso8601String(),
                            'eligible_at'      => $commission->eligible_at?->toIso8601String(),
                        ])
                        ->log('commission.auto_approved');
                });

                $approved++;
            }
        });

        $this->info("Approved {$approved} commission(s).");
        return self::SUCCESS;
    }
}
