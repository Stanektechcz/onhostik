<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Shared\Support\MoneyFormatter;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceDueSoonNotification extends Notification
{
    public function __construct(
        private readonly Invoice $invoice,
        private readonly int     $daysUntilDue,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail', 'database'];
        }
        $channels = [];
        if ($notifiable->wantsNotification('payment', 'mail')) {
            $channels[] = 'mail';
        }
        if ($notifiable->wantsNotification('payment', 'database')) {
            $channels[] = 'database';
        }
        return $channels === [] ? ['database'] : $channels;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'icon'  => 'clock',
            'color' => 'warning',
            'title' => "Faktura splatná za {$this->daysUntilDue} den — " . MoneyFormatter::format($this->invoice->total),
            'body'  => "Faktura {$this->invoice->number} je splatná dne {$this->invoice->due_date?->format('d.m.Y')}.",
            'url'   => route('panel.billing.invoices.show', $this->invoice),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount  = MoneyFormatter::format($this->invoice->total);
        $dueDate = $this->invoice->due_date?->format('d.m.Y') ?? '';

        return (new MailMessage)
            ->subject("⏰ Faktura splatná zítra — {$amount}")
            ->greeting('Dobrý den,')
            ->line("Připomínáme, že faktura **{$this->invoice->number}** je splatná **zítra ({$dueDate})**.")
            ->line("**Celková částka:** {$amount}")
            ->line('Pro bezproblémové pokračování služeb prosím uhraďte fakturu včas.')
            ->action('Zaplatit fakturu', route('panel.billing.invoices.show', $this->invoice))
            ->line('Máte dostatečný kredit? Fakturu lze uhradit okamžitě jedním kliknutím.')
            ->salutation('Tým Onhost.cz');
    }
}
