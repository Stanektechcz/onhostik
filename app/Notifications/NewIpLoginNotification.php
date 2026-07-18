<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewIpLoginNotification extends Notification
{
    public function __construct(
        private readonly string $ip,
        private readonly string $userAgent,
    ) {}

    /**
     * In-app only. A login from a new IP is surfaced in the notification
     * bell and the security log — we deliberately do NOT e-mail it, since a
     * mail on every new-IP login is noise (and fires spuriously in local /
     * proxied setups where the remote IP flips between requests).
     *
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Přihlášení z nové IP adresy — OnHost')
            ->greeting('Ahoj!')
            ->line('Zaznamenali jsme přihlášení do vašeho účtu z nové IP adresy.')
            ->line('**IP adresa:** ' . $this->ip)
            ->line('**Prohlížeč / zařízení:** ' . mb_substr($this->userAgent, 0, 120))
            ->line('Pokud jste to nebyli vy, okamžitě změňte heslo a aktivujte 2FA.')
            ->action('Přejít na nastavení zabezpečení', url('/panel/ucet/zabezpeceni'))
            ->line('Pokud jste se přihlásili vy, tuto zprávu ignorujte.');
    }

    /** @return array<string, mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'type'       => 'new_ip_login',
            'ip_address' => $this->ip,
        ];
    }
}
