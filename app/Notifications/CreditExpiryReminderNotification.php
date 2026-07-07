<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Billing\Models\CreditTransaction;
use App\Domains\Shared\Support\MoneyFormatter;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CreditExpiryReminderNotification extends Notification
{
    public function __construct(
        private readonly CreditTransaction $transaction,
        private readonly int               $daysBefore,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail', 'database'];
        }
        $channels = [];
        if ($notifiable->wantsNotification('credit', 'mail')) {
            $channels[] = 'mail';
        }
        if ($notifiable->wantsNotification('credit', 'database')) {
            $channels[] = 'database';
        }
        return $channels === [] ? ['database'] : $channels;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $amount  = MoneyFormatter::format($this->transaction->amount);
        $expires = $this->transaction->expires_at?->format('d.m.Y') ?? '';

        return [
            'icon'  => 'clock',
            'color' => $this->daysBefore <= 7 ? 'danger' : 'warning',
            'title' => "Kredit vyprší za {$this->daysBefore} dní — {$amount}",
            'body'  => "Váš kredit ve výši {$amount} vyprší dne {$expires}.",
            'url'   => route('panel.billing.credit'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount  = MoneyFormatter::format($this->transaction->amount);
        $expires = $this->transaction->expires_at?->format('d.m.Y') ?? '';
        $urgent  = $this->daysBefore <= 7;

        return (new MailMessage)
            ->subject(($urgent ? '⚠️ ' : '') . "Kredit vyprší za {$this->daysBefore} dní — {$amount}")
            ->greeting('Dobrý den,')
            ->line(
                ($urgent ? '⚠️ **Pozor:** Váš ' : 'Váš ') .
                "kredit ve výši **{$amount}** vyprší dne **{$expires}**."
            )
            ->line('Nevyužitý kredit po uplynutí platnosti propadne.')
            ->line('Doporučujeme kredit spotřebovat nebo novou zásilku objednat před datem vypršení.')
            ->action('Zobrazit kredit', route('panel.billing.credit'))
            ->salutation('Tým Onhost.cz');
    }
}
