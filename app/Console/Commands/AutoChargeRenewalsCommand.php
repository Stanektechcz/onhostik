<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Billing\Actions\AutoChargeSavedMethodAction;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use Illuminate\Console\Command;

/**
 * Charges open renewal invoices against the customer's saved payment method
 * (audit D39).
 *
 * Runs after `billing:create-renewals`, so a renewal invoice issued in the
 * morning is settled the same day for customers who opted into auto-pay.
 * Everyone else is unaffected and pays by hand as before.
 */
class AutoChargeRenewalsCommand extends Command
{
    protected $signature   = 'billing:auto-charge-renewals {--dry-run : Report what would be charged}';
    protected $description = 'Settle open renewal invoices from saved payment methods where auto-pay is set up.';

    public function handle(AutoChargeSavedMethodAction $charge): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $invoices = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Overdue->value])
            ->whereNull('paid_at')
            // Only renewals — a first order goes through checkout, where the
            // customer chooses how to pay.
            ->whereNotNull('renewal_service_id')
            ->with('customer')
            ->get();

        $tally = ['charged' => 0, 'declined' => 0, 'skipped_no_method' => 0, 'skipped_not_payable' => 0];

        foreach ($invoices as $invoice) {
            if ($dryRun) {
                $this->line("Would attempt invoice #{$invoice->number}");

                continue;
            }

            $result = $charge->execute($invoice);
            $tally[$result]++;
        }

        if ($dryRun) {
            $this->info("{$invoices->count()} renewal invoices would be attempted.");

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Charged %d · declined %d · no saved method %d · not payable %d',
            $tally['charged'],
            $tally['declined'],
            $tally['skipped_no_method'],
            $tally['skipped_not_payable'],
        ));

        return self::SUCCESS;
    }
}
