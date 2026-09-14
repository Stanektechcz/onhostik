<?php

declare(strict_types=1);

namespace Onhost\Domain\Partners\Listeners;

use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Platform\Outbox\OutboxMessage;

/** `onhost.invoice.paid` → commission line; `onhost.invoice.issued` (credit notes) → reversal. */
final class AccruePartnerCommission
{
    public function __construct(private readonly PartnerService $partners) {}

    public function handle(OutboxMessage $message): void
    {
        if ($message->aggregate_type !== 'invoice') {
            return;
        }
        $invoice = Invoice::query()->find($message->aggregate_id);
        if ($invoice === null) {
            return;
        }
        match ($message->name) {
            'invoice.paid' => $this->partners->accrueForInvoice($invoice),
            'invoice.issued' => $invoice->type === 'credit_note' ? $this->partners->reverseForCreditNote($invoice) : null,
            default => null,
        };
    }
}
