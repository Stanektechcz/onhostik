<?php

declare(strict_types=1);

namespace Onhost\Domain\Support;

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Billing\DunningService;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Orders\CreditOrderPolicy;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Domain\Support\Models\WorkOffer;
use Onhost\Domain\Tax\TaxEngine;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Paid work on a ticket (Brain card H29). What a plan covers is the infrastructure: the node, the network, the panel,
 * the backups we make. Administering the customer's own system, repairing their application or writing something for
 * them is outside of it — such work is offered on the ticket with a scope, an estimate and a price, the customer
 * approves the price, and only an approved offer can be billed, for exactly the price that was approved. Nothing is
 * charged at approval; the work is billed when staff mark it done — from credit when there is enough, otherwise by an
 * invoice with the usual due date.
 */
final class WorkOfferService
{
    /** What support does as part of the service, and what it does for money. */
    public const SCOPES = ['infrastructure' => false, 'administration' => true, 'application' => true, 'development' => true];

    public function __construct(
        private readonly TicketService $tickets,
        private readonly WalletService $wallets,
        private readonly TaxEngine $tax,
        private readonly InvoiceService $invoices,
        private readonly DunningService $dunning,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    /** @param array<string,mixed> $in scope, description, price_net (decimal string), minutes? */
    public function propose(Ticket $ticket, array $in, CommandContext $context): WorkOffer
    {
        $organization = $this->organization($ticket);
        $scope = (string) ($in['scope'] ?? '');
        if (! array_key_exists($scope, self::SCOPES)) {
            throw new DomainError('work_scope_invalid', 'Scope must be one of: '.implode(', ', array_keys(self::SCOPES)).'.', 422, ['field' => 'scope']);
        }
        if (! self::SCOPES[$scope]) {
            throw new DomainError('work_in_scope', 'Work on the infrastructure is part of the service and is not billed.', 422, ['field' => 'scope']);
        }
        $description = trim((string) ($in['description'] ?? ''));
        if (mb_strlen($description) < 10 || mb_strlen($description) > 1000) {
            throw new DomainError('work_description_invalid', 'Describe the work in 10 to 1000 characters: the customer approves what they read.', 422, ['field' => 'description']);
        }
        $price = Money::decimal((string) ($in['price_net'] ?? '0'), (string) $organization->currency);
        $ceiling = Money::decimal((string) config('onhost.support.work_offer.max_net', '250000'), (string) $organization->currency);
        if (! $price->isPositive() || $ceiling->lessThan($price)) {
            throw new DomainError('work_price_invalid', 'The price must be positive and within the limit for a ticket offer.', 422, ['field' => 'price_net']);
        }
        $offer = WorkOffer::query()->create([
            'ticket_id' => $ticket->id, 'organization_id' => $organization->id, 'service_id' => $ticket->service_id, 'scope' => $scope, 'description' => $description,
            'minutes' => isset($in['minutes']) ? max(1, (int) $in['minutes']) : null, 'price_net_minor' => $price->minor, 'currency' => $price->currency->value, 'state' => WorkOffer::PROPOSED,
            'proposed_by' => $context->actorId, 'valid_until' => now()->addDays(max(1, (int) config('onhost.support.work_offer.valid_days', 14))),
        ]);
        $this->tickets->reply($ticket, 'staff', $context->actorId, 'Podpora ONhost', 'Navrhujeme placený zásah: '.$description."\nCena ".$price->format().' bez DPH'.($offer->minutes ? ' (odhad '.$offer->minutes.' min)' : '').'. Nabídka platí do '.$offer->valid_until?->format('j. n. Y').'; bez vašeho schválení nic neúčtujeme.', $context);
        $this->audit->record($context->withScope($organization->id), 'ticket.work_offer.propose', 'succeeded', ['ticket' => $ticket->number, 'scope' => $scope, 'price_net_minor' => $price->minor, 'currency' => $price->currency->value], 'work_offer', $offer->id);
        $this->outbox->publish(GenericEvent::of('ticket.work_offer.proposed', 'ticket', $ticket->id, $this->payload($offer, $ticket), $organization->id));

        return $offer;
    }

    /** The customer's answer to the price. Approval commits the organization to pay it once the work is done. */
    public function decide(WorkOffer $offer, bool $approve, CommandContext $context, ?string $note = null, ?string $authorName = null): WorkOffer
    {
        if ($approve) { // owner decision 20 (TASK-0021): the approved work is billed to the credit — the owner or the billing admin commits it
            app(CreditOrderPolicy::class)->assertMaySpend((string) $offer->organization_id, $context, 'Požádejte vlastníka, aby nabídku schválil.');
        }

        return DB::transaction(function () use ($offer, $approve, $context, $note, $authorName) {
            $offer = WorkOffer::query()->lockForUpdate()->findOrFail($offer->id);
            if ($offer->state === ($approve ? WorkOffer::APPROVED : WorkOffer::DECLINED)) {
                return $offer; // the same answer twice is one answer
            }
            if ($offer->state === WorkOffer::PROPOSED && ! $offer->isOpen()) {
                $offer->forceFill(['state' => WorkOffer::EXPIRED])->save();
            }
            if ($offer->state !== WorkOffer::PROPOSED) {
                throw new DomainError('work_offer_not_open', 'This offer can no longer be decided.', 409, ['state' => $offer->state]);
            }
            $ticket = Ticket::query()->findOrFail($offer->ticket_id);
            $offer->forceFill(['state' => $approve ? WorkOffer::APPROVED : WorkOffer::DECLINED, 'decided_by' => $context->actorId, 'decided_at' => now(), 'decision_note' => $note !== null ? mb_substr(trim($note), 0, 500) : null])->save();
            $price = Money::minor($offer->price_net_minor, $offer->currency);
            // the answer is the customer's message: it resumes the clocks and hands the ticket back to staff
            $this->tickets->reply($ticket, 'customer', $context->actorId, $authorName, $approve ? 'Schvaluji placený zásah za '.$price->format().' bez DPH.' : 'Placený zásah odmítám.'.($offer->decision_note ? ' '.$offer->decision_note : ''), $context);
            $this->audit->record($context->withScope($offer->organization_id), 'ticket.work_offer.'.($approve ? 'approve' : 'decline'), 'succeeded', ['ticket' => $ticket->number, 'price_net_minor' => $offer->price_net_minor, 'currency' => $offer->currency], 'work_offer', $offer->id);
            $this->outbox->publish(GenericEvent::of('ticket.work_offer.'.($approve ? 'approved' : 'declined'), 'ticket', $ticket->id, $this->payload($offer, $ticket), $offer->organization_id));

            return $offer;
        }, 3);
    }

    /** Staff take an offer back: before the customer answered, or when the approved work will not be done after all. */
    public function withdraw(WorkOffer $offer, CommandContext $context, string $reason): WorkOffer
    {
        if (! in_array($offer->state, [WorkOffer::PROPOSED, WorkOffer::APPROVED], true)) {
            throw new DomainError('work_offer_not_open', 'Only an open or approved offer can be withdrawn.', 409, ['state' => $offer->state]);
        }
        $ticket = Ticket::query()->findOrFail($offer->ticket_id);
        $offer->forceFill(['state' => WorkOffer::WITHDRAWN, 'decision_note' => mb_substr(trim($reason), 0, 500)])->save();
        $this->tickets->reply($ticket, 'staff', $context->actorId, 'Podpora ONhost', 'Nabídku placeného zásahu jsme stáhli; nic vám za ni neúčtujeme.', $context);
        $this->audit->record($context->withScope($offer->organization_id), 'ticket.work_offer.withdraw', 'succeeded', ['ticket' => $ticket->number, 'reason' => $reason], 'work_offer', $offer->id);

        return $offer;
    }

    /**
     * The work is done: bill it. Only an approved offer gets here, and the amount is the approved one — there is no
     * parameter for another. Credit pays when it covers the total; otherwise an invoice with the usual due date.
     */
    public function complete(WorkOffer $offer, CommandContext $context): WorkOffer
    {
        return DB::transaction(function () use ($offer, $context) {
            $offer = WorkOffer::query()->lockForUpdate()->findOrFail($offer->id);
            if ($offer->state === WorkOffer::COMPLETED) {
                return $offer;
            }
            if ($offer->state !== WorkOffer::APPROVED) {
                throw new DomainError('work_offer_not_approved', 'Work outside the plan is billed only after the customer approved its price.', 409, ['state' => $offer->state]);
            }
            $ticket = Ticket::query()->findOrFail($offer->ticket_id);
            $organization = $this->organization($ticket);
            $scoped = $context->withScope($organization->id);
            $net = Money::minor($offer->price_net_minor, $offer->currency);
            $decision = $this->tax->calculate(['country' => $organization->country, 'customer_class' => $organization->customer_class, 'vat_status' => $organization->vat_status], [['key' => 'work', 'net' => $net, 'product_class' => 'service']], $net->currency, $organization->id);
            $line = $decision['lines'][0];
            $tax = Money::minor((int) $line['tax']->minor, $net->currency);
            $gross = $net->add($tax);
            $payment = 'wallet';
            try {
                $this->wallets->charge($organization, $gross, 'services', "work-offer:{$offer->id}", $scoped, 'work_offer', $offer->id, $tax);
            } catch (DomainError $e) {
                if (! in_array($e->error, ['insufficient_funds', 'wallet_frozen', 'budget_exceeded'], true)) {
                    throw $e;
                }
                $payment = 'invoice'; // approved work that was done is owed either way; the invoice follows the ordinary reminders
            }
            $draft = $this->invoices->draft($organization, 'invoice', $net->currency->value, [[
                'sku' => 'support-work-'.$offer->scope, 'description' => 'Placený zásah podpory ('.$ticket->number.'): '.mb_substr($offer->description, 0, 160), 'qty' => 1, 'unit' => 'ks',
                'unit_net' => $net->minor, 'discount' => 0, 'net' => $net->minor, 'tax_rate' => (string) $line['rate'], 'tax_category' => (string) $line['category'], 'tax' => $tax->minor, 'total' => $gross->minor,
            ]], $scoped, null, ['payment_method' => $payment, 'work_offer_id' => $offer->id, 'ticket_id' => $ticket->id, 'tax_calculation_id' => $decision['calculation']->id]);
            $invoice = $this->invoices->issue($draft, $scoped, dueDays: $payment === 'wallet' ? 0 : (int) config('onhost.billing.invoice_due_days', 14));
            if ($payment === 'invoice') {
                $this->dunning->open($organization->id, $invoice->id, null, $invoice->due_at ?? now()->addDays(14));
            }
            $offer->forceFill(['state' => WorkOffer::COMPLETED, 'completed_at' => now(), 'invoice_id' => $invoice->id, 'payment' => $payment])->save();
            $this->tickets->reply($ticket, 'staff', $context->actorId, 'Podpora ONhost', 'Placený zásah je hotový. Vyúčtovali jsme schválenou cenu '.$net->format().' bez DPH (doklad '.$invoice->number.').', $context);
            $this->audit->record($scoped, 'ticket.work_offer.complete', 'succeeded', ['ticket' => $ticket->number, 'invoice' => $invoice->number, 'payment' => $payment, 'net_minor' => $net->minor, 'gross_minor' => $gross->minor], 'work_offer', $offer->id);
            $this->outbox->publish(GenericEvent::of('ticket.work_offer.completed', 'ticket', $ticket->id, $this->payload($offer, $ticket) + ['invoice_id' => $invoice->id, 'invoice_number' => $invoice->number, 'payment' => $payment], $organization->id));

            return $offer;
        }, 3);
    }

    /** Offers nobody decided in time stop being offers; returns how many expired. */
    public function expire(): int
    {
        return WorkOffer::query()->where('state', WorkOffer::PROPOSED)->whereNotNull('valid_until')->where('valid_until', '<', now())->update(['state' => WorkOffer::EXPIRED]);
    }

    /** @return list<array<string,mixed>> */
    public function forTicket(Ticket $ticket): array
    {
        return WorkOffer::query()->where('ticket_id', $ticket->id)->orderBy('created_at')->get()->map(fn (WorkOffer $o) => self::present($o))->all();
    }

    /** @return array<string,mixed> */
    public static function present(WorkOffer $o): array
    {
        return [
            'id' => $o->id, 'ticket_id' => $o->ticket_id, 'scope' => $o->scope, 'description' => $o->description, 'minutes' => $o->minutes,
            'price_net' => ['minor' => $o->price_net_minor, 'currency' => $o->currency, 'formatted' => Money::minor($o->price_net_minor, $o->currency)->format()],
            'state' => $o->state === WorkOffer::PROPOSED && ! $o->isOpen() ? WorkOffer::EXPIRED : $o->state, 'valid_until' => $o->valid_until?->toIso8601String(),
            'decided_at' => $o->decided_at?->toIso8601String(), 'completed_at' => $o->completed_at?->toIso8601String(), 'invoice_id' => $o->invoice_id, 'payment' => $o->payment,
        ];
    }

    private function organization(Ticket $ticket): Organization
    {
        $organization = $ticket->organization_id !== null ? Organization::query()->find($ticket->organization_id) : null;
        if ($organization === null) {
            throw new DomainError('work_offer_needs_customer', 'Paid work can be offered only on a ticket of a customer organization.', 422);
        }

        return $organization;
    }

    /** @return array<string,mixed> */
    private function payload(WorkOffer $offer, Ticket $ticket): array
    {
        return ['offer_id' => $offer->id, 'number' => $ticket->number, 'subject' => $ticket->subject, 'scope' => $offer->scope, 'price' => ['minor' => $offer->price_net_minor, 'currency' => $offer->currency], 'valid_until' => $offer->valid_until?->toIso8601String()];
    }
}
