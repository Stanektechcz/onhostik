<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Billing\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LateFeeAppliedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly int $feeMinor,
    ) {
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $channels = [];
        if ($notifiable->wantsNotification('invoice', 'mail')) {
            $channels[] = 'mail';
        }
        if ($notifiable->wantsNotification('invoice', 'database')) {
            $channels[] = 'database';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $feeCzk = number_format($this->feeMinor / 100, 0, ',', ' ');

        return (new MailMessage())
            ->subject('Upomínkový poplatek k faktuře ' . $this->invoice->number)
            ->greeting('Vážený zákazníku,')
            ->line("K faktuře **{$this->invoice->number}** byl přidán upomínkový poplatek ve výši **{$feeCzk} Kč**.")
            ->line('Faktura je po datu splatnosti. Poplatek bude zahrnut do celkové dlužné částky.')
            ->action('Zobrazit fakturu', route('panel.billing.invoices.show', $this->invoice))
            ->salutation('Tým OnHost');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type'         => 'late_fee_applied',
            'invoice_id'   => $this->invoice->id,
            'invoice_number' => $this->invoice->number,
            'fee_minor'    => $this->feeMinor,
        ];
    }
}
