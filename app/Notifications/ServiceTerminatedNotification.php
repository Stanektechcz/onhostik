<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Provisioning\Models\Service;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ServiceTerminatedNotification extends Notification
{
    public function __construct(
        private readonly Service $service,
        private readonly string $reason = 'overdue_invoice',
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
            'icon'  => 'x-octagon',
            'color' => 'danger',
            'title' => "Služba ukončena — {$this->service->label}",
            'body'  => 'Vaše hostingová služba byla trvale ukončena. Kontaktujte podporu pro obnovení dat.',
            'url'   => route('panel.support.index'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reasonText = match ($this->reason) {
            'overdue_invoice' => 'dlouhodobě nezaplacené faktury po uplynutí ochranné lhůty',
            'manual'          => 'manuálního zásahu administrátora',
            default           => 'technického důvodu',
        };

        return (new MailMessage)
            ->subject("⛔ Vaše služba byla ukončena — {$this->service->label}")
            ->greeting('Dobrý den,')
            ->line("Vaše hostingová služba **{$this->service->label}** byla trvale ukončena z důvodu **{$reasonText}**.")
            ->when($this->reason === 'overdue_invoice', fn ($m) => $m
                ->line('Veškerá data spojená s touto službou byla smazána dle našich obchodních podmínek.')
                ->line('Pokud chcete zřídit novou službu, navštivte naši nabídku produktů.')
            )
            ->when($this->reason !== 'overdue_invoice', fn ($m) => $m
                ->line('Pro případnou obnovu nebo více informací kontaktujte naši podporu.')
            )
            ->action('Kontaktovat podporu', route('panel.support.index'))
            ->salutation('Tým Onhost.cz');
    }
}
