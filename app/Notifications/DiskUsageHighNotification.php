<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Provisioning\Models\Service;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DiskUsageHighNotification extends Notification
{
    public function __construct(
        private readonly Service $service,
        private readonly int $usedMb,
        private readonly int $limitMb,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $pct = $this->limitMb > 0 ? round($this->usedMb / $this->limitMb * 100, 1) : 0;

        return [
            'icon'  => 'server',
            'color' => 'warning',
            'title' => "Disk plný na {$pct}% — {$this->service->label}",
            'body'  => "Využito {$this->usedMb} MB z {$this->limitMb} MB. Zvažte upgrade tarifu.",
            'url'   => route('panel.services.show', $this->service),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pct = $this->limitMb > 0 ? round($this->usedMb / $this->limitMb * 100, 1) : 0;

        return (new MailMessage)
            ->subject("⚠ Disk plný na {$pct}% — {$this->service->label}")
            ->greeting('Upozornění na disk')
            ->line("Vaše hostingová služba **{$this->service->label}** využívá **{$pct}%** diskového prostoru.")
            ->line("Využito: **{$this->usedMb} MB** z **{$this->limitMb} MB**.")
            ->line('Doporučujeme smazat nepotřebné soubory nebo přejít na vyšší tarif.')
            ->action('Spravovat službu', route('panel.services.show', $this->service))
            ->salutation('S pozdravem, tým Onhost.cz');
    }
}
