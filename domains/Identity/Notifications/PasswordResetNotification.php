<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Onhost\Domain\Identity\Models\User;

/** Single-use reset link. The token only ever travels in this mail, never through the (redacted, durable) outbox. */
final class PasswordResetNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token, public readonly ?string $ip = null)
    {
        $this->onQueue('mails');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = rtrim((string) config('onhost.portal_url'), '/').'/obnova-hesla?token='.rawurlencode($this->token);
        $cs = ($notifiable->locale ?? 'cs') !== 'en';

        return (new MailMessage)
            ->subject($cs ? 'Obnova hesla k účtu ONhost' : 'Reset your ONhost password')
            ->greeting($cs ? "Dobrý den, {$notifiable->name}" : "Hello {$notifiable->name}")
            ->line($cs ? 'Někdo (pravděpodobně vy) požádal o obnovu hesla. Odkaz platí 2 hodiny a lze ho použít jen jednou.' : 'Someone (probably you) asked to reset the password. The link is valid for 2 hours and can be used once.')
            ->action($cs ? 'Nastavit nové heslo' : 'Set a new password', $url)
            ->line($cs ? 'Pokud jste o obnovu nežádali, tento e-mail ignorujte — heslo zůstává beze změny.' : 'If you did not request this, ignore this e-mail — the password stays unchanged.')
            ->line($this->ip ? ($cs ? "Žádost přišla z adresy {$this->ip}." : "The request came from {$this->ip}.") : '');
    }
}
