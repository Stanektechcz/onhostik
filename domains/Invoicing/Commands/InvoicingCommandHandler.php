<?php

declare(strict_types=1);

namespace Onhost\Domain\Invoicing\Commands;

use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\Models\PaymentStateMachineStates as PaymentState;
use Onhost\Domain\Payments\PaymentService;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

final class InvoicingCommandHandler implements CommandHandler
{
    public function __construct(private readonly InvoiceService $invoices, private readonly WalletService $wallets, private readonly PaymentService $payments) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof InvoiceCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $invoice = Invoice::query()->find((string) $command->get('invoice_id'));
        if ($invoice === null || $invoice->organization_id !== $command->organizationId) {
            throw DomainError::notFound('invoice');
        }

        return match ($command->op()) {
            'pay_from_wallet' => $this->payFromWallet($invoice, $command, $context),
            'pay_by_bank' => $this->payByIntent($invoice, $command, $context, 'bank'),
            'pay_by_card' => $this->payByIntent($invoice, $command, $context, 'gateway'),
            'credit_note' => ['invoice' => $this->invoices->creditNote($invoice, (string) $command->get('reason', ''), $context, $command->get('line_ids'), $command->get('incident_ref'))],
            'mark_paid' => ['invoice' => $this->invoices->markPaid($invoice, $invoice->total()->subtract(Money::minor((int) $invoice->paid_minor, $invoice->currency)), (string) $command->get('method', 'bank'), $context, true, $command->get('reference'))],
            default => throw new DomainError('invoice_op_unknown', "Unknown invoice operation {$command->op()}.", 422),
        };
    }

    /**
     * Bank transfer (instructions + variable symbol) or the card gateway (redirect) for an open document. A proforma of an
     * unpaid order already has the order's transfer waiting — that one is returned instead of a second symbol.
     *
     * @return array<string,mixed>
     */
    private function payByIntent(Invoice $invoice, InvoiceCommand $command, CommandContext $context, string $channel): array
    {
        if (! in_array($invoice->state, [Invoice::ISSUED, Invoice::OVERDUE], true)) {
            throw new DomainError('invoice_not_payable', "Invoice {$invoice->number} is {$invoice->state}.", 409);
        }
        $open = $invoice->outstanding();
        if (! $open->isPositive()) {
            throw new DomainError('invoice_not_payable', "Invoice {$invoice->number} has nothing left to pay.", 409);
        }
        $provider = $channel === 'bank' ? 'bank' : (string) config('onhost.payments.default', 'comgate');
        $organization = Organization::query()->findOrFail($invoice->organization_id);
        $intent = null;
        if ($invoice->type === 'proforma' && $invoice->order_id !== null) {
            $intent = PaymentIntent::query()->where('organization_id', $organization->id)->where('purpose', 'order')->where('reference_id', $invoice->order_id)->where('provider', $provider)
                ->whereIn('state', [PaymentState::CREATED, PaymentState::PENDING_CUSTOMER])->orderByDesc('created_at')->first();
        }
        $intent ??= $this->payments->createIntent($organization, $open, 'invoice', 'invoice', $invoice->id, $context, [
            'provider' => $provider, 'method' => $channel === 'bank' ? 'bank_transfer' : (string) $command->get('method', 'card'), 'return_urls' => (array) $command->get('return_urls', []),
            'reference' => $invoice->payment_reference ?: preg_replace('/\D/', '', (string) $invoice->number), 'description' => ($invoice->type === 'proforma' ? 'Zálohová faktura ' : 'Faktura ').$invoice->number, 'email' => $organization->billing_email,
        ]);

        return [
            'invoice_id' => $invoice->id, 'number' => $invoice->number, 'state' => $invoice->state, 'amount' => $open, 'payment_intent_id' => $intent->id, 'provider' => $intent->provider,
            'payment_state' => $intent->state, 'redirect_url' => $intent->redirect_url, 'instructions' => $intent->raw['instructions'] ?? null,
        ];
    }

    private function payFromWallet(Invoice $invoice, InvoiceCommand $command, CommandContext $context): array
    {
        if (! in_array($invoice->state, [Invoice::ISSUED, Invoice::OVERDUE], true)) {
            throw new DomainError('invoice_not_payable', "Invoice {$invoice->number} is {$invoice->state}.", 409);
        }
        $open = $invoice->total()->subtract(Money::minor((int) $invoice->paid_minor, $invoice->currency));
        $this->wallets->charge($invoice->organization_id, $open, $invoice->meta['revenue_family'] ?? 'services', "invoice:{$invoice->id}:wallet", $context, 'invoice', $invoice->id, Money::minor((int) round($invoice->tax_minor * ($open->minor / max(1, $invoice->total_minor))), $invoice->currency));
        $paid = $this->invoices->markPaid($invoice, $open, 'wallet', $context, postLedger: false);

        return ['invoice_id' => $paid->id, 'number' => $paid->number, 'state' => $paid->state, 'paid' => $open];
    }
}
