<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Shared\Support\MoneyFormatter;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RenewalReminderNotification extends Notification
{
    public function __construct(
        private readonly Service $service,
        private readonly Invoice $invoice,
        private readonly int     $daysLeft,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail', 'database'];
        }
        $channels = [];
        if ($notifiable->wantsNotification('renewal', 'mail')) {
            $channels[] = 'mail';
        }
        if ($notifiable->wantsNotification('renewal', 'database')) {
            $channels[] = 'database';
        }
        return $channels === [] ? ['database'] : $channels;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'icon'  => $this->daysLeft <= 3 ? 'alert-triangle' : 'clock',
            'color' => $this->daysLeft <= 3 ? 'danger' : 'warning',
            'title' => "Obnova za {$this->daysLeft}d — {$this->service->label}",
            'body'  => 'Faktura ' . $this->invoice->number . ' čeká na uhrazení.',
            'url'   => route('panel.billing.invoices.show', $this->invoice),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $urgent = $this->daysLeft <= 3;

        return (new MailMessage)
            ->subject(($urgent ? '⚠️ ' : '') . "Obnova služby za {$this->daysLeft} dní — {$this->service->label}")
            ->greeting('Dobrý den,')
            ->when($urgent, fn ($m) => $m->line(
                "⚠️ **Pozor:** Vaše služba **{$this->service->label}** vyprší za {$this->daysLeft} " .
                ($this->daysLeft === 1 ? 'den' : 'dny') . "."
            ))
            ->when(!$urgent, fn ($m) => $m->line(
                "Vaše služba **{$this->service->label}** vyprší za **{$this->daysLeft} dní** " .
                "({$this->service->next_due_date?->format('d.m.Y')})."
            ))
            ->line('Pro zajištění nepřetržitého provozu prosím uhraďte obnovovací fakturu.')
            ->line("**Faktura:** {$this->invoice->number}")
            ->line("**Částka:** " . MoneyFormatter::format($this->invoice->total))
            ->line("**Splatnost:** {$this->invoice->due_date?->format('d.m.Y')}")
            ->action('Zaplatit fakturu', route('panel.billing.invoices.show', $this->invoice))
            ->when($urgent, fn ($m) => $m->line(
                '🔴 **Pokud faktura nebude uhrazena včas, služba bude pozastavena.** ' .
                'Po úhradě bude automaticky obnovena.'
            ))
            ->line('Máte dostatečný kredit? Fakturu lze uhradit okamžitě jedním kliknutím.')
            ->salutation('Tým Onhost.cz');
    }
}
