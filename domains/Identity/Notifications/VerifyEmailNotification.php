<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Onhost\Domain\Identity\Models\User;

/** Welcome + e-mail verification link (3 days, single use). */
final class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token, public readonly ?string $organizationName = null)
    {
        $this->onQueue('mails');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = rtrim((string) config('onhost.portal_url'), '/').'/overeni-emailu?token='.rawurlencode($this->token);
        $cs = ($notifiable->locale ?? 'cs') !== 'en';

        return (new MailMessage)
            ->subject($cs ? 'Vítejte v ONhost — potvrďte svůj e-mail' : 'Welcome to ONhost — confirm your e-mail')
            ->greeting($cs ? "Dobrý den, {$notifiable->name}" : "Hello {$notifiable->name}")
            ->line($cs ? 'Účet'.($this->organizationName ? " pro {$this->organizationName}" : '').' je založený. Potvrďte prosím svou adresu, ať vám můžeme posílat doklady a bezpečnostní upozornění.' : 'Your account'.($this->organizationName ? " for {$this->organizationName}" : '').' is ready. Please confirm your address so we can send invoices and security notices.')
            ->action($cs ? 'Potvrdit e-mail' : 'Confirm e-mail', $url)
            ->line($cs ? 'Odkaz platí 3 dny.' : 'The link is valid for 3 days.');
    }
}
