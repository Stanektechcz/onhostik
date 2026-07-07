<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Support\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SlaEscalationNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly SupportTicket $ticket) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Eskalace SLA — ticket #' . $this->ticket->id)
            ->line('Ticket **#' . $this->ticket->id . '** překročil SLA deadline a nebyl vyřešen.')
            ->line('Předmět: ' . $this->ticket->subject)
            ->line('SLA deadline: ' . $this->ticket->sla_deadline?->format('d.m.Y H:i'))
            ->action('Zobrazit ticket', url('/'))
            ->line('Eskalováno automaticky systémem OnHost.');
    }
}
