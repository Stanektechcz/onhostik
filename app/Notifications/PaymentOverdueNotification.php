<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Shared\Support\MoneyFormatter;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentOverdueNotification extends Notification
{
    public function __construct(
        private readonly Invoice $invoice,
        private readonly int     $daysOverdue,
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
            'icon'  => 'alert-triangle',
            'color' => 'danger',
            'title' => "Upomínka platby — {$this->invoice->number}",
            'body'  => "Faktura je {$this->daysOverdue} " . $this->dayWord() . " po splatnosti.",
            'url'   => route('panel.billing.invoices.show', $this->invoice),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = $this->invoice;

        return (new MailMessage)
            ->subject("⚠️ Upomínka platby — faktura {$invoice->number} ({$this->daysOverdue} {$this->dayWord()} po splatnosti)")
            ->greeting('Dobrý den,')
            ->line(
                "upozorňujeme Vás, že faktura **{$invoice->number}** je **{$this->daysOverdue} " .
                $this->dayWord() . " po splatnosti**."
            )
            ->line('Částka k úhradě: **' . MoneyFormatter::format($invoice->total) . '**')
            ->line('Původní splatnost: **' . ($invoice->due_date?->format('d.m.Y') ?? '—') . '**')
            ->line('Prosíme o neprodlené uhrazení faktury, aby nedošlo k pozastavení Vašich služeb.')
            ->action('Zaplatit fakturu', route('panel.billing.invoices.show', $invoice))
            ->line('Pokud jste platbu již odeslali, tuto upomínku ignorujte — platba bude spárována automaticky.')
            ->salutation('Děkujeme, tým Onhost.cz');
    }

    private function dayWord(): string
    {
        return match (true) {
            $this->daysOverdue === 1 => 'den',
            $this->daysOverdue <= 4 => 'dny',
            default                  => 'dní',
        };
    }
}
