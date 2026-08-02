<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V2;

use App\Domains\Api\Support\ApiQuery;
use App\Domains\Api\Support\ProblemDetails;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Shared\Support\MoneyFormatter;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Invoices v2 — cursor paginated, sortable, filterable, sparse-fieldset aware.
 */
final class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user()?->customer;

        if ($customer === null) {
            return ProblemDetails::make(403, 'no-customer-account', 'Účet bez zákaznického profilu',
                'K tomuto tokenu není přiřazen zákaznický účet.', $request);
        }

        $query = ApiQuery::apply(
            Invoice::query()->where('customer_id', $customer->id),
            $request,
            sortable: ['id', 'number', 'status', 'due_date', 'paid_at', 'created_at'],
            filterable: ['status', 'currency'],
        );

        $paginator = ApiQuery::paginate($query, $request);

        $rows = collect($paginator->items())
            ->map(fn (Invoice $i): array => [
                'id'         => $i->id,
                'number'     => $i->number,
                'status'     => $i->status->value,
                'currency'   => $i->currency->value,
                'total'      => MoneyFormatter::format($i->total),
                'total_minor' => $i->total->getMinorAmount()->toInt(),
                'due_date'   => $i->due_date?->toDateString(),
                'paid_at'    => $i->paid_at?->toIso8601String(),
                'created_at' => $i->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return response()->json(ApiQuery::envelope($paginator, ApiQuery::sparse($rows, $request)));
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $customer = $request->user()?->customer;

        if ($customer === null || $invoice->customer_id !== $customer->id) {
            return ProblemDetails::make(403, 'forbidden', 'Přístup odepřen',
                'Tato faktura nepatří k vašemu účtu.', $request);
        }

        return response()->json(['data' => [
            'id'          => $invoice->id,
            'number'      => $invoice->number,
            'status'      => $invoice->status->value,
            'currency'    => $invoice->currency->value,
            'subtotal'    => MoneyFormatter::format($invoice->subtotal),
            'total'       => MoneyFormatter::format($invoice->total),
            'total_minor' => $invoice->total->getMinorAmount()->toInt(),
            'due_date'    => $invoice->due_date?->toDateString(),
            'paid_at'     => $invoice->paid_at?->toIso8601String(),
            'created_at'  => $invoice->created_at?->toIso8601String(),
        ]]);
    }
}
