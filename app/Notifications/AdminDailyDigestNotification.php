<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class AdminDailyDigestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $overdueInvoices,
        public readonly int $openTickets,
        public readonly int $servicesDueIn7Days,
        public readonly Carbon $date,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Denní přehled OnHost — ' . $this->date->format('d.m.Y'))
            ->greeting('Dobrý den,')
            ->line('Zde je váš denní přehled pro ' . $this->date->format('d.m.Y') . ':')
            ->line('**Faktury po splatnosti:** ' . $this->overdueInvoices)
            ->line('**Otevřené tickety:** ' . $this->openTickets)
            ->line('**Služby k obnově do 7 dní:** ' . $this->servicesDueIn7Days)
            ->salutation('OnHost Admin');
    }
}
