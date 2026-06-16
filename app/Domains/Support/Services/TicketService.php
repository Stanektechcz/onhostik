<?php

declare(strict_types=1);

namespace App\Domains\Support\Services;

use App\Domains\Customer\Models\Customer;
use App\Domains\Support\Enums\TicketPriority;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Models\SupportTicketMessage;
use App\Models\User;
use App\Notifications\TicketRepliedNotification;
use Illuminate\Support\Facades\DB;

/**
 * All support ticket state transitions live here — controllers stay thin
 * and every transition writes both a ticket event row and an audit entry.
 */
final class TicketService
{
    public function open(
        Customer $customer,
        User $author,
        string $subject,
        string $message,
        TicketPriority $priority = TicketPriority::Normal,
        ?string $department = null,
    ): SupportTicket {
        $ticket = DB::transaction(function () use ($customer, $author, $subject, $message, $priority, $department): SupportTicket {
            $ticket = SupportTicket::create([
                'customer_id'   => $customer->id,
                'subject'       => $subject,
                'status'        => TicketStatus::Open,
                'priority'      => $priority,
                'department'    => $department,
                'last_reply_at' => now(),
            ]);

            $ticket->messages()->create([
                'user_id'  => $author->id,
                'is_staff' => false,
                'message'  => $message,
            ]);

            $ticket->events()->create([
                'user_id' => $author->id,
                'event'   => 'created',
                'payload' => ['priority' => $priority->value, 'department' => $department],
            ]);

            return $ticket;
        });

        activity('support')
            ->performedOn($ticket)
            ->causedBy($author)
            ->withProperties(['subject' => $subject, 'priority' => $priority->value])
            ->log('support.ticket_created');

        return $ticket;
    }

    public function reply(SupportTicket $ticket, User $author, string $message, bool $isStaff): SupportTicketMessage
    {
        $reply = DB::transaction(function () use ($ticket, $author, $message, $isStaff): SupportTicketMessage {
            $reply = $ticket->messages()->create([
                'user_id'  => $author->id,
                'is_staff' => $isStaff,
                'message'  => $message,
            ]);

            $ticket->update([
                'status'        => $isStaff ? TicketStatus::Answered : TicketStatus::Open,
                'last_reply_at' => now(),
                'closed_at'     => null,
            ]);

            $ticket->events()->create([
                'user_id' => $author->id,
                'event'   => 'replied',
                'payload' => ['is_staff' => $isStaff],
            ]);

            return $reply;
        });

        activity('support')
            ->performedOn($ticket)
            ->causedBy($author)
            ->withProperties(['is_staff' => $isStaff])
            ->log('support.ticket_replied');

        if ($isStaff) {
            $ticket->customer?->user?->notify(new TicketRepliedNotification($ticket, $reply));
        }

        return $reply;
    }

    public function changeStatus(SupportTicket $ticket, User $actor, TicketStatus $status): void
    {
        if ($ticket->status === $status) {
            return;
        }

        $previous = $ticket->status;

        $ticket->update([
            'status'    => $status,
            'closed_at' => $status === TicketStatus::Closed ? now() : null,
        ]);

        $ticket->events()->create([
            'user_id' => $actor->id,
            'event'   => 'status_changed',
            'payload' => ['from' => $previous->value, 'to' => $status->value],
        ]);

        activity('support')
            ->performedOn($ticket)
            ->causedBy($actor)
            ->withProperties(['from' => $previous->value, 'to' => $status->value])
            ->log('support.status_changed');
    }

    public function changePriority(SupportTicket $ticket, User $actor, TicketPriority $priority): void
    {
        if ($ticket->priority === $priority) {
            return;
        }

        $previous = $ticket->priority;

        $ticket->update(['priority' => $priority]);

        $ticket->events()->create([
            'user_id' => $actor->id,
            'event'   => 'priority_changed',
            'payload' => ['from' => $previous->value, 'to' => $priority->value],
        ]);

        activity('support')
            ->performedOn($ticket)
            ->causedBy($actor)
            ->withProperties(['from' => $previous->value, 'to' => $priority->value])
            ->log('support.priority_changed');
    }
}
