<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\InvoicePdfService;
use App\Domains\Shared\Support\MoneyFormatter;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceIssuedNotification extends Notification
{
    public function __construct(private readonly Invoice $invoice) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail', 'database'];
        }
        $channels = [];
        if ($notifiable->wantsNotification('invoice', 'mail')) {
            $channels[] = 'mail';
        }
        if ($notifiable->wantsNotification('invoice', 'database')) {
            $channels[] = 'database';
        }
        return $channels === [] ? ['database'] : $channels;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'icon'  => 'file-text',
            'color' => 'primary',
            'title' => "Faktura {$this->invoice->number}",
            'body'  => 'Nová faktura k uhrazení: ' . MoneyFormatter::format($this->invoice->total),
            'url'   => route('panel.billing.invoices.show', $this->invoice),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice  = $this->invoice;
        $pdfSvc   = app(InvoicePdfService::class);

        $mail = (new MailMessage)
            ->subject("Faktura {$invoice->number} — Onhost.cz")
            ->greeting('Dobrý den,')
            ->line("vystavili jsme vám fakturu číslo **{$invoice->number}**.")
            ->line('Celková částka k úhradě: **' . MoneyFormatter::format($invoice->total) . '**')
            ->line('Splatnost: **' . ($invoice->due_date?->format('d.m.Y') ?? '—') . '**')
            ->line('Variabilní symbol: ' . $invoice->variable_symbol)
            ->action('Zobrazit fakturu v klientském portálu', route('panel.billing.invoices.show', $invoice))
            ->line('Faktura není daňovým dokladem. Daňový doklad obdržíte po úhradě.')
            ->salutation('S pozdravem, tým Onhost.cz');

        try {
            $pdfContent = $pdfSvc->generate($invoice);
            $filename   = $pdfSvc->filename($invoice);
            $mail->attachData($pdfContent, $filename, ['mime' => 'application/pdf']);
        } catch (\Throwable) {
            // PDF generation failure must not block the email from sending
        }

        return $mail;
    }
}
