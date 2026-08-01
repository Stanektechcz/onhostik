<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Customer\Models\CustomerInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails a sub-account invitation link. Transactional — always sent (it is the
 * only way the invitee learns of the invite), so it does not consult
 * notification preferences. The raw token is passed in and never stored.
 */
final class CustomerInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly CustomerInvitation $invitation,
        private readonly string $rawToken,
        private readonly string $accountName,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('invitation.accept', ['token' => $this->rawToken]);

        return (new MailMessage)
            ->subject('Pozvánka do účtu ' . $this->accountName . ' na OnHost')
            ->greeting('Dobrý den,')
            ->line('Byli jste pozváni ke správě účtu **' . $this->accountName . '** na OnHost.')
            ->action('Přijmout pozvánku', $url)
            ->line('Odkaz je platný do ' . $this->invitation->expires_at->format('d.m.Y H:i') . '.')
            ->line('Pokud jste tuto pozvánku nečekali, tento e-mail ignorujte.');
    }
}
