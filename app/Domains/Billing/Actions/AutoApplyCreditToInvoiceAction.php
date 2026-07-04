<?php

declare(strict_types=1);

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Exceptions\InsufficientCreditException;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\CreditLedger;
use InvalidArgumentException;

/**
 * Attempts to pay a single invoice from the customer's credit balance.
 * Returns true on success, false when the balance is insufficient or the
 * invoice is already paid / not in a payable state.
 *
 * Designed to be called after an invoice is issued so renewal / new orders
 * are paid automatically when the customer has enough credit.
 */
final class AutoApplyCreditToInvoiceAction
{
    public function __construct(
        private readonly CreditLedger $ledger,
        private readonly PayInvoiceWithCreditAction $payAction,
    ) {}

    public function execute(Invoice $invoice): bool
    {
        if (!$invoice->status->isOpen()) {
            return false;
        }

        $customer = $invoice->customer;

        if ($customer === null) {
            return false;
        }

        $balance = $this->ledger->getBalance($customer);

        if ($balance->isLessThan($invoice->total)) {
            return false;
        }

        try {
            $this->payAction->execute($invoice);

            return true;
        } catch (InsufficientCreditException) {
            return false;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
