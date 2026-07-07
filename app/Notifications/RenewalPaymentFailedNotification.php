<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RenewalPaymentFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly Service $service,
    ) {
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $channels = [];
        if ($notifiable->wantsNotification('renewal', 'mail')) {
            $channels[] = 'mail';
        }
        if ($notifiable->wantsNotification('renewal', 'database')) {
            $channels[] = 'database';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Obnova služby se nezdařila — ' . $this->service->label)
            ->greeting('Vážený zákazníku,')
            ->line("Nepodařilo se nám obnovit vaši službu **{$this->service->label}**.")
            ->line("Faktura **{$this->invoice->number}** je po datu splatnosti a služba může být brzy pozastavena.")
            ->line('Uhraďte prosím fakturu co nejdříve, aby nedošlo k přerušení služby.')
            ->action('Zaplatit fakturu', route('panel.billing.invoices.show', $this->invoice))
            ->salutation('Tým OnHost');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type'           => 'renewal_payment_failed',
            'invoice_id'     => $this->invoice->id,
            'invoice_number' => $this->invoice->number,
            'service_id'     => $this->service->id,
            'service_name'   => $this->service->label,
        ];
    }
}
