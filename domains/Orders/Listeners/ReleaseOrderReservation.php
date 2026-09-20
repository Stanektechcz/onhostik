<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders\Listeners;

use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Orders\OrderSettlement;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxMessage;

/**
 * `onhost.invoice.paid`: a postpaid order reserved its share of the customer's credit line when it was placed and keeps
 * it until its invoice is paid — however it was paid (from credit, by card, by a transfer finance recorded by hand).
 */
final class ReleaseOrderReservation
{
    public function __construct(private readonly OrderSettlement $orders) {}

    public function handle(OutboxMessage $message): void
    {
        $invoice = Invoice::query()->find($message->aggregate_id);
        if ($invoice === null || $invoice->state !== Invoice::PAID) {
            return;
        }
        $this->orders->releaseReservation($invoice, CommandContext::system("invoice {$invoice->number} paid"));
    }
}
