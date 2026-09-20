<?php

declare(strict_types=1);

namespace Onhost\Domain\WalletLedger;

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\WalletLedger\Models\Budget;
use Onhost\Domain\WalletLedger\Models\CreditLine;
use Onhost\Domain\WalletLedger\Models\LedgerTransaction;
use Onhost\Domain\WalletLedger\Models\Wallet;
use Onhost\Domain\WalletLedger\Models\WalletAdjustment;
use Onhost\Domain\WalletLedger\Models\WalletHold;
use Onhost\Domain\WalletLedger\Models\WalletRefund;
use Onhost\Domain\WalletLedger\Models\WalletTopup;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Currency;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Wallet operations on top of the ledger (§62). Every mutation locks the wallet
 * row (`SELECT … FOR UPDATE`), re-derives balances from the ledger, and is
 * idempotent by key — two parallel creates on the same balance can never both
 * commit (S52). Holds are the only way to reserve money before provisioning:
 *   quote -> hold -> provision -> verify -> capture   (failure: hold -> release)
 */
final class WalletService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly OutboxPublisher $outbox,
    ) {}

    public function wallet(Organization|string $organization, Currency|string $currency, string $kind = 'main'): Wallet
    {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;
        $currency = $currency instanceof Currency ? $currency : Currency::fromString($currency);

        $wallet = Wallet::query()->firstOrCreate(
            ['organization_id' => $organizationId, 'currency' => $currency->value, 'kind' => $kind],
            ['state' => 'active', 'low_balance_threshold_minor' => $currency === Currency::CZK ? 50000 : 2000],
        );

        return $wallet->wasRecentlyCreated ? $wallet->refresh() : $wallet; // pick up the database defaults (zero balances) on a fresh wallet
    }

    /** @return array{posted:Money, reserved:Money, available:Money, credit_line:Money, promo:Money, accrued_unbilled:Money} */
    public function balances(Organization|string $organization, Currency|string $currency): array
    {
        $wallet = $this->wallet($organization, $currency);
        $promo = $this->wallet($organization, $currency, 'promo');
        $creditLine = $this->approvedCreditLine($wallet->organization_id, $wallet->currency);

        return [
            'posted' => $wallet->posted(),
            'reserved' => $wallet->reserved(),
            'available' => $wallet->available(),
            'credit_line' => $creditLine,
            'promo' => $promo->available(),
            'accrued_unbilled' => Money::minor($wallet->accrued_unbilled_minor, $wallet->currency),
        ];
    }

    /** Spendable including promo bucket and approved credit line. */
    public function spendable(Organization|string $organization, Currency|string $currency): Money
    {
        $b = $this->balances($organization, $currency);

        return $b['available']->add($b['promo'])->add($b['credit_line']);
    }

    /**
     * Credit the wallet after a verified payment (or admin/promo). Postings:
     *   card/bank: DR asset:bank:{provider}  CR liability:wallet:{org}
     *   promo:     DR expense:promo          CR liability:promo:{org}
     */
    public function topup(
        Organization|string $organization,
        Money $amount,
        string $source,
        string $idempotencyKey,
        CommandContext $context,
        ?string $paymentIntentId = null,
        ?string $note = null,
        bool $promo = false,
        ?string $bankProvider = null,
        string $purpose = 'topup',
    ): WalletTopup {
        if (! $amount->isPositive()) {
            throw new DomainError('invalid_amount', 'Top-up amount must be positive.');
        }
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        return DB::transaction(function () use ($organizationId, $amount, $source, $idempotencyKey, $context, $paymentIntentId, $note, $promo, $bankProvider, $purpose) {
            $existing = WalletTopup::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }
            $wallet = $this->lockWallet($organizationId, $amount->currency, $promo ? 'promo' : 'main');
            $bucket = $promo ? 'promo' : 'wallet';
            $counter = $promo
                ? LedgerService::expenseAccount('promo', $amount->currency)
                : LedgerService::bankAccount($bankProvider ?? $source, $amount->currency);
            $transaction = $this->ledger->post(
                'topup', $amount->currency,
                [
                    ['account' => $counter, 'debit' => $amount->minor],
                    ['account' => LedgerService::walletAccount($organizationId, $amount->currency, $bucket), 'credit' => $amount->minor],
                ],
                'ledger:'.$idempotencyKey, $organizationId, 'wallet_topup', $idempotencyKey,
                $note ?? "Top-up via {$source}", $this->actor($context),
            );
            $topup = WalletTopup::query()->create([
                'wallet_id' => $wallet->id,
                'organization_id' => $organizationId,
                'amount_minor' => $amount->minor,
                'currency' => $amount->currency->value,
                'source' => $source,
                'bucket' => $promo ? 'promo' : 'purchased',
                'refundable' => ! $promo,
                'payment_intent_id' => $paymentIntentId,
                'state' => 'completed',
                'transaction_id' => $transaction->id,
                'note' => $note,
                'idempotency_key' => $idempotencyKey,
                'created_by' => $this->actor($context),
            ]);
            $this->refreshCaches($wallet);
            $this->outbox->publish(GenericEvent::of('wallet.topup.completed', 'wallet', $wallet->id, [
                'topup_id' => $topup->id, 'amount' => $amount, 'source' => $source, 'purpose' => $purpose, 'balance' => $wallet->refresh()->available(), // purpose `order`: the money settles an order at once, no "credit topped up" mail
            ], $organizationId));

            return $topup;
        }, 3);
    }

    /**
     * Reserve money before provisioning. Fails with `insufficient_funds` when
     * available (+credit line) < amount. Domain renewals use priority `domain`
     * and may consume the organization's domain renewal reserve.
     */
    public function hold(
        Organization|string $organization,
        Money $amount,
        string $purpose,
        string $idempotencyKey,
        CommandContext $context,
        ?string $referenceType = null,
        ?string $referenceId = null,
        string $priority = 'normal',
        ?int $ttlMinutes = 60 * 24,
        bool $enforceBudget = true,
    ): WalletHold {
        if (! $amount->isPositive()) {
            throw new DomainError('invalid_amount', 'Hold amount must be positive.');
        }
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        return DB::transaction(function () use ($organizationId, $amount, $purpose, $idempotencyKey, $context, $referenceType, $referenceId, $priority, $ttlMinutes, $enforceBudget) {
            $existing = WalletHold::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }
            $wallet = $this->lockWallet($organizationId, $amount->currency);
            if ($wallet->isFrozen()) {
                throw new DomainError('wallet_frozen', 'The wallet is frozen; contact support.', 423);
            }
            $available = $wallet->available()->add($this->approvedCreditLine($organizationId, $wallet->currency));
            if ($priority !== 'domain') {
                $available = $available->subtract($this->domainReserve($organizationId, $wallet->currency));
            }
            if ($available->lessThan($amount)) { // bonus credit covers what the purchased credit lacks (it counts as spendable, so an order must be able to use it)
                $available = $available->add($this->applyPromo($wallet, $amount->subtract($available), $idempotencyKey, $context, $referenceType, $referenceId));
            }
            if ($available->lessThan($amount)) {
                throw new DomainError('insufficient_funds', 'Insufficient wallet balance for this operation.', 402, [
                    'required' => $amount, 'available' => $available, 'hint' => 'Top up the wallet or enable auto top-up.',
                ]);
            }
            if ($enforceBudget) {
                $this->assertBudget($organizationId, $amount, $context);
            }
            $hold = WalletHold::query()->create([
                'wallet_id' => $wallet->id,
                'organization_id' => $organizationId,
                'amount_minor' => $amount->minor,
                'currency' => $amount->currency->value,
                'purpose' => $purpose,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'priority' => $priority,
                'state' => 'active',
                'expires_at' => $ttlMinutes === null ? null : now()->addMinutes($ttlMinutes),
                'idempotency_key' => $idempotencyKey,
                'created_by' => $this->actor($context),
            ]);
            $wallet->forceFill(['reserved_balance_minor' => $wallet->reserved_balance_minor + $amount->minor])->save();

            return $hold;
        }, 3);
    }

    /**
     * Capture a hold: DR liability:wallet:{org}  CR revenue:{family} (+ CR liability:vat for the tax part).
     * The captured amount may be lower than the hold (proration); the remainder is released.
     */
    /** @param array<string,int>|null $revenueSplit net amounts by revenue family when one capture covers lines of several families (their sum is the net) */
    public function capture(WalletHold $hold, string $revenueFamily, CommandContext $context, ?Money $amount = null, ?Money $taxPart = null, ?string $description = null, ?array $revenueSplit = null): WalletHold
    {
        return DB::transaction(function () use ($hold, $revenueFamily, $context, $amount, $taxPart, $description, $revenueSplit) {
            $hold = WalletHold::query()->lockForUpdate()->findOrFail($hold->id);
            if ($hold->state === 'captured') {
                return $hold;
            }
            if ($hold->state !== 'active') {
                throw new DomainError('hold_not_active', "Hold {$hold->id} is {$hold->state}.", 409);
            }
            $wallet = $this->lockWallet($hold->organization_id, $hold->currency);
            $captured = $amount ?? $hold->amount();
            if ($captured->greaterThan($hold->amount())) {
                throw new DomainError('capture_exceeds_hold', 'Cannot capture more than the held amount.', 409);
            }
            $tax = $taxPart ?? Money::zero($captured->currency);
            $net = $captured->subtract($tax);
            $postings = [['account' => LedgerService::walletAccount($hold->organization_id, $hold->currency), 'debit' => $captured->minor]];
            foreach ($this->revenuePostings($revenueFamily, $net, $revenueSplit) as $posting) {
                $postings[] = $posting;
            }
            if ($tax->isPositive()) {
                $postings[] = ['account' => LedgerService::vatAccount($hold->currency), 'credit' => $tax->minor];
            }
            $transaction = $this->ledger->post(
                'hold_capture', $hold->currency, $postings, 'capture:'.$hold->idempotency_key, $hold->organization_id,
                $hold->reference_type, $hold->reference_id, $description ?? "Charge for {$hold->purpose}", $this->actor($context),
            );
            $hold->forceFill(['state' => 'captured', 'captured_transaction_id' => $transaction->id, 'amount_minor' => $captured->minor])->save();
            $wallet->forceFill(['reserved_balance_minor' => max(0, $wallet->reserved_balance_minor - $hold->getOriginal('amount_minor'))])->save();
            $this->refreshCaches($wallet);
            $this->registerSpend($hold->organization_id, $captured);
            $this->outbox->publish(GenericEvent::of('wallet.charged', 'wallet', $wallet->id, [
                'hold_id' => $hold->id, 'amount' => $captured, 'purpose' => $hold->purpose, 'reference' => [$hold->reference_type, $hold->reference_id],
            ], $hold->organization_id));

            return $hold;
        }, 3);
    }

    /**
     * @param  array<string,int>|null  $split
     * @return list<array{account:string, credit:int}>
     */
    private function revenuePostings(string $family, Money $net, ?array $split): array
    {
        if (! $net->isPositive()) {
            return [];
        }
        $split = array_filter($split ?? [], fn ($minor) => (int) $minor > 0);
        if ($split === [] || (int) array_sum($split) !== $net->minor) { // a split that does not add up to the net is not trusted: one family, the whole net
            return [['account' => LedgerService::revenueAccount($family, $net->currency), 'credit' => $net->minor]];
        }
        $postings = [];
        foreach ($split as $name => $minor) {
            $postings[] = ['account' => LedgerService::revenueAccount((string) $name, $net->currency), 'credit' => (int) $minor];
        }

        return $postings;
    }

    public function release(WalletHold $hold, string $reason, CommandContext $context): WalletHold
    {
        return DB::transaction(function () use ($hold, $reason, $context) {
            $hold = WalletHold::query()->lockForUpdate()->findOrFail($hold->id);
            if ($hold->state !== 'active') {
                return $hold;
            }
            $wallet = $this->lockWallet($hold->organization_id, $hold->currency);
            $hold->forceFill(['state' => 'released'])->save();
            $wallet->forceFill(['reserved_balance_minor' => max(0, $wallet->reserved_balance_minor - $hold->amount_minor)])->save();
            $this->outbox->publish(GenericEvent::of('wallet.hold.released', 'wallet', $wallet->id, ['hold_id' => $hold->id, 'reason' => $reason, 'by' => $this->actor($context)], $hold->organization_id));

            return $hold;
        }, 3);
    }

    /** Direct charge without a prior hold (metered usage). */
    public function charge(Organization|string $organization, Money $amount, string $revenueFamily, string $idempotencyKey, CommandContext $context, ?string $referenceType = null, ?string $referenceId = null, ?Money $taxPart = null, bool $allowNegative = false, bool $enforceBudget = true, ?array $revenueSplit = null): void
    {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;
        DB::transaction(function () use ($organizationId, $amount, $revenueFamily, $idempotencyKey, $context, $referenceType, $referenceId, $taxPart, $allowNegative, $enforceBudget, $revenueSplit) {
            $wallet = $this->lockWallet($organizationId, $amount->currency);
            if (LedgerTransaction::query()->where('idempotency_key', 'ledger:'.$idempotencyKey)->exists()) {
                return; // the same charge again: it was taken once, and it is counted against the budget once
            }
            $spendable = $wallet->available()->add($this->approvedCreditLine($organizationId, $wallet->currency));
            if (! $allowNegative && $spendable->lessThan($amount)) {
                throw new DomainError('insufficient_funds', 'Insufficient wallet balance.', 402, ['required' => $amount, 'available' => $spendable]);
            }
            if ($enforceBudget && ! $allowNegative) { // renewals and metered usage spend the budget like an order does (H30); settling an issued invoice is a debt, not a purchase
                $this->assertBudget($organizationId, $amount, $context);
            }
            $tax = $taxPart ?? Money::zero($amount->currency);
            $postings = [['account' => LedgerService::walletAccount($organizationId, $amount->currency), 'debit' => $amount->minor]];
            $net = $amount->subtract($tax);
            foreach ($this->revenuePostings($revenueFamily, $net, $revenueSplit) as $posting) {
                $postings[] = $posting;
            }
            if ($tax->isPositive()) {
                $postings[] = ['account' => LedgerService::vatAccount($amount->currency), 'credit' => $tax->minor];
            }
            $this->ledger->post('usage_charge', $amount->currency, $postings, 'ledger:'.$idempotencyKey, $organizationId, $referenceType, $referenceId, "Usage charge {$revenueFamily}", $this->actor($context));
            $this->refreshCaches($wallet);
            $this->registerSpend($organizationId, $amount);
        }, 3);
    }

    /**
     * Pays an invoice that was booked when it was ISSUED (postpaid: DR receivable / CR revenue / CR VAT). The payment moves
     * money, it does not earn it a second time: DR liability:wallet / CR asset:receivable. Going through `charge()` instead
     * booked the revenue and the VAT twice and left the receivable open for ever — every entry balanced, so the ledger
     * check saw nothing.
     */
    public function settleReceivable(Organization|string $organization, Money $amount, string $idempotencyKey, CommandContext $context, string $referenceType, string $referenceId, ?string $description = null): void
    {
        if (! $amount->isPositive()) {
            throw new DomainError('invalid_amount', 'The settled amount must be positive.');
        }
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;
        DB::transaction(function () use ($organizationId, $amount, $idempotencyKey, $context, $referenceType, $referenceId, $description) {
            $wallet = $this->lockWallet($organizationId, $amount->currency);
            if (LedgerTransaction::query()->where('idempotency_key', 'ledger:'.$idempotencyKey)->exists()) {
                return;
            }
            $spendable = $wallet->available()->add($this->approvedCreditLine($organizationId, $wallet->currency));
            if ($spendable->lessThan($amount)) {
                throw new DomainError('insufficient_funds', 'Insufficient wallet balance.', 402, ['required' => $amount, 'available' => $spendable]);
            }
            $this->ledger->post('invoice_settlement', $amount->currency, [
                ['account' => LedgerService::walletAccount($organizationId, $amount->currency), 'debit' => $amount->minor],
                ['account' => LedgerService::receivableAccount($organizationId, $amount->currency), 'credit' => $amount->minor],
            ], 'ledger:'.$idempotencyKey, $organizationId, $referenceType, $referenceId, $description ?? 'Invoice paid from credit', $this->actor($context));
            $this->refreshCaches($wallet);
            $this->registerSpend($organizationId, $amount);
        }, 3);
    }

    /** Manual adjustment (signed). High-risk permission; approval id recorded. */
    public function adjust(Organization|string $organization, Money $amount, string $reason, string $idempotencyKey, CommandContext $context, ?string $approvalId = null): WalletAdjustment
    {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        return DB::transaction(function () use ($organizationId, $amount, $reason, $idempotencyKey, $context, $approvalId) {
            $existing = WalletAdjustment::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }
            $wallet = $this->lockWallet($organizationId, $amount->currency);
            $walletAccount = LedgerService::walletAccount($organizationId, $amount->currency);
            $adjustmentAccount = LedgerService::expenseAccount('adjustment', $amount->currency);
            $abs = $amount->abs()->minor;
            $postings = $amount->isNegative()
                ? [['account' => $walletAccount, 'debit' => $abs], ['account' => $adjustmentAccount, 'credit' => $abs]]
                : [['account' => $adjustmentAccount, 'debit' => $abs], ['account' => $walletAccount, 'credit' => $abs]];
            $transaction = $this->ledger->post('adjustment', $amount->currency, $postings, 'ledger:'.$idempotencyKey, $organizationId, 'wallet_adjustment', $idempotencyKey, $reason, $this->actor($context), ['approval_id' => $approvalId, 'ticket' => $context->ticketRef]);
            $adjustment = WalletAdjustment::query()->create([
                'wallet_id' => $wallet->id, 'organization_id' => $organizationId, 'amount_minor' => $amount->minor, 'currency' => $amount->currency->value,
                'reason' => $reason, 'ticket_ref' => $context->ticketRef, 'approval_id' => $approvalId, 'transaction_id' => $transaction->id,
                'idempotency_key' => $idempotencyKey, 'created_by' => $this->actor($context),
            ]);
            $this->refreshCaches($wallet);

            return $adjustment;
        }, 3);
    }

    /** Refund purchased (never promo) credit back to the payment source or by credit note. */
    public function refund(Organization|string $organization, Money $amount, string $reason, string $idempotencyKey, CommandContext $context, string $destination = 'source', ?string $paymentIntentId = null, ?string $approvalId = null): WalletRefund
    {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        return DB::transaction(function () use ($organizationId, $amount, $reason, $idempotencyKey, $context, $destination, $paymentIntentId, $approvalId) {
            $existing = WalletRefund::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }
            $wallet = $this->lockWallet($organizationId, $amount->currency);
            $refundable = $this->refundableBalance($organizationId, $amount->currency);
            if ($refundable->lessThan($amount)) {
                throw new DomainError('refund_exceeds_refundable', 'Refund exceeds the refundable purchased credit.', 409, ['refundable' => $refundable]);
            }
            $transaction = $this->ledger->post('refund', $amount->currency, [
                ['account' => LedgerService::walletAccount($organizationId, $amount->currency), 'debit' => $amount->minor],
                ['account' => $destination === 'source' && $paymentIntentId ? LedgerService::bankAccount('refund_pending', $amount->currency) : LedgerService::expenseAccount('refund', $amount->currency), 'credit' => $amount->minor],
            ], 'ledger:'.$idempotencyKey, $organizationId, 'wallet_refund', $idempotencyKey, $reason, $this->actor($context), ['approval_id' => $approvalId]);
            $refund = WalletRefund::query()->create([
                'wallet_id' => $wallet->id, 'organization_id' => $organizationId, 'amount_minor' => $amount->minor, 'currency' => $amount->currency->value,
                'reason' => $reason, 'destination' => $destination, 'payment_intent_id' => $paymentIntentId, 'state' => 'pending',
                'transaction_id' => $transaction->id, 'approval_id' => $approvalId, 'idempotency_key' => $idempotencyKey, 'created_by' => $this->actor($context),
            ]);
            $this->refreshCaches($wallet);
            $this->outbox->publish(GenericEvent::of('wallet.refund.requested', 'wallet', $wallet->id, ['refund_id' => $refund->id, 'amount' => $amount, 'destination' => $destination], $organizationId));

            return $refund;
        }, 3);
    }

    /** Purchased credit that has not been consumed, i.e. min(available, sum of purchased top-ups − consumed). */
    public function refundableBalance(string $organizationId, Currency|string $currency): Money
    {
        $wallet = $this->wallet($organizationId, $currency);
        $purchased = (int) WalletTopup::query()->where('organization_id', $organizationId)->where('currency', $wallet->currency)
            ->where('bucket', 'purchased')->where('state', 'completed')->sum('amount_minor');
        $refunded = (int) WalletRefund::query()->where('organization_id', $organizationId)->where('currency', $wallet->currency)
            ->whereIn('state', ['pending', 'completed'])->sum('amount_minor');
        $cap = Money::minor(max(0, $purchased - $refunded), $wallet->currency);
        $available = $wallet->available();

        return $available->lessThan($cap) ? $available : $cap;
    }

    public function freeze(Organization|string $organization, Currency|string $currency, string $reason, CommandContext $context): Wallet
    {
        $wallet = $this->lockWallet($organization instanceof Organization ? $organization->id : $organization, $currency);
        $wallet->forceFill(['state' => 'frozen'])->save();
        $this->outbox->publish(GenericEvent::of('wallet.frozen', 'wallet', $wallet->id, ['reason' => $reason, 'by' => $this->actor($context)], $wallet->organization_id));

        return $wallet;
    }

    public function expireHolds(): int
    {
        $count = 0;
        foreach (WalletHold::query()->where('state', 'active')->whereNotNull('expires_at')->where('expires_at', '<', now())->get() as $hold) {
            $this->release($hold, 'expired', CommandContext::system('hold expiry'));
            $count++;
        }

        return $count;
    }

    /** Re-derive cached balance from ledger (called after every mutation; also by reconciliation). */
    public function refreshCaches(Wallet $wallet): Wallet
    {
        $bucket = $wallet->kind === 'promo' ? 'promo' : 'wallet';
        $posted = $this->ledger->balance(LedgerService::walletAccount($wallet->organization_id, $wallet->currency, $bucket), $wallet->currency);
        $reserved = (int) WalletHold::query()->where('wallet_id', $wallet->id)->where('state', 'active')->sum('amount_minor');
        $wallet->forceFill(['posted_balance_minor' => $posted->minor, 'reserved_balance_minor' => $reserved])->save();

        return $wallet;
    }

    public function approvedCreditLine(string $organizationId, Currency|string $currency): Money
    {
        $cur = $currency instanceof Currency ? $currency : Currency::fromString($currency);
        $line = CreditLine::query()->where('organization_id', $organizationId)->where('currency', $cur->value)->where('state', 'approved')->first();

        return $line === null ? Money::zero($cur) : Money::minor(max(0, $line->limit_minor - $line->risk_hold_minor), $cur);
    }

    /** Amount kept aside for upcoming domain renewals (§65.2) — computed by the billing module and cached on the organization settings. */
    public function domainReserve(string $organizationId, Currency|string $currency): Money
    {
        $cur = $currency instanceof Currency ? $currency : Currency::fromString($currency);
        $organization = Organization::query()->find($organizationId);
        $minor = (int) data_get($organization?->settings, "domain_reserve.{$cur->value}", 0);

        return Money::minor(max(0, $minor), $cur);
    }

    /**
     * Moves bonus credit into the main wallet for a hold that purchased credit alone cannot cover (audit §5z):
     * DR liability:promo:{org}  CR liability:wallet:{org}, recorded as a non-refundable `promo_used` movement (bucket column is 12 characters).
     * Returns what was applied (zero when there is no bonus credit).
     */
    private function applyPromo(Wallet $main, Money $shortfall, string $idempotencyKey, CommandContext $context, ?string $referenceType, ?string $referenceId): Money
    {
        $promo = $this->lockWallet($main->organization_id, $main->currency, 'promo');
        $usable = $promo->available();
        if (! $shortfall->isPositive() || ! $usable->isPositive()) {
            return Money::zero($main->currency);
        }
        $applied = $usable->lessThan($shortfall) ? $usable : $shortfall;
        $key = 'promo-apply:'.$idempotencyKey;
        $transaction = $this->ledger->post(
            'promo_apply', $main->currency,
            [
                ['account' => LedgerService::walletAccount($main->organization_id, $main->currency, 'promo'), 'debit' => $applied->minor],
                ['account' => LedgerService::walletAccount($main->organization_id, $main->currency), 'credit' => $applied->minor],
            ],
            'ledger:'.$key, $main->organization_id, $referenceType, $referenceId, 'Použit bonusový kredit', $this->actor($context),
        );
        WalletTopup::query()->create([
            'wallet_id' => $main->id, 'organization_id' => $main->organization_id, 'amount_minor' => $applied->minor, 'currency' => $main->currency instanceof Currency ? $main->currency->value : $main->currency,
            'source' => 'promo', 'bucket' => 'promo_used', 'refundable' => false, 'state' => 'completed', 'transaction_id' => $transaction->id,
            'note' => 'Použit bonusový kredit', 'idempotency_key' => $key, 'created_by' => $this->actor($context),
        ]);
        $this->refreshCaches($promo);
        $this->refreshCaches($main);

        return $applied;
    }

    private function lockWallet(string $organizationId, Currency|string $currency, string $kind = 'main'): Wallet
    {
        $wallet = $this->wallet($organizationId, $currency, $kind);

        return Wallet::query()->lockForUpdate()->findOrFail($wallet->id);
    }

    private function assertBudget(string $organizationId, Money $amount, CommandContext $context): void
    {
        $budgets = Budget::query()->where('organization_id', $organizationId)->where('currency', $amount->currency->value)
            ->where(fn ($q) => $q->whereNull('project_id')->orWhere('project_id', $context->projectId))->get();
        $held = null;
        foreach ($budgets as $budget) {
            $this->rollOver($budget);
            if ($budget->max_single_service_minor !== null && $amount->minor > $budget->max_single_service_minor) {
                throw new DomainError('budget_single_service_exceeded', 'This purchase exceeds the maximum single service price set in the budget.', 409, ['limit' => Money::minor($budget->max_single_service_minor, $amount->currency)]);
            }
            // money already promised to open orders is spent as far as the limit is concerned
            $held ??= (int) WalletHold::query()->where('organization_id', $organizationId)->where('currency', $amount->currency->value)->where('state', 'active')->sum('amount_minor');
            if ($budget->hard && $budget->spent_minor + $held + $amount->minor > $budget->limit_minor) {
                throw new DomainError('budget_exceeded', 'The monthly hard budget would be exceeded.', 409, ['limit' => Money::minor($budget->limit_minor, $amount->currency), 'spent' => Money::minor($budget->spent_minor, $amount->currency)]);
            }
        }
    }

    private function registerSpend(string $organizationId, Money $amount): void
    {
        $budgets = Budget::query()->where('organization_id', $organizationId)->where('currency', $amount->currency->value)->get();
        foreach ($budgets as $budget) {
            $this->rollOver($budget);
            $spent = $budget->spent_minor + $amount->minor;
            $notified = $budget->notified ?? [];
            foreach ($budget->alert_thresholds ?? [50, 75, 90, 100] as $threshold) {
                if ($budget->limit_minor > 0 && $spent * 100 >= $budget->limit_minor * $threshold && ! in_array($threshold, $notified, true)) {
                    $notified[] = $threshold;
                    $this->outbox->publish(GenericEvent::of('budget.threshold', 'budget', $budget->id, ['threshold' => $threshold, 'spent' => Money::minor($spent, $amount->currency), 'limit' => Money::minor($budget->limit_minor, $amount->currency)], $organizationId));
                }
            }
            $budget->forceFill(['spent_minor' => $spent, 'notified' => $notified])->save();
        }
    }

    /** The budget question asked before money moves: an order paid by card or transfer is checked when it is placed. */
    public function assertWithinBudget(Organization|string $organization, Money $amount, CommandContext $context): void
    {
        $this->assertBudget($organization instanceof Organization ? $organization->id : $organization, $amount, $context);
    }

    /** A budget is a monthly one: the first touch in a new month starts it from zero, and its thresholds warn again. */
    public function rollOver(Budget $budget): Budget
    {
        $month = now()->startOfMonth();
        if ($budget->period_start === null || $budget->period_start->lt($month)) {
            $budget->forceFill(['spent_minor' => 0, 'notified' => [], 'period_start' => $month->toDateString()])->save();
        }

        return $budget;
    }

    private function actor(CommandContext $context): string
    {
        return $context->actorType.':'.($context->actorId ?? 'system');
    }
}
