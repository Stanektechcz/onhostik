<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewIpLoginNotification extends Notification
{
    use RespectsNotificationPreferences;

    public function __construct(
        private readonly string $ip,
        private readonly string $userAgent,
    ) {}

    /**
     * Always in the bell; e-mailed only if the user asked for it (audit I125).
     *
     * It used to be in-app only, on the grounds that mailing every new-IP
     * login is noise. That was true of the old detection, which compared
     * against the single last_login_ip and so fired on every login for anyone
     * alternating between two addresses. TrackSecurityEvent now checks the
     * login history, so the signal is worth offering — as opt-in, since the
     * people who want it want it badly and the rest should not be trained to
     * ignore security mail.
     *
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return $this->channelsFor($notifiable, 'new_ip_login');
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
