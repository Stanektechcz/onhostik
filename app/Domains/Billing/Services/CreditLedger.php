<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Enums\CreditTransactionType;
use App\Domains\Billing\Events\CreditBalanceLow;
use App\Domains\Billing\Exceptions\InsufficientCreditException;
use App\Domains\Billing\Models\CreditTransaction;
use App\Domains\Customer\Models\Customer;
use App\Domains\Shared\Enums\Currency;
use Brick\Money\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Append-only credit ledger.
 *
 * CONCURRENCY MODEL:
 *  Every write locks the customer row (SELECT ... FOR UPDATE), recomputes
 *  the authoritative balance as SUM(amount) inside the same transaction,
 *  validates invariants, inserts the new ledger row, and refreshes the cache.
 *  Two concurrent deductions can therefore never overdraw the account.
 *
 * CURRENCY MODEL:
 *  The ledger is single-currency per customer (Customer::preferred_currency).
 *  Mixed-currency deposits must be converted by the caller BEFORE entering
 *  the ledger; the ledger itself never converts.
 */
final class CreditLedger
{
    private const CACHE_TTL = 300;

    public function deposit(
        Customer $customer,
        Money $amount,
        string $description,
        ?Model $reference = null,
        ?int $createdBy = null,
    ): CreditTransaction {
        $this->assertPositive($amount);

        return $this->write($customer, CreditTransactionType::Deposit, $amount, $description, $reference, $createdBy);
    }

    public function deduct(
        Customer $customer,
        Money $amount,
        string $description,
        ?Model $reference = null,
    ): CreditTransaction {
        $this->assertPositive($amount);

        return $this->write($customer, CreditTransactionType::Deduction, $amount->negated(), $description, $reference);
    }

    public function refund(
        Customer $customer,
        Money $amount,
        string $description,
        ?Model $reference = null,
        ?int $createdBy = null,
    ): CreditTransaction {
        $this->assertPositive($amount);

        return $this->write($customer, CreditTransactionType::Refund, $amount, $description, $reference, $createdBy);
    }

    /** Admin adjustment — amount may be positive or negative. */
    public function adjust(
        Customer $customer,
        Money $amount,
        string $description,
        int $createdBy,
    ): CreditTransaction {
        return $this->write($customer, CreditTransactionType::Adjustment, $amount, $description, createdBy: $createdBy);
    }

    public function getBalance(Customer $customer): Money
    {
        $minor = Cache::remember(
            $this->cacheKey($customer),
            self::CACHE_TTL,
            fn (): int => (int) CreditTransaction::where('customer_id', $customer->id)->sum('amount'),
        );

        return Money::ofMinor($minor, $customer->preferred_currency->value);
    }

    /** @return LengthAwarePaginator<int, CreditTransaction> */
    public function getHistory(Customer $customer, int $perPage = 25): LengthAwarePaginator
    {
        return CreditTransaction::where('customer_id', $customer->id)
            ->latest('id')
            ->paginate($perPage);
    }

    // ---------------------------------------------------------------- internals

    private function write(
        Customer $customer,
        CreditTransactionType $type,
        Money $amount,
        string $description,
        ?Model $reference = null,
        ?int $createdBy = null,
    ): CreditTransaction {
        $this->assertCurrencyMatches($customer, $amount);

        $transaction = DB::transaction(function () use ($customer, $type, $amount, $description, $reference, $createdBy): CreditTransaction {
            // Serialize all ledger writes for this customer.
            DB::table('customers')->where('id', $customer->id)->lockForUpdate()->first();

            $currentMinor = (int) CreditTransaction::where('customer_id', $customer->id)->sum('amount');
            $deltaMinor   = $amount->getMinorAmount()->toInt();
            $newMinor     = $currentMinor + $deltaMinor;

            if ($newMinor < 0) {
                throw new InsufficientCreditException(
                    available: Money::ofMinor($currentMinor, $amount->getCurrency()->getCurrencyCode()),
                    requested: $amount->abs(),
                );
            }

            return CreditTransaction::create([
                'customer_id'    => $customer->id,
                'type'           => $type,
                'currency'       => $amount->getCurrency()->getCurrencyCode(),
                'amount'         => $amount,
                'balance_after'  => Money::ofMinor($newMinor, $amount->getCurrency()->getCurrencyCode()),
                'description'    => $description,
                'reference_type' => $reference ? $reference::class : null,
                'reference_id'   => $reference?->getKey(),
                'created_by'     => $createdBy,
            ]);
        });

        Cache::forget($this->cacheKey($customer));

        $this->dispatchLowBalanceEventIfNeeded($customer);

        return $transaction;
    }

    private function dispatchLowBalanceEventIfNeeded(Customer $customer): void
    {
        $threshold = (int) config('billing.credit_low_threshold_minor', 10_000); // 100 CZK default
        $balance   = $this->getBalance($customer);

        if ($balance->getMinorAmount()->toInt() < $threshold) {
            event(new CreditBalanceLow($customer, $balance));
        }
    }

    private function assertPositive(Money $amount): void
    {
        if (!$amount->isPositive()) {
            throw new \InvalidArgumentException('Ledger amount must be positive; sign is determined by the operation.');
        }
    }

    private function assertCurrencyMatches(Customer $customer, Money $amount): void
    {
        if ($amount->getCurrency()->getCurrencyCode() !== $customer->preferred_currency->value) {
            throw new \InvalidArgumentException(sprintf(
                'Ledger currency mismatch: customer uses %s, got %s. Convert before writing.',
                $customer->preferred_currency->value,
                $amount->getCurrency()->getCurrencyCode(),
            ));
        }
    }

    private function cacheKey(Customer $customer): string
    {
        return "credit_balance:{$customer->id}";
    }
}
