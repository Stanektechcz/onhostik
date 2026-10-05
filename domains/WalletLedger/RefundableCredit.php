<?php

declare(strict_types=1);

namespace Onhost\Domain\WalletLedger;

use Illuminate\Support\Facades\DB;
use Onhost\Domain\WalletLedger\Models\LedgerAccount;
use Onhost\Domain\WalletLedger\Models\WalletRefund;
use Onhost\Domain\WalletLedger\Models\WalletTopup;
use Onhost\Platform\Money\Currency;

/**
 * How much of the main wallet is purchased credit that has not been spent — the most a cash refund may pay out.
 *
 * The main wallet is ONE ledger account: money the customer paid in (a purchased top-up), credit that came back from a
 * correction (`returnToCredit`, non-refundable), bonus credit moved in for an order (`promo_used`) and staff credits all
 * land on it, and every spend takes from it without saying which of them it used. "Purchased top-ups minus refunds" was
 * therefore not the unspent purchased credit: 1000 bought, 800 spent and 800 returned left 1000 to pay out in cash while
 * only 200 of the money paid in was still there — the returned credit turned into money.
 *
 * The spend order (one rule for the whole wallet, the one the bonus wallet already follows — bonus credit is pulled in
 * only for what the purchased credit cannot cover):
 *
 *   1. purchased credit is spent FIRST,
 *   2. then the other credit (returned, bonus moved in, staff credits),
 *   3. what neither covers is a debt (metered usage may go below zero); credit that arrives later pays the debt first.
 *
 * A refund is a spend like any other (it can only take purchased credit, and under this order that is what it takes).
 * The ledger is replayed in the order the money moved (transaction ids are ULIDs, monotonic in creation order), so the
 * answer is the same whenever it is asked and needs no column the ledger does not have.
 */
final class RefundableCredit
{
    /**
     * Purchased credit left after replaying the movements of the main wallet account, oldest first.
     *
     * @param  iterable<array{credit:int, debit:int, purchased:bool}>  $movements  the net movement of each transaction
     */
    public static function replay(iterable $movements): int
    {
        $purchased = 0;
        $other = 0;
        $debt = 0;
        foreach ($movements as $movement) {
            $net = $movement['credit'] - $movement['debit'];
            if ($net > 0) {
                $paysDebt = min($debt, $net);
                $debt -= $paysDebt;
                if ($movement['purchased']) {
                    $purchased += $net - $paysDebt;
                } else {
                    $other += $net - $paysDebt;
                }

                continue;
            }
            $out = -$net;
            $fromPurchased = min($purchased, $out);
            $purchased -= $fromPurchased;
            $out -= $fromPurchased;
            $fromOther = min($other, $out);
            $other -= $fromOther;
            $debt += $out - $fromOther;
        }

        return $purchased;
    }

    /** The unspent purchased credit of an organization's main wallet in one currency, in minor units (never negative). */
    public static function of(string $organizationId, Currency $currency): int
    {
        $account = LedgerAccount::query()->where('code', LedgerService::walletAccount($organizationId, $currency))->first();
        if ($account === null) {
            return 0;
        }
        $purchased = WalletTopup::query()->where('organization_id', $organizationId)->where('currency', $currency->value)
            ->where('bucket', 'purchased')->where('state', 'completed')->whereNotNull('transaction_id')->pluck('transaction_id')->flip();
        // a refund that is ever reversed (the gateway refused to pay it out) gives back purchased credit
        $refunds = WalletRefund::query()->where('organization_id', $organizationId)->where('currency', $currency->value)
            ->whereNotNull('transaction_id')->pluck('transaction_id')->flip();
        $rows = DB::table('ledger_postings')
            ->join('ledger_transactions', 'ledger_transactions.id', '=', 'ledger_postings.transaction_id')
            ->where('ledger_postings.account_id', $account->id)
            ->groupBy('ledger_postings.transaction_id', 'ledger_transactions.reversal_of')
            ->selectRaw("ledger_postings.transaction_id AS tx, ledger_transactions.reversal_of AS reversal_of, SUM(CASE WHEN ledger_postings.direction = 'credit' THEN ledger_postings.amount_minor ELSE 0 END) AS credits, SUM(CASE WHEN ledger_postings.direction = 'debit' THEN ledger_postings.amount_minor ELSE 0 END) AS debits")
            ->orderBy('ledger_postings.transaction_id')
            ->cursor()
            ->map(fn (object $row): array => [
                'credit' => (int) $row->credits, 'debit' => (int) $row->debits,
                'purchased' => $purchased->has($row->tx) || ($row->reversal_of !== null && $refunds->has($row->reversal_of)),
            ]);

        return self::replay($rows);
    }
}
