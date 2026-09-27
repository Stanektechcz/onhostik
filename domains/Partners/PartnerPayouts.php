<?php

declare(strict_types=1);

namespace Onhost\Domain\Partners;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Authorization\ApprovalService;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Domain\Partners\Models\PartnerCommission;
use Onhost\Domain\Partners\Models\PartnerPayout;
use Onhost\Domain\Partners\Models\PartnerPayoutAccount;
use Onhost\Domain\Tax\TaxEngine;
use Onhost\Domain\Tax\VatNumber;
use Onhost\Domain\Tax\VatStanding;
use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * A partner payout from the request to the bank (TASK-0040, permission program P0-13 / IF-14), moved out of PartnerService
 * with its self-billing document (TASK-0031): payouts ≥ 1 000 CZK by self-billing, ledger-posted as partner-commission expense
 * when paid. What this class guarantees:
 *  - a payout is exactly the commissions it allocated, read under the partner row lock (audit P1: the race of two requests);
 *  - its IBAN is the partner's confirmed payout account (PayoutAccounts), never the request's (audit P2), and the payment
 *    checks it again: a payout left open by the old code is not paid to an IBAN nobody confirmed (review round 1);
 *  - it is paid once, only from `approved`, by somebody other than who asked for it or approved it, with a second person
 *    on the payment (PartnerCommand `payout.pay` is CRITICAL; the sole operator waits the time lock) — audit P8, TD-4;
 *  - a payout held for a look (frozen) is neither approved nor paid until finance rejects or releases it.
 */
final class PartnerPayouts
{
    public function __construct(
        private readonly OutboxPublisher $outbox,
        private readonly AuditRecorder $audit,
        private readonly LedgerService $ledger,
        private readonly InvoiceService $invoices,
        private readonly TaxEngine $tax,
        private readonly PayoutAccounts $accounts,
    ) {}

    /**
     * TASK-0040 (permission program IF-14, audit P1/P2): the balance, the check and the allocation are one locked read. The
     * balance used to be read before the transaction that allocated it, so two requests racing for one balance both passed
     * the check; the second allocated nothing (the first had taken every commission) and still stood for the whole amount —
     * a payout nothing backed. Now the partner row is locked first (a second request waits for the first to commit), the
     * payable commissions are read under that lock, and the payout is exactly what it allocated.
     *
     * The IBAN is the partner's confirmed payout account (PayoutAccounts), never the request's: an IBAN named in the request
     * must be that account — it confirms what the partner sees, it does not redirect the money (it used to overwrite the
     * account with no step-up).
     */
    public function requestPayout(Partner $partner, Money $amount, ?string $iban, CommandContext $context, string $method = 'bank_transfer'): PartnerPayout
    {
        if (! $partner->isActive()) {
            throw new DomainError('partner_not_active', 'The partner account is not active.', 409);
        }
        if (! in_array($method, ['bank_transfer', 'offset'], true)) {
            throw new DomainError('payout_method_invalid', 'The payout method is a bank transfer or an offset.', 422, ['field' => 'method']);
        }
        $min = Money::minor((int) config('onhost.partners.min_payout_minor', 100000), $partner->currency);
        if ($amount->lessThan($min)) {
            throw new DomainError('payout_below_minimum', "Minimum payout is {$min->format()}.", 422, ['field' => 'amount', 'minimum' => $min]);
        }
        $account = $method === 'bank_transfer' ? $this->confirmedAccount($partner, $iban) : null;
        $organization = Organization::query()->findOrFail($partner->organization_id);
        $vat = $this->selfBillingVat($organization); // decided before anything is written: a refused payout leaves nothing half-made

        return DB::transaction(function () use ($partner, $amount, $account, $method, $organization, $context, $vat) {
            $locked = Partner::query()->whereKey($partner->id)->lockForUpdate()->first();
            if ($locked === null || ! $locked->isActive()) {
                throw new DomainError('partner_not_active', 'The partner account is not active.', 409);
            }
            $payable = PartnerCommission::query()->where('partner_id', $partner->id)->where('state', 'payable')->orderBy('invoice_paid_at')->orderBy('created_at')->lockForUpdate()->get();
            $available = Money::minor((int) $payable->sum('amount_minor'), $partner->currency);
            if ($amount->greaterThan($available)) {
                throw new DomainError('payout_exceeds_balance', "Available for payout: {$available->format()}.", 422, ['field' => 'amount', 'available' => $available]);
            }
            $number = $this->payoutNumber();
            $payout = PartnerPayout::query()->create([
                'partner_id' => $partner->id, 'number' => $number, 'amount_minor' => $amount->minor, 'currency' => $amount->currency->value, 'method' => $method, 'iban' => $account['iban'] ?? null,
                'payout_account_id' => $account['id'] ?? null, 'requested_by' => $context->actorType === 'user' ? $context->actorId : null, 'state' => 'requested', 'self_billing' => [], 'requested_at' => now(),
            ]);
            $allocated = $this->allocate($payable, $payout, $amount->minor);
            $total = array_sum(array_map(fn (PartnerCommission $c) => (int) $c->amount_minor, $allocated));
            if ($total !== $amount->minor) { // cannot happen under the lock; if it ever did, nothing of this payout is kept
                throw new \LogicException("Payout {$number} would stand for {$amount->minor} but allocated {$total}.");
            }
            $payout->forceFill(['self_billing' => $this->selfBilling($number, $partner, $organization, $amount, $allocated, $vat)])->save();
            if ($account !== null) {
                $locked->forceFill(['iban' => $account['iban']])->save(); // the last account paid to, as the staff list shows it — never the request's
            }
            $this->audit->record($context->withScope($partner->organization_id), 'partner.payout.request', 'succeeded', ['number' => $number, 'amount' => $amount, 'method' => $method, 'commissions' => count($allocated), 'account' => PayoutAccounts::mask($account['iban'] ?? null), 'account_source' => $account['source'] ?? null], 'partner_payout', $payout->id);
            $this->outbox->publish(GenericEvent::of('partner.payout.requested', 'partner_payout', $payout->id, ['number' => $number, 'amount' => $amount, 'method' => $method], $partner->organization_id));

            return $payout;
        });
    }

    /**
     * FIFO allocation of the locked payable commissions; the last one is split so the payout matches the requested amount
     * exactly. A reversal (negative) row met on the way is allocated too and asks for more.
     *
     * @param  Collection<int, PartnerCommission>  $payable
     * @return list<PartnerCommission>
     */
    private function allocate(Collection $payable, PartnerPayout $payout, int $amountMinor): array
    {
        $remaining = $amountMinor;
        $allocated = [];
        foreach ($payable as $commission) {
            if ($remaining <= 0) {
                break;
            }
            if ($commission->amount_minor > $remaining) {
                $rest = $commission->replicate(['id']);
                $rest->forceFill(['amount_minor' => $commission->amount_minor - $remaining, 'state' => 'payable', 'payout_id' => null])->save();
                $commission->forceFill(['amount_minor' => $remaining]);
            }
            $commission->forceFill(['state' => 'allocated', 'payout_id' => $payout->id])->save();
            $remaining -= $commission->amount_minor;
            $allocated[] = $commission;
        }

        return $allocated;
    }

    /** @return array{id: ?string, iban: string, source: string, since: ?Carbon} the account a bank transfer goes to (TASK-0040) */
    private function confirmedAccount(Partner $partner, ?string $named): array
    {
        $named = PayoutAccounts::normalIban((string) $named);
        if ($named !== '' && ! PayoutAccounts::validIban($named)) {
            throw new DomainError('payout_iban_invalid', 'Enter a valid IBAN (Czech IBAN is CZ followed by 22 digits).', 422, ['field' => 'iban']);
        }
        $account = $this->accounts->of($partner)['active'];
        $days = PayoutAccounts::coolingDays();
        if ($account === null) {
            throw new DomainError('payout_account_missing', "Set the payout account first: the organization owner sets it in the partner portal, and a new account is used after a {$days}-day cooling-off.", 409, ['field' => 'iban']);
        }
        if ($named !== '' && $named !== $account['iban']) {
            $masked = PayoutAccounts::mask($account['iban']);
            throw new DomainError('payout_account_mismatch', "Payouts go to the confirmed payout account {$masked}. A different account is set by the organization owner as its own step and used after a {$days}-day cooling-off.", 409, ['field' => 'iban', 'account' => $masked]);
        }

        return $account;
    }

    public function approvePayout(PartnerPayout $payout, CommandContext $context): PartnerPayout
    {
        DB::transaction(function () use ($payout, $context): void {
            $row = PartnerPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            if ($row->state !== 'requested') {
                throw new DomainError('payout_not_requested', 'Only requested payouts can be approved.', 409);
            }
            $this->assertNotFrozen($row);
            if ($context->actorType === 'user' && $context->actorId !== null && $context->actorId === $row->requested_by) {
                throw new DomainError('payout_same_person', 'You asked for this payout; somebody else approves it.', 409);
            }
            $row->forceFill(['state' => 'approved', 'decided_by' => $context->actorId, 'approved_by' => $context->actorId, 'approved_at' => now()])->save();
            $this->audit->record($context, 'partner.payout.approve', 'succeeded', ['number' => $row->number], 'partner_payout', $row->id);
        });

        return $payout->refresh();
    }

    public function rejectPayout(PartnerPayout $payout, string $reason, CommandContext $context): PartnerPayout
    {
        DB::transaction(function () use ($payout, $reason, $context): void {
            $row = PartnerPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            if (! in_array($row->state, ['requested', 'approved'], true)) {
                throw new DomainError('payout_not_open', 'Only open payouts can be rejected.', 409);
            }
            PartnerCommission::query()->where('payout_id', $row->id)->update(['state' => 'payable', 'payout_id' => null]);
            $row->forceFill(['state' => 'rejected', 'decided_by' => $context->actorId, 'note' => $reason])->save();
            $this->audit->record($context, 'partner.payout.reject', 'succeeded', ['number' => $row->number, 'reason' => $reason, 'frozen' => $row->isFrozen()], 'partner_payout', $row->id);
        });

        return $payout->refresh();
    }

    /**
     * Bank transfer executed: post the commission expense and notify the partner (mail template `payout`). What is paid is the
     * self-billing document's total (TASK-0031 review): a VAT-payer partner's document says net + VAT, and the partner owes that
     * VAT on what it receives — the VAT is booked as input VAT against the VAT account, the commission stays the expense.
     *
     * TASK-0040 (program IF-14, audit P8, TD-4): only an `approved` payout is paid — it used to be paid straight from
     * `requested` — once (the row is locked and its state read under the lock), for exactly what it allocated, and not by
     * whoever asked for it or approved it: the payment is CRITICAL (PartnerCommand), so a second person signs it, and the
     * approver of the payout is not the one who pays. The sole operator (ONHOST_FOUR_EYES=false) waits the time lock instead.
     */
    public function markPayoutPaid(PartnerPayout $payout, string $paymentReference, CommandContext $context): PartnerPayout
    {
        $partner = Partner::query()->findOrFail($payout->partner_id);
        DB::transaction(function () use ($payout, $partner, $paymentReference, $context): void {
            $row = PartnerPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            if ($row->state !== 'approved') {
                throw new DomainError('payout_not_approved', 'Only an approved payout can be paid, and only once.', 409, ['state' => $row->state]);
            }
            $this->assertNotFrozen($row);
            $this->assertPayer($row, $context);
            $this->assertAccountConfirmed($row);
            $allocated = (int) PartnerCommission::query()->where('payout_id', $row->id)->where('state', 'allocated')->sum('amount_minor');
            if ($allocated !== $row->amount_minor) {
                throw new DomainError('payout_allocation_mismatch', 'The payout stands for another amount than the commissions allocated to it; reject it and let the partner ask again.', 409, ['allocated' => Money::minor($allocated, $row->currency)]);
            }
            $amount = $row->net();
            $tax = $row->documentTax();
            $transfer = $row->transferAmount();
            $postings = [['account' => LedgerService::expenseAccount('partner_commission', $amount->currency), 'debit' => $amount->minor]];
            if ($tax->isPositive()) {
                $postings[] = ['account' => LedgerService::vatAccount($amount->currency), 'debit' => $tax->minor];
            }
            $postings[] = ['account' => LedgerService::bankAccount($row->method === 'offset' ? 'offset' : 'bank', $amount->currency), 'credit' => $transfer->minor];
            $transaction = $this->ledger->post('partner_payout', $amount->currency, $postings, "partner-payout:{$row->id}", $partner->organization_id, 'partner_payout', $row->id, "Partner payout {$row->number}");
            PartnerCommission::query()->where('payout_id', $row->id)->update(['state' => 'paid']);
            $row->forceFill(['state' => 'paid', 'paid_at' => now(), 'payment_reference' => $paymentReference, 'decided_by' => $context->actorId, 'ledger_transaction_id' => $transaction->id ?? null])->save();
            $this->audit->record($context->withScope($partner->organization_id), 'partner.payout.paid', 'succeeded', ['number' => $row->number, 'amount' => $amount, 'tax' => $tax, 'transfer' => $transfer, 'reference' => $paymentReference, 'account' => PayoutAccounts::mask($row->iban), 'approved_by' => $row->approver()], 'partner_payout', $row->id);
            $this->outbox->publish(GenericEvent::of('partner.payout.paid', 'partner_payout', $row->id, ['number' => $row->number, 'amount' => $amount, 'transfer' => $transfer, 'period' => $row->self_billing['period'] ?? substr($row->number, 3), 'reference' => $paymentReference], $partner->organization_id));
        });

        return $payout->refresh();
    }

    /**
     * Held for a look (TASK-0040): `onhost:partners:payout-anomalies --apply` freezes what its dry run listed, finance may
     * freeze by hand. A frozen payout is neither approved nor paid; it can still be rejected (the commissions go back).
     */
    public function freezePayout(PartnerPayout $payout, string $reason, CommandContext $context): PartnerPayout
    {
        DB::transaction(function () use ($payout, $reason, $context): void {
            $row = PartnerPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            if (! in_array($row->state, ['requested', 'approved'], true)) {
                throw new DomainError('payout_not_open', 'Only open payouts can be frozen.', 409);
            }
            if ($row->isFrozen()) {
                return;
            }
            $organizationId = Partner::query()->find($row->partner_id)?->organization_id;
            $row->forceFill(['frozen_at' => now(), 'frozen_by' => $context->actorId ?? mb_substr('system:'.($context->reason ?? 'unknown'), 0, 60), 'frozen_reason' => mb_substr($reason, 0, 500)])->save();
            $this->audit->record($context->withScope($organizationId), 'partner.payout.freeze', 'succeeded', ['number' => $row->number, 'reason' => $reason], 'partner_payout', $row->id);
            $this->outbox->publish(GenericEvent::of('partner.payout.frozen', 'partner_payout', $row->id, ['number' => $row->number, 'amount' => $row->net(), 'reason' => mb_substr($reason, 0, 250)], $organizationId));
        });

        return $payout->refresh();
    }

    public function unfreezePayout(PartnerPayout $payout, string $reason, CommandContext $context): PartnerPayout
    {
        DB::transaction(function () use ($payout, $reason, $context): void {
            $row = PartnerPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            if (! $row->isFrozen()) {
                throw new DomainError('payout_not_frozen', 'This payout is not frozen.', 409);
            }
            $organizationId = Partner::query()->find($row->partner_id)?->organization_id;
            $row->forceFill(['frozen_at' => null, 'frozen_by' => null, 'frozen_reason' => null])->save();
            $this->audit->record($context->withScope($organizationId), 'partner.payout.unfreeze', 'succeeded', ['number' => $row->number, 'reason' => $reason], 'partner_payout', $row->id);
            $this->outbox->publish(GenericEvent::of('partner.payout.unfrozen', 'partner_payout', $row->id, ['number' => $row->number, 'reason' => mb_substr($reason, 0, 250)], $organizationId));
        });

        return $payout->refresh();
    }

    /**
     * Where the money goes is checked at the payment itself, under the row lock (review round 1, billing + security HIGH). A
     * payout asked for since TASK-0040 takes its IBAN from the confirmed account; one the old code left open carries the IBAN
     * its request typed (audit P2) — a partner's first payout is only listed by the anomaly look, and a look not run yet
     * freezes nothing. So a bank transfer is paid only to an IBAN that was the partner's confirmed account when it was asked
     * for (PayoutAccounts::confirmedAt), and a payout that names its account row only to that row's IBAN. A leftover without
     * an account row passes only once finance released it with a recorded reason (`partner.payout.unfreeze`: hold it, confirm
     * the account with the partner, release it); otherwise finance rejects it, the owner sets the account and the partner asks
     * again. Paying a released one never makes its IBAN the account (grandfathering ends at the cut-over).
     */
    private function assertAccountConfirmed(PartnerPayout $payout): void
    {
        if ($payout->method !== 'bank_transfer') {
            return;
        }
        $iban = PayoutAccounts::normalIban((string) $payout->iban);
        $confirmed = $iban !== '' && in_array($iban, $this->accounts->confirmedAt($payout->partner_id, $payout->requested_at ?? now(), $payout->id), true);
        if ($payout->payout_account_id !== null) {
            $confirmed = $confirmed && PartnerPayoutAccount::query()->whereKey($payout->payout_account_id)->where('partner_id', $payout->partner_id)->where('iban', $iban)->exists();
        } elseif (! $confirmed && $iban !== '') {
            $confirmed = self::released($payout->id);
        }
        if (! $confirmed) {
            $masked = PayoutAccounts::mask($iban);
            throw new DomainError('payout_account_unconfirmed', "This payout goes to {$masked}, which was not the partner's confirmed payout account when it was asked for. Reject it (the organization owner sets the account and the partner asks again), or hold it, confirm the account with the partner and release it with a reason.", 409, ['account' => $masked]);
        }
    }

    /** Finance released the payout from a hold with a recorded reason (the domain's own audit row, never a refused attempt). */
    public static function released(string $payoutId): bool
    {
        return DB::table('audit_events')->where('action', 'partner.payout.unfreeze')->where('result', 'succeeded')->where('resource_id', $payoutId)->exists();
    }

    private function assertNotFrozen(PartnerPayout $payout): void
    {
        if ($payout->isFrozen()) {
            throw new DomainError('payout_frozen', 'This payout is frozen for a check: '.(string) $payout->frozen_reason, 409, ['frozen_at' => $payout->frozen_at?->toIso8601String()]);
        }
    }

    /**
     * Who may pay (TASK-0040, program §3 "paid only from approved by a person different from the approver"): never the person
     * who asked for the payout, never the one who approved it — unless the payment went through the sole operator's time
     * lock (the bus says so in `verifiedApprovalIds`). Platform code acting as the system (tests, operator tooling) is not a
     * person; the HTTP door always acts as one.
     */
    private function assertPayer(PartnerPayout $payout, CommandContext $context): void
    {
        if ($context->actorType === 'system') {
            return;
        }
        $actor = (string) $context->actorId;
        $requester = $payout->requested_by ?? DB::table('audit_events')->where('action', 'partner.payout.request')->where('resource_id', $payout->id)->value('actor_id');
        if ($requester !== null && (string) $requester === $actor) {
            throw new DomainError('payout_same_person', 'You asked for this payout; somebody else pays it.', 409);
        }
        $waived = $context->verifiedApprovalIds === ['waived:single-operator'] && ! ApprovalService::enabled();
        if (! $waived && $payout->approver() === $actor) {
            throw new DomainError('payout_same_person', 'You approved this payout; somebody else pays it.', 409);
        }
    }

    private function payoutNumber(): string
    {
        $base = 'PO-'.now()->format('Y-m');
        $n = PartnerPayout::query()->where('number', 'like', "{$base}%")->count();

        return $n === 0 ? $base : "{$base}-".($n + 1);
    }

    /**
     * The VAT of the self-billing document (TASK-0031, D31.6), from the same recorded check as the tax decision: a partner that
     * is a VAT payer in the supplier's country (a Czech DIČ is in VIES) is billed the standard rate of the active rule set; one
     * of another EU state, under reverse charge; anybody else without VAT. It used to ask for a status `payer` (never
     * written) and a `rates.<CC>` key the rule set does not have, falling back to 21: every document was 0 %. A missing rate is
     * refused, never guessed.
     *
     * A number no check has spoken about yet is not proof of the opposite (review round 2): the document then says the
     * registration is not verified and carries `vat_review` for finance, instead of stating that the partner is not a payer.
     *
     * Payer status follows the customer's acceptance rules (review round 3, VatStanding::payerStanding): a valid number of
     * another country than the partner's, or one VIES registers to another trader, is not proof — the document is at 0 %, says
     * the registration is not verified and carries `vat_review` with `vat_review_reason` (vat_country_mismatch, name_mismatch,
     * or unknown for a number nothing has proved either way), so no VAT is transferred on markPayoutPaid or booked as input
     * VAT. Only a check that said invalid, or a staff override to invalid, lets the document say the partner is not a payer.
     *
     * @return array{rate:float, category:string, note_vat:string, vat_review:bool, vat_review_reason:?string}
     */
    private function selfBillingVat(Organization $organization): array
    {
        $rules = $this->tax->currentRules()->rules;
        $supplier = strtoupper((string) data_get($rules, 'supplier.country', 'CZ'));
        $country = strtoupper((string) $organization->country);
        $standing = VatStanding::payerStanding($organization);
        $payer = $standing['payer'];
        if ($payer && $country === $supplier) {
            $rate = data_get($rules, 'standard_rates.'.$country);
            if (! is_numeric($rate)) {
                throw new DomainError('tax_rate_missing', 'No standard VAT rate for '.$country.' in the active tax rules; the self-billing document cannot be issued.', 409, ['country' => $country]);
            }

            return ['rate' => (float) $rate, 'category' => TaxEngine::CAT_STANDARD, 'note_vat' => 'Dodavatel je plátcem DPH.', 'vat_review' => false, 'vat_review_reason' => null];
        }
        if ($payer && in_array($country, array_map('strtoupper', (array) data_get($rules, 'eu_members', VatNumber::EU_MEMBERS)), true)) {
            return ['rate' => 0.0, 'category' => TaxEngine::CAT_REVERSE_CHARGE, 'note_vat' => 'Daň odvede odběratel (reverse charge, čl. 196 směrnice 2006/112/ES).', 'vat_review' => false, 'vat_review_reason' => null];
        }
        // closing review: a supplier finance has not confirmed (or that renamed itself since) is refused like a name VIES disowns
        $refused = in_array($standing['reason'], ['vat_country_mismatch', 'name_mismatch', 'identity_unconfirmed', 'identity_changed'], true);
        $said = in_array($standing['reason'], ['invalid', 'staff_override'], true); // a check of this number, or staff, said "not a payer"
        if ($refused || (! $said && VatStanding::subject($organization)?->isWellFormed() === true)) {
            return ['rate' => 0.0, 'category' => TaxEngine::CAT_EXEMPT, 'note_vat' => 'Registrace dodavatele k DPH neověřena.', 'vat_review' => true, 'vat_review_reason' => $refused ? $standing['reason'] : 'unknown'];
        }

        return ['rate' => 0.0, 'category' => TaxEngine::CAT_EXEMPT, 'note_vat' => 'Dodavatel není plátcem DPH.', 'vat_review' => false, 'vat_review_reason' => null];
    }

    /**
     * Self-billed invoice snapshot: the partner is the supplier, ONhost's legal entity the customer. Written once; a later change
     * of the partner's VAT status never rewrites it.
     *
     * @param  array{rate:float, category:string, note_vat:string, vat_review:bool, vat_review_reason:?string}  $vat
     */
    private function selfBilling(string $number, Partner $partner, Organization $organization, Money $amount, array $commissions, array $vat): array
    {
        $entity = $this->invoices->legalEntity();
        $rate = $vat['rate'];
        $tax = $amount->percent((string) $rate);
        $byClient = collect($commissions)->groupBy('organization_id')->map(function (Collection $items, string $clientId) use ($partner) {
            $client = Organization::query()->find($clientId);

            return ['client' => $client->name ?? $clientId, 'documents' => $items->count(), 'base' => Money::minor((int) $items->sum('base_minor'), $partner->currency), 'amount' => Money::minor((int) $items->sum('amount_minor'), $partner->currency)];
        })->values()->all();

        return [
            'number' => $number, 'period' => now()->format('Y-m'), 'issued_at' => now()->toIso8601String(), 'self_billing' => true,
            'supplier' => $organization->only(['name', 'ico', 'dic', 'vat_id', 'street', 'city', 'postal_code', 'country']), 'customer' => ['name' => $entity->name ?? 'ONhost', 'ico' => $entity->ico ?? null, 'dic' => $entity->dic ?? null],
            'lines' => $byClient, 'net' => $amount, 'tax_rate' => $rate, 'tax_category' => $vat['category'], 'tax' => $tax, 'total' => $amount->add($tax), 'currency' => $amount->currency->value,
            'note_vat' => $vat['note_vat'], 'vat_review' => $vat['vat_review'], 'vat_review_reason' => $vat['vat_review_reason'], 'vat_check' => VatStanding::snapshot($organization),
            'note' => 'Doklad vystaven odběratelem v režimu samofakturace (§ 28 odst. 7 zákona o DPH) na základě partnerské smlouvy.',
        ];
    }
}
