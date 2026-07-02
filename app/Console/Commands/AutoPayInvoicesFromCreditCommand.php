<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Billing\Actions\PayInvoiceWithCreditAction;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Exceptions\InsufficientCreditException;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\CreditLedger;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

/**
 * Attempts to pay all open (Sent/Overdue) invoices from customers' credit balance.
 *
 * Runs daily. Safe to re-run: PayInvoiceWithCreditAction is idempotent.
 * Skips invoices whose customer has insufficient credit — no partial deductions.
 */
class AutoPayInvoicesFromCreditCommand extends Command
{
    protected $signature = 'billing:auto-pay-from-credit
                            {--dry-run : Show what would be paid without actually paying}';

    protected $description = 'Auto-pay open invoices from customer credit balance';

    public function __construct(
        private readonly PayInvoiceWithCreditAction $payAction,
        private readonly CreditLedger $ledger,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $invoices = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
            ->where('purpose', '!=', 'credit_topup')
            ->with('customer')
            ->get();

        $paid    = 0;
        $skipped = 0;
        $failed  = 0;

        foreach ($invoices as $invoice) {
            $customer = $invoice->customer;

            if ($customer === null) {
                $this->warn("Invoice {$invoice->number}: no customer — skipped.");
                $skipped++;
                continue;
            }

            $balance = $this->ledger->getBalance($customer);
            $total   = $invoice->total;

            if ($balance->isLessThan($total)) {
                $this->line("Invoice {$invoice->number}: insufficient credit ({$balance->getAmount()} < {$total->getAmount()}) — skipped.");
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->info("[dry-run] Would pay {$invoice->number} ({$total->getAmount()} {$total->getCurrency()->getCurrencyCode()}) for customer #{$customer->id}");
                $paid++;
                continue;
            }

            try {
                $this->payAction->execute($invoice);
                $this->info("Paid {$invoice->number} from credit.");
                $paid++;
            } catch (InsufficientCreditException) {
                $this->line("Invoice {$invoice->number}: insufficient credit (race condition) — skipped.");
                $skipped++;
            } catch (InvalidArgumentException $e) {
                $this->warn("Invoice {$invoice->number}: {$e->getMessage()} — skipped.");
                $skipped++;
            } catch (Throwable $e) {
                $this->error("Invoice {$invoice->number}: unexpected error — {$e->getMessage()}");
                $failed++;
            }
        }

        $this->line("Done. Paid: {$paid} | Skipped: {$skipped} | Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
