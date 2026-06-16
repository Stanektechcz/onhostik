<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Shared\Support\MoneyFormatter;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceIssuedNotification extends Notification
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
            ->subject("Faktura {$invoice->number} — Onhost.cz")
            ->greeting('Dobrý den,')
            ->line("vystavili jsme vám fakturu číslo **{$invoice->number}**.")
            ->line('Celková částka k úhradě: **' . MoneyFormatter::format($invoice->total) . '**')
            ->line('Splatnost: **' . ($invoice->due_date?->format('d.m.Y') ?? '—') . '**')
            ->line('Variabilní symbol: ' . $invoice->variable_symbol)
            ->action('Zobrazit fakturu v klientském portálu', route('panel.billing.invoices.show', $invoice))
            ->line('Faktura není daňovým dokladem. Daňový doklad obdržíte po úhradě.')
            ->salutation('S pozdravem, tým Onhost.cz');
    }
}
