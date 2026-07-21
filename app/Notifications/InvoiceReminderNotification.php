<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Billing\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Notifications\Notification;

class InvoiceReminderNotification extends Notification
{
    use RespectsNotificationPreferences;

    use Queueable;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly int $reminderNumber,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channelsFor($notifiable, 'payment', ['mail']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = match (true) {
            $this->reminderNumber === 1 => 'Upomínka č. 1 — nezaplacená faktura',
            $this->reminderNumber === 2 => 'Upomínka č. 2 — faktura po splatnosti',
            default                     => 'Poslední upomínka — hrozí pozastavení služby',
        };

        return (new MailMessage())
            ->subject($subject)
            ->line('Evidujeme nezaplacenou fakturu č. ' . $this->invoice->number . '.')
            ->line('Splatnost: ' . $this->invoice->due_date?->format('d.m.Y'))
            ->action('Zobrazit fakturu', url('/'))
            ->line('Upomínka č. ' . $this->reminderNumber);
    }
}
