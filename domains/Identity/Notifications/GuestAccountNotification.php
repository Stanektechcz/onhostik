<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Onhost\Domain\Identity\Models\User;

/** Account created during a guest checkout: the order number and a single-use "set your password" link (48 hours). */
final class GuestAccountNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token, public readonly string $orderNumber, public readonly ?string $organizationName = null)
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
            ->subject($cs ? "Objednávka {$this->orderNumber} přijata — nastavte si heslo k účtu" : "Order {$this->orderNumber} received — set your account password")
            ->greeting($cs ? "Dobrý den, {$notifiable->name}" : "Hello {$notifiable->name}")
            ->line($cs ? "Objednávku {$this->orderNumber} jsme přijali. Založili jsme vám účet".($this->organizationName ? " pro {$this->organizationName}" : '')." s přihlašovacím e-mailem {$notifiable->email}; průběh objednávky, doklady a nastavení služeb najdete v klientském panelu." : "We received order {$this->orderNumber} and created an account".($this->organizationName ? " for {$this->organizationName}" : '')." with the sign-in e-mail {$notifiable->email}; the order progress, documents and service settings are in the client panel.")
            ->action($cs ? 'Nastavit heslo' : 'Set a password', $url)
            ->line($cs ? 'Odkaz platí 48 hodin. Pokud jste objednávku nezadali vy, napište nám a účet zrušíme.' : 'The link is valid for 48 hours. If you did not place this order, tell us and we will remove the account.');
    }
}
