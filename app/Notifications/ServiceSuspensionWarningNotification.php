<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ServiceSuspensionWarningNotification extends Notification
{
    public function __construct(
        private readonly Service $service,
        private readonly Invoice $invoice,
        private readonly int $daysUntilSuspension,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'icon'       => 'alert-triangle',
            'color'      => 'warning',
            'title'      => "Upozornění: Služba bude pozastavena — {$this->service->label}",
            'body'       => "Neuhrazená faktura {$this->invoice->number}. Pozastavení za {$this->daysUntilSuspension} dní.",
            'url'        => route('panel.billing.invoices'),
            'invoice_id' => $this->invoice->id,
            'service_id' => $this->service->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dayWord = $this->daysUntilSuspension === 1 ? 'den' : ($this->daysUntilSuspension < 5 ? 'dny' : 'dní');

        return (new MailMessage)
            ->subject("⚠️ Vaše služba bude pozastavena za {$this->daysUntilSuspension} {$dayWord} — {$this->service->label}")
            ->greeting('Dobrý den,')
            ->line("Vaše hosting služba **{$this->service->label}** bude **pozastavena za {$this->daysUntilSuspension} {$dayWord}** z důvodu nezaplacené faktury.")
            ->line("Faktura č. **{$this->invoice->number}** je po splatnosti. Pro zabránění přerušení služby prosíme o okamžitou úhradu.")
            ->action('Zaplatit fakturu', route('panel.billing.invoices'))
            ->line('Po úhradě faktury bude služba automaticky obnovena do několika minut.')
            ->line('Pokud jste fakturu již uhradili, prosíme ignorujte tuto zprávu.')
            ->salutation('Tým Onhost.cz');
    }
}
