<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\KpiAlert;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KpiAlertTriggeredNotification extends Notification
{
    public function __construct(private readonly KpiAlert $alert) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $value     = number_format((float) $this->alert->last_value, 2, ',', ' ');
        $threshold = number_format($this->alert->threshold, 2, ',', ' ');

        return (new MailMessage)
            ->subject('⚠️ KPI upozornění: ' . $this->alert->metricLabel())
            ->greeting('Upozornění KPI systému')
            ->line("Metrika **{$this->alert->metricLabel()}** překročila nastavený práh.")
            ->line("Aktuální hodnota: **{$value}** ({$this->alert->operatorLabel()} {$threshold})")
            ->action('Přejít do admin panelu', route('admin.kpi-alerts.index'))
            ->salutation('OnHost Admin');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'icon'    => 'alert-triangle',
            'color'   => 'danger',
            'title'   => 'KPI: ' . $this->alert->metricLabel(),
            'message' => 'Hodnota ' . $this->alert->last_value . ' ' . $this->alert->operatorLabel() . ' ' . $this->alert->threshold,
        ];
    }
}
