<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V2;

use App\Domains\Api\Support\ApiQuery;
use App\Domains\Api\Support\ProblemDetails;
use App\Domains\Billing\Models\Order;
use App\Domains\Shared\Support\MoneyFormatter;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Orders v2 — cursor paginated with line items on the detail view.
 */
final class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user()?->customer;

        if ($customer === null) {
            return ProblemDetails::make(403, 'no-customer-account', 'Účet bez zákaznického profilu',
                'K tomuto tokenu není přiřazen zákaznický účet.', $request);
        }

        $query = ApiQuery::apply(
            Order::query()->where('customer_id', $customer->id),
            $request,
            sortable: ['id', 'status', 'created_at', 'paid_at'],
            filterable: ['status', 'currency'],
        );

        $paginator = ApiQuery::paginate($query, $request);

        $rows = collect($paginator->items())
            ->map(fn (Order $o): array => [
                'id'          => $o->id,
                'status'      => $o->status->value,
                'currency'    => $o->currency->value,
                'total'       => MoneyFormatter::format($o->total),
                'total_minor' => $o->total->getMinorAmount()->toInt(),
                'paid_at'     => $o->paid_at?->toIso8601String(),
                'created_at'  => $o->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return response()->json(ApiQuery::envelope($paginator, ApiQuery::sparse($rows, $request)));
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $customer = $request->user()?->customer;

        if ($customer === null || $order->customer_id !== $customer->id) {
            return ProblemDetails::make(403, 'forbidden', 'Přístup odepřen',
                'Tato objednávka nepatří k vašemu účtu.', $request);
        }

        $order->loadMissing('items');

        return response()->json(['data' => [
            'id'          => $order->id,
            'status'      => $order->status->value,
            'currency'    => $order->currency->value,
            'subtotal'    => MoneyFormatter::format($order->subtotal),
            'total'       => MoneyFormatter::format($order->total),
            'total_minor' => $order->total->getMinorAmount()->toInt(),
            'paid_at'     => $order->paid_at?->toIso8601String(),
            'created_at'  => $order->created_at?->toIso8601String(),
            'items'       => $order->items->map(fn ($item): array => [
                'id'          => $item->id,
                'description' => $item->description,
                'quantity'    => $item->quantity,
                'total'       => MoneyFormatter::format($item->total),
            ])->values()->all(),
        ]]);
    }
}
