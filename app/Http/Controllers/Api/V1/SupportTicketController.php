<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Support\Models\SupportTicket;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user()?->customer;

        if ($customer === null) {
            return response()->json(['error' => 'No customer account.'], 403);
        }

        $tickets = SupportTicket::query()
            ->where('customer_id', $customer->id)
            ->latest('last_reply_at')
            ->limit(50)
            ->get()
            ->map(fn (SupportTicket $t) => [
                'id'           => $t->id,
                'uuid'         => $t->uuid,
                'subject'      => $t->subject,
                'status'       => $t->status->value,
                'priority'     => $t->priority->value,
                'department'   => $t->department,
                'last_reply_at' => $t->last_reply_at?->toIso8601String(),
                'closed_at'    => $t->closed_at?->toIso8601String(),
                'created_at'   => $t->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $tickets]);
    }
}
