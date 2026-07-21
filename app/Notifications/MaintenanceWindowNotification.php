<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Shared\Support\IcsCalendar;
use App\Models\MaintenanceWindow;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Notifications\Notification;

class MaintenanceWindowNotification extends Notification
{
    use RespectsNotificationPreferences;

    use Queueable;

    public function __construct(public readonly MaintenanceWindow $window)
    {
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channelsFor($notifiable, 'maintenance', ['mail', 'database']);
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
            ->line('V příloze najdete kalendářovou pozvánku, kterou si můžete přidat do svého kalendáře.')
            ->line('Omlouváme se za případné nepohodlí.')
            // A date buried in an e-mail gets forgotten; a calendar entry does
            // not (audit I129).
            ->attachData($this->calendarInvite(), 'udrzba.ics', ['mime' => 'text/calendar; charset=UTF-8'])
            ->salutation('Tým OnHost');
    }

    private function calendarInvite(): string
    {
        return IcsCalendar::event(
            // Stable per window, so re-sending updates the existing entry
            // instead of littering the calendar with duplicates.
            uid: 'maintenance-' . $this->window->id . '@onhost.cz',
            summary: 'OnHost — plánovaná údržba: ' . $this->window->title,
            description: (string) ($this->window->message ?? $this->window->description ?? ''),
            start: $this->window->starts_at,
            end: $this->window->ends_at,
            url: url('/panel'),
        );
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
