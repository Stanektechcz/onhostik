<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RenewalFailureAdminSummaryNotification extends Notification
{
    use Queueable;

    /** @param int $failureCount */
    public function __construct(
        public readonly int $failureCount,
    ) {
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject("Upozornění: {$this->failureCount} neúspěšných obnovení")
            ->greeting('Dobrý den,')
            ->line("Dnes bylo detekováno **{$this->failureCount} neúspěšných obnovení** předplatného.")
            ->line('Zákazníci byli informováni. Doporučujeme zkontrolovat přehled zákazníků.')
            ->action('Přehled zákazníků', route('admin.customers.index'))
            ->salutation('Systém OnHost');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type'          => 'renewal_failure_admin_summary',
            'failure_count' => $this->failureCount,
        ];
    }
}
