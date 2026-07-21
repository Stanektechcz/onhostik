<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Monitoring\Models\MonitorAlert;
use Illuminate\Notifications\Messages\MailMessage;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Notifications\Notification;

class MonitorThresholdAlertNotification extends Notification
{
    use RespectsNotificationPreferences;

    public function __construct(
        private readonly MonitorAlert $alert,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channelsFor($notifiable, 'monitor', ['mail', 'database']);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'icon'  => 'alert-triangle',
            'color' => 'warning',
            'title' => "Monitor alert — {$this->alert->monitor?->name}: {$this->alert->typeLabel()}",
            'body'  => "Aktuální hodnota: {$this->alert->current_value} | Práh: {$this->alert->threshold_value}",
            'url'   => route('admin.monitoring.index'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $monitor = $this->alert->monitor;
        $label   = $this->alert->typeLabel();

        return (new MailMessage)
            ->subject("⚠️ Monitor alert — {$monitor?->name}: {$label}")
            ->greeting('Dobrý den,')
            ->line("Byl detekován problém s monitorem **{$monitor?->name}** (`{$monitor?->target}`).")
            ->line("**Typ upozornění:** {$label}")
            ->line("**Aktuální hodnota:** {$this->alert->current_value}")
            ->line("**Nastavený práh:** {$this->alert->threshold_value}")
            ->action('Přejít na monitoring', route('admin.monitoring.index'))
            ->salutation('Tým Onhost.cz');
    }
}
