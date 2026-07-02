<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Shared\Support\MoneyFormatter;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
                'amount'         => $balance->getMinorAmount()->toInt(),
                'currency'       => $balance->getCurrency()->getCurrencyCode(),
                'formatted'      => MoneyFormatter::format($balance),
            ],
        ]);
    }
}
