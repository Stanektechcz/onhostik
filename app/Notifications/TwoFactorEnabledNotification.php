<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Notifications\Notification;

class TwoFactorEnabledNotification extends Notification
{
    use RespectsNotificationPreferences;

    use Queueable;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channelsFor($notifiable, 'security', ['mail']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Dvoufázové ověřování bylo aktivováno')
            ->greeting('Dobrý den!')
            ->line('Na vašem účtu bylo právě aktivováno dvoufázové ověřování (2FA).')
            ->line('Od nyní budete při každém přihlášení požádáni o kód z vaší autentifikační aplikace.')
            ->line('Pokud jste tuto změnu neprovedli vy, ihned kontaktujte naši podporu.')
            ->action('Nastavení bezpečnosti', route('panel.account.security'))
            ->salutation('Tým OnHost');
    }
}
