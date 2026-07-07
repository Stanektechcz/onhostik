<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\MaintenanceWindow;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MaintenanceWindowNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly MaintenanceWindow $window)
    {
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Plánovaná údržba: ' . $this->window->title)
            ->greeting('Vážený zákazníku,')
            ->line('Informujeme vás o plánované odstávce systému.')
            ->line('**' . $this->window->title . '**')
            ->line($this->window->message)
            ->line('**Začátek:** ' . $this->window->starts_at->format('d.m.Y H:i'))
            ->line('**Konec:** ' . $this->window->ends_at->format('d.m.Y H:i'))
            ->line('Omlouváme se za případné nepohodlí.')
            ->salutation('Tým OnHost');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type'       => 'maintenance_window',
            'title'      => $this->window->title,
            'starts_at'  => $this->window->starts_at->toIso8601String(),
            'ends_at'    => $this->window->ends_at->toIso8601String(),
        ];
    }
}
