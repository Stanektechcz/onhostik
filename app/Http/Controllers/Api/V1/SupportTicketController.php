<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Support\Enums\TicketPriority;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Models\SupportTicketMessage;
use App\Domains\Support\Services\TicketService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            ->map(fn (SupportTicket $t) => $this->ticketPayload($t));

        return response()->json(['data' => $tickets]);
    }

    public function store(Request $request, TicketService $tickets): JsonResponse
    {
        if (! $request->user()?->tokenCan('write:tickets')) {
            return response()->json(['error' => 'Token nemá oprávnění write:tickets.'], 403);
        }

        $customer = $request->user()->customer;

        if ($customer === null) {
            return response()->json(['error' => 'No customer account.'], 403);
        }

        $validated = $request->validate([
            'subject'    => ['required', 'string', 'min:3', 'max:150'],
            'message'    => ['required', 'string', 'min:10', 'max:5000'],
            'priority'   => ['nullable', Rule::enum(TicketPriority::class)],
            'department' => ['nullable', 'string', 'max:50'],
        ]);

        $ticket = $tickets->open(
            customer: $customer,
            author: $request->user(),
            subject: $validated['subject'],
            message: $validated['message'],
            priority: TicketPriority::tryFrom((string) ($validated['priority'] ?? '')) ?? TicketPriority::Normal,
            department: is_string($validated['department'] ?? null) ? $validated['department'] : null,
        );

        return response()->json(['data' => $this->ticketPayload($ticket)], 201);
    }

    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        $customer = $request->user()?->customer;

        if ($customer === null || $ticket->customer_id !== $customer->id) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        $ticket->load('messages.author');

        $messages = $ticket->messages->map(fn (SupportTicketMessage $m) => [
            'id'         => $m->id,
            'author'     => $m->author?->name,
            'is_staff'   => $m->is_staff,
            'message'    => $m->message,
            'created_at' => $m->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'data' => array_merge($this->ticketPayload($ticket), ['messages' => $messages->all()]),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket, TicketService $tickets): JsonResponse
    {
        if (! $request->user()?->tokenCan('write:tickets')) {
            return response()->json(['error' => 'Token nemá oprávnění write:tickets.'], 403);
        }

        $customer = $request->user()->customer;

        if ($customer === null || $ticket->customer_id !== $customer->id) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        if ($ticket->status === TicketStatus::Closed) {
            return response()->json(['error' => 'Cannot reply to a closed ticket.'], 422);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        $reply = $tickets->reply($ticket, $request->user(), $validated['message'], isStaff: false);

        return response()->json([
            'data' => [
                'id'         => $reply->id,
                'message'    => $reply->message,
                'created_at' => $reply->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function close(Request $request, SupportTicket $ticket, TicketService $tickets): JsonResponse
    {
        if (! $request->user()?->tokenCan('write:tickets')) {
            return response()->json(['error' => 'Token nemá oprávnění write:tickets.'], 403);
        }

        $customer = $request->user()->customer;

        if ($customer === null || $ticket->customer_id !== $customer->id) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        if ($ticket->status === TicketStatus::Closed) {
            return response()->json(['error' => 'Ticket is already closed.'], 422);
        }

        $tickets->changeStatus($ticket, $request->user(), TicketStatus::Closed);

        return response()->json(['data' => $this->ticketPayload($ticket->fresh() ?? $ticket)]);
    }

    /** @return array<string, mixed> */
    private function ticketPayload(SupportTicket $t): array
    {
        return [
            'id'            => $t->id,
            'uuid'          => $t->uuid,
            'subject'       => $t->subject,
            'status'        => $t->status->value,
            'priority'      => $t->priority->value,
            'department'    => $t->department,
            'last_reply_at' => $t->last_reply_at?->toIso8601String(),
            'closed_at'     => $t->closed_at?->toIso8601String(),
            'created_at'    => $t->created_at?->toIso8601String(),
        ];
    }
}
