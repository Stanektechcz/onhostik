<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Provisioning\Models\Service;
use Illuminate\Notifications\Messages\MailMessage;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Notifications\Notification;

class ServiceSuspendedNotification extends Notification
{
    use RespectsNotificationPreferences;

    public function __construct(
        private readonly Service $service,
        private readonly string $reason = 'overdue_invoice',
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channelsFor($notifiable, 'service_critical', ['mail', 'database']);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'icon'  => 'alert-octagon',
            'color' => 'danger',
            'title' => "Služba pozastavena — {$this->service->label}",
            'body'  => 'Uhraďte fakturu pro okamžité obnovení.',
            'url'   => route('panel.billing.invoices'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reasonText = match ($this->reason) {
            'overdue_invoice' => 'nezaplacené faktury po uplynutí lhůty splatnosti',
            'manual'          => 'manuálního zásahu administrátora',
            default           => 'technického důvodu',
        };

        return (new MailMessage)
            ->subject("⚠️ Vaše služba byla pozastavena — {$this->service->label}")
            ->greeting('Dobrý den,')
            ->line("Vaše hosting služba **{$this->service->label}** byla pozastavena z důvodu **{$reasonText}**.")
            ->when($this->reason === 'overdue_invoice', fn ($m) => $m
                ->line('Aby byla služba okamžitě obnovena, prosíme o uhrazení dlužné faktury.')
                ->action('Zaplatit fakturu', route('panel.billing.invoices'))
            )
            ->when($this->reason !== 'overdue_invoice', fn ($m) => $m
                ->line('Pro obnovení služby kontaktujte naši podporu.')
                ->action('Kontaktovat podporu', route('panel.support.index'))
            )
            ->line('Po úhradě faktury bude služba automaticky obnovena do několika minut.')
            ->salutation('Tým Onhost.cz');
    }
}
