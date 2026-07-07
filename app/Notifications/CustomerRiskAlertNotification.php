<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Customer\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerRiskAlertNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Customer $customer) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Upozornění: zákazník v riziku — ' . ($this->customer->company_name ?? ('zákazník #' . $this->customer->id)))
            ->line('Zákazník **' . ($this->customer->company_name ?? ('zákazník #' . $this->customer->id)) . '** má zdravotní skóre ' . $this->customer->health_score . '/100.')
            ->line('Segment: ' . ($this->customer->segment ?? 'neznámý'))
            ->action('Zobrazit zákazníka', url('/'))
            ->line('Toto upozornění bylo odesláno automaticky systémem OnHost.');
    }
}
