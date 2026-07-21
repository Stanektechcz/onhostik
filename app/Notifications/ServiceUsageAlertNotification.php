<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Provisioning\Models\Service;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Notifications\Notification;

class ServiceUsageAlertNotification extends Notification
{
    use RespectsNotificationPreferences;

    use Queueable;

    public function __construct(public readonly Service $service, public readonly int $usagePct) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channelsFor($notifiable, 'monitor', ['mail']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Upozornění: vysoké využití služby ' . $this->service->label)
            ->line('Vaše služba **' . $this->service->label . '** dosáhla ' . $this->usagePct . '% využití kapacity.')
            ->line('Zvažte navýšení kapacity nebo optimalizaci využití.')
            ->action('Zobrazit službu', url('/panel'))
            ->line('Toto upozornění bylo odesláno systémem OnHost.');
    }
}
