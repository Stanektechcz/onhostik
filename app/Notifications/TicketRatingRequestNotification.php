<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Support\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Prompts the customer to rate a newly-closed support ticket.
 * Sent once per ticket at close time; respects 'support' notification prefs.
 */
class TicketRatingRequestNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly SupportTicket $ticket) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $channels = [];
        if ($notifiable->wantsNotification('support', 'mail')) {
            $channels[] = 'mail';
        }
        if ($notifiable->wantsNotification('support', 'database')) {
            $channels[] = 'database';
        }
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject("Ticket #{$this->ticket->id} byl uzavřen – ohodnoťte nás")
            ->greeting('Váš ticket byl uzavřen.')
            ->line("Ticket **#{$this->ticket->id}: {$this->ticket->subject}** byl uzavřen.")
            ->line('Rádi bychom znali váš názor. Ohodnoťte prosím naši podporu — zabere to jen chvíli.')
            ->action('Ohodnotit ticket', route('panel.support.show', $this->ticket))
            ->salutation('Tým OnHost Support');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type'           => 'ticket_rating_request',
            'ticket_id'      => $this->ticket->id,
            'ticket_subject' => $this->ticket->subject,
        ];
    }
}
