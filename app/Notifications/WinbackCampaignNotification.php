<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\WinbackCampaign;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WinbackCampaignNotification extends Notification
{
    public function __construct(private readonly WinbackCampaign $campaign) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Chybíte nám — zvláštní nabídka od OnHost')
            ->greeting('Vážený zákazníku,')
            ->line($this->campaign->message)
            ->action('Vrátit se do OnHost', url('/'))
            ->line('Těšíme se na vaši návštěvu.');
    }
}
