<?php

declare(strict_types=1);

namespace App\Domains\Support\Services;

use App\Domains\Customer\Models\Customer;
use App\Domains\Support\Enums\TicketPriority;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Models\SupportTicketMessage;
use App\Jobs\AnalyzeTicketWithAiJob;
use App\Models\NpsResponse;
use App\Models\User;
use App\Notifications\NpsSurveyNotification;
use App\Notifications\TicketRatingRequestNotification;
use App\Notifications\TicketRepliedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
                'sla_deadline'  => now()->addHours($priority->slaHours()),
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

        AnalyzeTicketWithAiJob::dispatch($ticket, $author);

        $this->fireWebhook('ticket.created', $ticket);

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

        $this->fireWebhook('ticket.replied', $ticket);

        return $reply;
    }

    public function addInternalNote(SupportTicket $ticket, User $staff, string $note): SupportTicketMessage
    {
        $message = $ticket->messages()->create([
            'user_id'     => $staff->id,
            'is_staff'    => true,
            'is_internal' => true,
            'message'     => $note,
        ]);

        $ticket->events()->create([
            'user_id' => $staff->id,
            'event'   => 'internal_note',
            'payload' => ['length' => mb_strlen($note)],
        ]);

        activity('support')
            ->performedOn($ticket)
            ->causedBy($staff)
            ->log('support.internal_note_added');

        return $message;
    }

    public function setSlaDeadline(SupportTicket $ticket, User $actor, ?\DateTimeInterface $deadline): void
    {
        $ticket->update(['sla_deadline' => $deadline]);

        $ticket->events()->create([
            'user_id' => $actor->id,
            'event'   => 'sla_set',
            'payload' => ['deadline' => $deadline?->format('Y-m-d H:i')],
        ]);

        activity('support')
            ->performedOn($ticket)
            ->causedBy($actor)
            ->withProperties(['sla_deadline' => $deadline?->format('Y-m-d H:i')])
            ->log('support.sla_set');
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

        $webhookEvent = $status === TicketStatus::Closed ? 'ticket.closed' : 'ticket.status_changed';
        $this->fireWebhook($webhookEvent, $ticket);

        if ($status === TicketStatus::Closed) {
            $this->sendNpsSurvey($ticket, $actor);
            $this->sendRatingRequest($ticket, $actor);
        }
    }

    private function sendNpsSurvey(SupportTicket $ticket, User $actor): void
    {
        $customer = $ticket->customer;

        if ($customer === null) {
            return;
        }

        // Only send to the ticket owner, not to the admin who closed it
        $user = $customer->user;

        if ($user === null || $user->id === $actor->id) {
            return;
        }

        // Avoid duplicate surveys for the same ticket
        $alreadySent = NpsResponse::query()
            ->where('ticket_id', $ticket->id)
            ->exists();

        if ($alreadySent) {
            return;
        }

        try {
            $npsResponse = NpsResponse::create([
                'customer_id'  => $customer->id,
                'ticket_id'    => $ticket->id,
                'survey_token' => \Illuminate\Support\Str::random(48),
                'notified_at'  => now(),
            ]);

            $user->notify(new NpsSurveyNotification($npsResponse, $ticket));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function sendRatingRequest(SupportTicket $ticket, User $actor): void
    {
        $user = $ticket->customer?->user;

        if ($user === null || $user->id === $actor->id) {
            return;
        }

        // Skip if already rated
        if ($ticket->rating !== null) {
            return;
        }

        try {
            $user->notify(new TicketRatingRequestNotification($ticket));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function fireWebhook(string $event, SupportTicket $ticket): void
    {
        try {
            /** @var HelpdeskWebhookService $svc */
            $svc = app(HelpdeskWebhookService::class);
            $svc->fire($event, $ticket);
        } catch (\Throwable $e) {
            Log::warning("Helpdesk webhook fire failed for {$event}: {$e->getMessage()}");
        }
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
