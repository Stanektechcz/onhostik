<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class AdminWeeklyReportNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $newCustomers,
        public readonly int $revenueMinor,
        public readonly int $newTickets,
        public readonly int $newOrders,
        public readonly int $activeServices,
        public readonly Carbon $weekStart,
        public readonly Carbon $weekEnd,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $revenue = number_format($this->revenueMinor / 100, 2, ',', ' ') . ' Kč';
        $from    = $this->weekStart->format('d.m.Y');
        $to      = $this->weekEnd->format('d.m.Y');

        return (new MailMessage())
            ->subject("Týdenní přehled OnHost ({$from} – {$to})")
            ->greeting('Dobrý den,')
            ->line("přinášíme vám týdenní přehled za období **{$from} – {$to}**.")
            ->line("**Nových zákazníků:** {$this->newCustomers}")
            ->line("**Tržby (uhrazené platby):** {$revenue}")
            ->line("**Nových tiketů:** {$this->newTickets}")
            ->line("**Nových objednávek:** {$this->newOrders}")
            ->line("**Aktivních služeb celkem:** {$this->activeServices}")
            ->action('Přejít do administrace', url('/panel'))
            ->salutation('Tým OnHost');
    }
}
