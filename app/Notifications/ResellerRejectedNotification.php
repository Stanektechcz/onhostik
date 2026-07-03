<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Reseller\Models\ResellerProfile;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResellerRejectedNotification extends Notification
{
    public function __construct(private readonly ResellerProfile $reseller) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'icon'  => 'x-circle',
            'color' => 'danger',
            'title' => 'Žádost o reseller program nebyla schválena',
            'body'  => "Žádost firmy {$this->reseller->business_name} byla zamítnuta.",
            'url'   => route('panel.reseller-program'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Žádost o reseller program — OnHost')
            ->greeting('Dobrý den,')
            ->line("Vaše žádost o reseller program pro firmu **{$this->reseller->business_name}** bohužel nebyla schválena.")
            ->line('Důvodem může být nedostatek informací nebo nesplnění podmínek reseller programu.')
            ->line('Kontaktujte nás prostřednictvím zákaznické podpory, pokud chcete probrat možnosti nebo podat novou žádost.')
            ->action('Zákaznická podpora', route('panel.support.index'))
            ->salutation('S pozdravem, tým OnHost');
    }
}
