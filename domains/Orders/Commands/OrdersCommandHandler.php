<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders\Commands;

use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\Quote;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

final class OrdersCommandHandler implements CommandHandler
{
    public function __construct(private readonly CheckoutService $checkout, private readonly QuoteService $quotes, private readonly WalletService $wallets) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if ($command instanceof StaffCustomerCommand) {
            return $this->staff($command, $context);
        }
        if ($command instanceof ReviewOrderCommand) { // staff decision on a held order (audit §5f-8)
            $order = Order::query()->find((string) $command->get('order_id'));
            if ($order === null) {
                throw DomainError::notFound('order');
            }
            $reviewed = $this->checkout->review($order, (string) $command->get('decision'), $context->withScope($order->organization_id), $command->get('reason'));

            return ['order_id' => $reviewed->id, 'number' => $reviewed->number, 'state' => $reviewed->state, 'review' => $reviewed->meta['review'] ?? null];
        }
        if (! $command instanceof PlaceOrderCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $organization = Organization::query()->findOrFail($command->organizationId);
        $quote = Quote::query()->find((string) $command->get('quote_id'));
        if ($quote === null) {
            throw new DomainError('quote_not_found', 'The quote does not exist; refresh the cart.', 404, ['field' => 'quote_id']);
        }
        $user = $context->actorType === 'user' && $context->actorId !== null ? User::query()->find($context->actorId) : null;
        $result = $this->checkout->placeOrder($quote, $organization, $user, (array) $command->get('consents', []), (array) $command->get('payment', ['mode' => 'wallet']), $command->idempotencyKey, $context, (string) $command->get('source', 'web'));

        $order = $result['order']->fresh();

        return ['order_id' => $order->id, 'number' => $order->number, 'state' => $order->state, 'redirect_url' => $result['redirect_url'], 'payment_intent_id' => $result['payment_intent_id'], 'bank_instructions' => $result['bank_instructions'], 'total' => (int) $order->total_minor, 'subtotal' => (int) $order->subtotal_minor, 'discount' => (int) $order->discount_minor, 'tax' => (int) $order->tax_minor, 'currency' => $order->currency, 'payment_mode' => $order->payment_mode];
    }

    /** Staff work on a customer's account (audit §5y): a manual credit, an order placed on the customer's behalf. */
    private function staff(StaffCustomerCommand $command, CommandContext $context): array
    {
        $organization = Organization::query()->find((string) $command->get('organization_id'));
        if ($organization === null) {
            throw DomainError::notFound('organization');
        }
        $ctx = $context->withScope($organization->id);
        $note = trim((string) $command->get('note', ''));
        if ($command->op() === 'wallet.credit') {
            if ($note === '') {
                throw new DomainError('note_required', 'Uveďte důvod připsání kreditu (zapíše se do auditu i k pohybu v peněžence).', 422, ['field' => 'note']);
            }
            $currency = (string) ($command->get('currency') ?: ($organization->currency ?? 'CZK'));
            $amount = Money::decimal((string) $command->get('amount'), $currency);
            $max = (float) config('onhost.billing.manual_credit_max', 100000);
            if ((float) $amount->toDecimal() > $max) {
                throw new DomainError('amount_too_large', "Ruční připsání je omezené na {$max} {$currency}; větší částku připište ve více krocích.", 422, ['field' => 'amount']);
            }
            $promo = $command->get('kind') === 'promo';
            $topup = $this->wallets->topup($organization, $amount, $promo ? 'promo' : 'manual', 'staff-credit:'.$command->idempotencyKey, $ctx, note: 'Ruční připsání: '.$note, promo: $promo, bankProvider: $promo ? null : 'manual');

            return ['topup_id' => $topup->id, 'amount' => $amount, 'kind' => $promo ? 'promo' : 'manual', 'balances' => $this->wallets->balances($organization, $currency), 'spendable' => $this->wallets->spendable($organization, $currency)];
        }
        if ($command->op() !== 'order.assisted') {
            throw new \LogicException('Unsupported staff customer op '.$command->op());
        }
        if ($note === '') {
            throw new DomainError('note_required', 'Uveďte, na čí žádost objednávku zadáváte (např. číslo tiketu nebo telefonát).', 422, ['field' => 'note']);
        }
        $currency = (string) ($organization->currency ?? 'CZK');
        $quote = $this->quotes->quote((array) $command->get('items', []), $currency, ['country' => $organization->country ?? 'CZ', 'customer_class' => $organization->customer_class ?? 'b2c', 'vat_status' => $organization->vat_status ?? 'unknown', 'ip_country' => null], (int) $command->get('commit_months', 1), null, $organization);
        $staff = $context->actorType === 'user' && $context->actorId !== null ? User::query()->find($context->actorId) : null;
        $consents = [];
        foreach ($this->checkout->requiredDocuments($quote, $organization) as $key) { // assisted order: staff confirm the documents on the customer's request, recorded as such
            $consents[$key] = ['person' => mb_substr(($staff !== null ? (string) $staff->name : 'ONhost').' · asistovaná objednávka: '.$note, 0, 250), 'language' => $organization->locale];
        }
        $mode = (string) $command->get('payment', 'bank');
        $result = $this->checkout->placeOrder($quote, $organization, null, $consents, ['mode' => $mode], 'staff-order:'.$command->idempotencyKey, $ctx, 'staff');
        $order = $result['order']->fresh();

        return ['order_id' => $order->id, 'number' => $order->number, 'state' => $order->state, 'total' => $order->total(), 'payment_mode' => $mode, 'bank_instructions' => $result['bank_instructions'], 'spendable' => $this->wallets->spendable($organization, $currency)];
    }
}
