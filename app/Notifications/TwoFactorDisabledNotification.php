<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorDisabledNotification extends Notification
{
    use Queueable;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Bezpečnostní upozornění: 2FA bylo deaktivováno')
            ->greeting('Bezpečnostní upozornění!')
            ->line('Dvoufázové ověřování (2FA) bylo deaktivováno na vašem účtu.')
            ->line('Váš účet je nyní chráněn pouze heslem. Doporučujeme 2FA znovu aktivovat.')
            ->line('Pokud jste tuto změnu neprovedli vy, ihned kontaktujte naši podporu a změňte heslo.')
            ->action('Přihlásit se', url('/login'))
            ->salutation('Tým OnHost');
    }
}
