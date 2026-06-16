<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Shared\Support\MoneyFormatter;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoicePaidNotification extends Notification
{
    public function __construct(private readonly Invoice $invoice) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = $this->invoice;

        return (new MailMessage)
            ->subject("Platba přijata — faktura {$invoice->number}")
            ->greeting('Dobrý den,')
            ->line("Potvrzujeme přijetí platby za fakturu **{$invoice->number}**.")
            ->line('Zaplacená částka: **' . MoneyFormatter::format($invoice->total) . '**')
            ->line('Vaše služby budou aktivovány automaticky.')
            ->action('Zobrazit fakturu', route('panel.billing.invoices.show', $invoice))
            ->salutation('Děkujeme za platbu, tým Onhost.cz');
    }
}
