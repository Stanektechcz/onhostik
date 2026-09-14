<?php

declare(strict_types=1);

namespace Onhost\Domain\WalletLedger;

use Illuminate\Support\Facades\DB;
use Onhost\Domain\WalletLedger\Models\LedgerAccount;
use Onhost\Domain\WalletLedger\Models\LedgerPosting;
use Onhost\Domain\WalletLedger\Models\LedgerTransaction;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Currency;
use Onhost\Platform\Money\Money;

/**
 * Double-entry ledger. Invariant: for every transaction SUM(debits) == SUM(credits)
 * in one currency. Transactions are immutable; corrections are reversals.
 * Accounts are addressed by code and created lazily:
 *   liability:wallet:{org}:{CUR}      customer prepaid credit (closed-loop, §62.1)
 *   liability:promo:{org}:{CUR}       promotional credit (non-refundable bucket)
 *   asset:receivable:{org}:{CUR}      unpaid invoices / postpaid exposure
 *   asset:bank:{provider}:{CUR}       money received at a gateway / bank
 *   asset:clearing:{provider}:{CUR}   pending settlement
 *   revenue:{family}:{CUR}            recognised revenue
 *   liability:vat:{CUR}               VAT payable
 *   expense:refund:{CUR}, expense:fee:{provider}:{CUR}, expense:registrar:{CUR}, expense:sla_credit:{CUR}, expense:write_off:{CUR}
 */
final class LedgerService
{
    /**
     * @param  list<array{account:string, debit?:int, credit?:int}>  $postings  amounts in minor units (positive)
     * @param  array<string,mixed>  $meta
     */
    public function post(
        string $kind,
        Currency|string $currency,
        array $postings,
        string $idempotencyKey,
        ?string $organizationId = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $description = null,
        ?string $createdBy = null,
        array $meta = [],
        ?string $reversalOf = null,
    ): LedgerTransaction {
        $currency = $currency instanceof Currency ? $currency : Currency::fromString($currency);
        $this->assertBalanced($postings, $currency);

        return DB::transaction(function () use ($kind, $currency, $postings, $idempotencyKey, $organizationId, $referenceType, $referenceId, $description, $createdBy, $meta, $reversalOf) {
            $existing = LedgerTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }
            $transaction = LedgerTransaction::query()->create([
                'kind' => $kind,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'organization_id' => $organizationId,
                'currency' => $currency->value,
                'description' => $description === null ? null : mb_substr($description, 0, 250),
                'idempotency_key' => $idempotencyKey,
                'reversal_of' => $reversalOf,
                'created_by' => $createdBy,
                'meta' => $meta,
                'posted_at' => now(),
                'created_at' => now(),
            ]);
            foreach ($postings as $posting) {
                $account = $this->account($posting['account'], $currency);
                foreach (['debit', 'credit'] as $direction) {
                    $amount = (int) ($posting[$direction] ?? 0);
                    if ($amount <= 0) {
                        continue;
                    }
                    LedgerPosting::query()->create([
                        'transaction_id' => $transaction->id,
                        'account_id' => $account->id,
                        'direction' => $direction,
                        'amount_minor' => $amount,
                        'currency' => $currency->value,
                        'organization_id' => $account->organization_id ?? $organizationId,
                        'created_at' => now(),
                    ]);
                }
            }

            return $transaction;
        }, 3);
    }

    /** Reverse a transaction in full (swap debit/credit). */
    public function reverse(LedgerTransaction $original, string $idempotencyKey, string $reason, ?string $createdBy = null): LedgerTransaction
    {
        $postings = [];
        foreach ($original->postings()->get() as $posting) {
            $account = LedgerAccount::query()->findOrFail($posting->account_id);
            $postings[] = [
                'account' => $account->code,
                $posting->direction === LedgerPosting::DEBIT ? 'credit' : 'debit' => $posting->amount_minor,
            ];
        }

        return $this->post(
            'reversal', $original->currency, $postings, $idempotencyKey, $original->organization_id,
            $original->reference_type, $original->reference_id, "Reversal: {$reason}", $createdBy, ['reversed' => $original->id], $original->id,
        );
    }

    /** Signed balance of an account by its natural side (liability/revenue: credit-positive). */
    public function balance(string $code, Currency|string $currency): Money
    {
        $currency = $currency instanceof Currency ? $currency : Currency::fromString($currency);
        $account = LedgerAccount::query()->where('code', $code)->first();
        if ($account === null) {
            return Money::zero($currency);
        }
        $sums = DB::table('ledger_postings')
            ->selectRaw("SUM(CASE WHEN direction = 'debit' THEN amount_minor ELSE 0 END) AS debits, SUM(CASE WHEN direction = 'credit' THEN amount_minor ELSE 0 END) AS credits")
            ->where('account_id', $account->id)
            ->first();
        $debits = (int) ($sums->debits ?? 0);
        $credits = (int) ($sums->credits ?? 0);

        return Money::minor($account->debitIncreases() ? $debits - $credits : $credits - $debits, $currency);
    }

    /** Global invariant check used by reconciliation and property tests. @return array{balanced:bool, transactions:int, unbalanced:list<string>} */
    public function verifyInvariant(): array
    {
        $rows = DB::table('ledger_postings')
            ->selectRaw("transaction_id, SUM(CASE WHEN direction = 'debit' THEN amount_minor ELSE 0 END) AS debits, SUM(CASE WHEN direction = 'credit' THEN amount_minor ELSE 0 END) AS credits")
            ->groupBy('transaction_id')
            ->get();
        $unbalanced = [];
        foreach ($rows as $row) {
            if ((int) $row->debits !== (int) $row->credits) {
                $unbalanced[] = (string) $row->transaction_id;
            }
        }

        return ['balanced' => $unbalanced === [], 'transactions' => $rows->count(), 'unbalanced' => $unbalanced];
    }

    public function account(string $code, Currency|string $currency): LedgerAccount
    {
        $currency = $currency instanceof Currency ? $currency : Currency::fromString($currency);
        $existing = LedgerAccount::query()->where('code', $code)->first();
        if ($existing !== null) {
            if ($existing->currency !== $currency->value) {
                throw new DomainError('ledger_currency_mismatch', "Account {$code} is denominated in {$existing->currency}", 500);
            }

            return $existing;
        }
        [$type, $kind, $organizationId] = self::describe($code);

        return LedgerAccount::query()->create([
            'code' => $code,
            'type' => $type,
            'kind' => $kind,
            'currency' => $currency->value,
            'organization_id' => $organizationId,
        ]);
    }

    public static function walletAccount(string $organizationId, Currency|string $currency, string $bucket = 'wallet'): string
    {
        $cur = $currency instanceof Currency ? $currency->value : strtoupper($currency);

        return "liability:{$bucket}:{$organizationId}:{$cur}";
    }

    public static function receivableAccount(string $organizationId, Currency|string $currency): string
    {
        $cur = $currency instanceof Currency ? $currency->value : strtoupper($currency);

        return "asset:receivable:{$organizationId}:{$cur}";
    }

    public static function bankAccount(string $provider, Currency|string $currency): string
    {
        $cur = $currency instanceof Currency ? $currency->value : strtoupper($currency);

        return "asset:bank:{$provider}:{$cur}";
    }

    public static function revenueAccount(string $family, Currency|string $currency): string
    {
        $cur = $currency instanceof Currency ? $currency->value : strtoupper($currency);

        return "revenue:{$family}:{$cur}";
    }

    public static function vatAccount(Currency|string $currency): string
    {
        $cur = $currency instanceof Currency ? $currency->value : strtoupper($currency);

        return "liability:vat:{$cur}";
    }

    public static function expenseAccount(string $kind, Currency|string $currency): string
    {
        $cur = $currency instanceof Currency ? $currency->value : strtoupper($currency);

        return "expense:{$kind}:{$cur}";
    }

    /** @return array{0:string,1:string,2:?string} type, kind, organization id */
    private static function describe(string $code): array
    {
        $parts = explode(':', $code);
        $type = $parts[0] ?? 'asset';
        if (! in_array($type, ['asset', 'liability', 'revenue', 'expense', 'equity'], true)) {
            throw new DomainError('ledger_invalid_account', "Invalid account code {$code}", 500);
        }
        $kind = $parts[1] ?? 'misc';
        $organizationId = null;
        if (in_array($kind, ['wallet', 'promo', 'receivable'], true) && isset($parts[2]) && str_starts_with($parts[2], 'org_')) {
            $organizationId = $parts[2];
        }

        return [$type, $kind, $organizationId];
    }

    /** @param list<array{account:string, debit?:int, credit?:int}> $postings */
    private function assertBalanced(array $postings, Currency $currency): void
    {
        $debits = 0;
        $credits = 0;
        foreach ($postings as $posting) {
            $d = (int) ($posting['debit'] ?? 0);
            $c = (int) ($posting['credit'] ?? 0);
            if ($d < 0 || $c < 0 || ($d === 0 && $c === 0) || ($d > 0 && $c > 0)) {
                throw new DomainError('ledger_invalid_posting', 'Each posting must be a positive debit or a positive credit', 500);
            }
            $debits += $d;
            $credits += $c;
        }
        if ($debits !== $credits || $debits === 0) {
            throw new DomainError('ledger_unbalanced', "Ledger transaction is unbalanced ({$debits} debit vs {$credits} credit {$currency->value})", 500);
        }
    }
}
