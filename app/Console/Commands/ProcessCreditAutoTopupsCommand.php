<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Billing\Actions\AutoChargeSavedMethodAction;
use App\Domains\Billing\Actions\CreateCreditTopUpInvoiceAction;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Customer\Models\Customer;
use Brick\Money\Money;
use Illuminate\Console\Command;
use Throwable;

class ProcessCreditAutoTopupsCommand extends Command
{
    protected $signature   = 'billing:process-auto-topups';
    protected $description = 'Issue topup invoices for customers whose credit balance is below their configured threshold.';

    public function handle(
        CreditLedger $ledger,
        CreateCreditTopUpInvoiceAction $action,
        AutoChargeSavedMethodAction $autoCharge,
    ): int {
        $customers = Customer::query()
            ->whereNotNull('credit_auto_topup')
            ->get();

        $triggered = 0;
        $charged   = 0;
        $skipped   = 0;

        foreach ($customers as $customer) {
            $config = $customer->credit_auto_topup;

            if (! is_array($config)) {
                continue;
            }

            if (! array_key_exists('enabled', $config) || ! array_key_exists('threshold_minor', $config) || ! array_key_exists('topup_minor', $config)) {
                continue;
            }

            if (! $config['enabled']) {
                continue;
            }

            $thresholdMinor = (int) $config['threshold_minor'];
            $topupMinor     = (int) $config['topup_minor'];
            $currency       = $customer->preferred_currency->value;

            $balance        = $ledger->getBalance($customer);
            $balanceMinor   = $balance->getMinorAmount()->toInt();

            if ($balanceMinor >= $thresholdMinor) {
                $skipped++;
                continue;
            }

            // Skip if a pending topup invoice already exists to avoid duplicates
            $pendingExists = Invoice::query()
                ->where('customer_id', $customer->id)
                ->where('purpose', 'credit_topup')
                ->whereIn('status', ['sent', 'overdue'])
                ->exists();

            if ($pendingExists) {
                $skipped++;
                continue;
            }

            try {
                $amount  = Money::ofMinor($topupMinor, $currency);
                $invoice = $action->execute($customer, $amount);
                $triggered++;

                /*
                 | Issuing the invoice was only half the job: a customer who
                 | asked for automatic top-up did not ask for an e-mail telling
                 | them to go and pay. Try their saved card — the action itself
                 | is opt-in (default method only) and declines honestly when no
                 | merchant-initiated API is wired, so nothing is ever marked
                 | paid on a charge that did not happen.
                 */
                $outcome = $autoCharge->execute($invoice);

                if ($outcome === 'charged') {
                    $charged++;
                }

                $this->line("Topup invoice issued for customer #{$customer->id} (charge: {$outcome})");
            } catch (Throwable $e) {
                $this->warn("Failed for customer #{$customer->id}: " . $e->getMessage());
            }
        }

        $this->info("Done — {$triggered} invoices issued, {$charged} charged automatically, {$skipped} skipped.");

        return self::SUCCESS;
    }
}
