<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Shared\Support\MoneyFormatter;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user()?->customer;

        if ($customer === null) {
            return response()->json(['error' => 'No customer account.'], 403);
        }

        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->paginate(20);

        $data = $invoices->getCollection()->map(fn (Invoice $i) => [
            'id'         => $i->id,
            'number'     => $i->number,
            'status'     => $i->status->value,
            'total'      => MoneyFormatter::format($i->total),
            'due_date'   => $i->due_date?->toDateString(),
            'paid_at'    => $i->paid_at?->toIso8601String(),
            'created_at' => $i->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'data'  => $data,
            'meta'  => [
                'current_page' => $invoices->currentPage(),
                'last_page'    => $invoices->lastPage(),
                'per_page'     => $invoices->perPage(),
                'total'        => $invoices->total(),
            ],
        ]);
    }
}
