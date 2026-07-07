<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MagicLinkNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Přihlášení bez hesla — OnHost')
            ->line('Klikněte na odkaz níže pro přihlášení. Odkaz je platný 15 minut.')
            ->action('Přihlásit se', url('/magic-link/' . $this->token))
            ->line('Pokud jste o přihlášení nežádali, ignorujte tento e-mail.');
    }
}
