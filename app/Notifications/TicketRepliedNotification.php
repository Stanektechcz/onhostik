<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Models\SupportTicketMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketRepliedNotification extends Notification
{
    public function __construct(
        private readonly SupportTicket $ticket,
        private readonly SupportTicketMessage $reply,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'icon'  => 'message-circle',
            'color' => 'info',
            'title' => "Odpověď na ticket — {$this->ticket->subject}",
            'body'  => mb_substr($this->reply->message, 0, 120),
            'url'   => route('panel.support.show', $this->ticket),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticket  = $this->ticket;
        $preview = mb_substr($this->reply->message, 0, 200);

        return (new MailMessage)
            ->subject("Odpověď na ticket #{$ticket->id}: {$ticket->subject}")
            ->greeting('Dobrý den,')
            ->line("Váš support ticket **{$ticket->subject}** obdržel odpověď od našeho týmu.")
            ->line('> ' . $preview . (mb_strlen($this->reply->message) > 200 ? '…' : ''))
            ->action('Zobrazit celou odpověď', route('panel.support.show', $ticket))
            ->salutation('S pozdravem, tým Onhost.cz');
    }
}
