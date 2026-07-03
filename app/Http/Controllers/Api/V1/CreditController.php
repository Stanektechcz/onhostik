<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Billing\Actions\CreateCreditTopUpInvoiceAction;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Shared\Support\MoneyFormatter;
use App\Http\Controllers\Controller;
use Brick\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CreditController extends Controller
{
    public function balance(Request $request, CreditLedger $ledger): JsonResponse
    {
        $customer = $request->user()?->customer;

        if ($customer === null) {
            return response()->json(['error' => 'No customer account.'], 403);
        }

        $balance = $ledger->getBalance($customer);

        return response()->json([
            'data' => [
                'amount'    => $balance->getMinorAmount()->toInt(),
                'currency'  => $balance->getCurrency()->getCurrencyCode(),
                'formatted' => MoneyFormatter::format($balance),
            ],
        ]);
    }

    public function topup(Request $request, CreateCreditTopUpInvoiceAction $action): JsonResponse
    {
        if (! $request->user()?->tokenCan('write:credit')) {
            return response()->json(['error' => 'Token nemá oprávnění write:credit.'], 403);
        }

        $customer = $request->user()->customer;

        if ($customer === null) {
            return response()->json(['error' => 'No customer account.'], 403);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000'],
        ]);

        try {
            $invoice = $action->execute(
                $customer,
                Money::of($validated['amount'], $customer->preferred_currency->value),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->number,
                'amount'     => $invoice->total->getMinorAmount()->toInt(),
                'currency'   => $invoice->total->getCurrency()->getCurrencyCode(),
                'pay_url'    => route('panel.billing.invoices.show', $invoice),
            ],
        ], 201);
    }
}
