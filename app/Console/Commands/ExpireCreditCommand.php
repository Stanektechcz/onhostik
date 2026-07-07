<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Billing\Enums\CreditTransactionType;
use App\Domains\Billing\Models\CreditTransaction;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Customer\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Creates Expiry deduction ledger rows for Deposit transactions whose
 * expires_at has passed. Only considers still-positive net-deposit amounts.
 * Safe to re-run — does not create duplicate expiry rows because it checks
 * for an existing Expiry entry linked via reference_id.
 */
class ExpireCreditCommand extends Command
{
    protected $signature   = 'billing:expire-credit';
    protected $description = 'Write Expiry deduction entries for deposit transactions whose expires_at has passed';

    public function __construct(private readonly CreditLedger $ledger)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $expired = 0;

        CreditTransaction::query()
            ->where('type', CreditTransactionType::Deposit)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereDoesntHave('expiryDeductions')
            ->with('customer')
            ->each(function (CreditTransaction $tx) use (&$expired): void {
                $customer = $tx->customer;

                if ($customer === null) {
                    return;
                }

                $balance = $this->ledger->getBalance($customer);

                if ($balance->getMinorAmount()->toInt() <= 0) {
                    return;
                }

                // Expire at most the remaining balance so we never overdraw the account.
                $depositAmount = $tx->amount->abs();
                $amount = $depositAmount->getMinorAmount()->toInt() <= $balance->getMinorAmount()->toInt()
                    ? $depositAmount
                    : $balance;

                try {
                    DB::transaction(function () use ($customer, $amount, $tx): void {
                        $this->ledger->expire($customer, $amount, "Vypršení kreditu (vklad #{$tx->id})", $tx);
                    });
                    $expired++;
                } catch (\Throwable $e) {
                    report($e);
                }
            });

        $this->info("Expired {$expired} credit transaction(s).");

        return self::SUCCESS;
    }
}
