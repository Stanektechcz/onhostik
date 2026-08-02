<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V2;

use App\Domains\Api\Support\ApiQuery;
use App\Domains\Api\Support\ProblemDetails;
use App\Domains\Support\Models\SupportTicket;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Support tickets v2 — cursor paginated, filterable by status/priority.
 */
final class SupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user()?->customer;

        if ($customer === null) {
            return ProblemDetails::make(403, 'no-customer-account', 'Účet bez zákaznického profilu',
                'K tomuto tokenu není přiřazen zákaznický účet.', $request);
        }

        $query = ApiQuery::apply(
            SupportTicket::query()->where('customer_id', $customer->id),
            $request,
            sortable: ['id', 'status', 'priority', 'created_at', 'last_reply_at'],
            filterable: ['status', 'priority', 'department'],
        );

        $paginator = ApiQuery::paginate($query, $request);

        $rows = collect($paginator->items())
            ->map(fn (SupportTicket $t): array => [
                'id'            => $t->id,
                'subject'       => $t->subject,
                'status'        => $t->status->value,
                'priority'      => $t->priority->value,
                'department'    => $t->department,
                'last_reply_at' => $t->last_reply_at?->toIso8601String(),
                'created_at'    => $t->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return response()->json(ApiQuery::envelope($paginator, ApiQuery::sparse($rows, $request)));
    }
}
