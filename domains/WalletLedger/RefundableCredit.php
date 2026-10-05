<?php

declare(strict_types=1);

namespace Onhost\Domain\WalletLedger;

use Illuminate\Support\Facades\DB;
use Onhost\Domain\WalletLedger\Models\LedgerAccount;
use Onhost\Platform\Money\Currency;

/**
 * How much of the main wallet is purchased credit that has not been spent — and the spend order of the credit (owner
 * decision G-R3, 2026-10-05: purchased credit first; `replay` is that rule and stays as it is).
 *
 * It is no longer the cap of a cash refund: credit is never paid out in money (owner decision G-R4) and the wallet has no
 * refund. The measure is kept because the spend order is a decision of record, because it tells purchased credit from the
 * credit that came back from a correction in the ledger alone, and because a statutory refund of an unused prepayment (the
 * open owner question in ROZHODNUTI.md G-R4) would be capped by exactly this amount. No screen, API or command shows it.
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
 * A refund booked before G-R4 (ledger kind `refund`) is a spend like any other; its reversal gives the purchased money back.
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

    /**
     * The unspent purchased credit of an organization's main wallet in one currency, in minor units (never negative).
     *
     * What counts as purchased is read from the ledger entry itself, never from a `wallet_topups` row whose state could
     * change (security review H2): a credit to the main wallet is purchased when its transaction is a `topup` (a bonus
     * top-up credits the promo account, not this one), or when it reverses a `refund` (a payout the gateway refused gives
     * the purchased money back). Everything else credited here — a return, bonus moved in, a staff credit, and the
     * reversal of a spend — is credit that cannot be paid out. A reversed top-up is a debit and takes purchased credit
     * first, so it takes back its own money and not another top-up's.
     *
     * Cost: one grouped scan of this one account's postings through the `ledger_postings.account_id` index, streamed. No
     * listing, presenter, doctor row or payout calls it (G-R4); tests pin the spend order with it.
     */
    public static function of(string $organizationId, Currency $currency): int
    {
        $account = LedgerAccount::query()->where('code', LedgerService::walletAccount($organizationId, $currency))->first();
        if ($account === null) {
            return 0;
        }
        $rows = DB::table('ledger_postings')
            ->join('ledger_transactions', 'ledger_transactions.id', '=', 'ledger_postings.transaction_id')
            ->leftJoin('ledger_transactions as reversed', 'reversed.id', '=', 'ledger_transactions.reversal_of')
            ->where('ledger_postings.account_id', $account->id)
            ->groupBy('ledger_postings.transaction_id', 'ledger_transactions.kind', 'reversed.kind')
            ->selectRaw("ledger_postings.transaction_id AS tx, ledger_transactions.kind AS kind, reversed.kind AS reversed_kind, SUM(CASE WHEN ledger_postings.direction = 'credit' THEN ledger_postings.amount_minor ELSE 0 END) AS credits, SUM(CASE WHEN ledger_postings.direction = 'debit' THEN ledger_postings.amount_minor ELSE 0 END) AS debits")
            ->orderBy('ledger_postings.transaction_id')
            ->cursor()
            ->map(fn (object $row): array => [
                'credit' => (int) $row->credits, 'debit' => (int) $row->debits,
                'purchased' => $row->kind === 'topup' || ($row->kind === 'reversal' && $row->reversed_kind === 'refund'),
            ]);

        return self::replay($rows);
    }
}
