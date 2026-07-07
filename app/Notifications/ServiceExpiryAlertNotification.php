<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Provisioning\Models\Service;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ServiceExpiryAlertNotification extends Notification
{
    public function __construct(
        private readonly Service $service,
        private readonly int     $daysLeft,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail'];
        }
        return $notifiable->wantsNotification('renewal', 'mail') ? ['mail'] : ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'icon'  => 'clock',
            'color' => 'warning',
            'title' => "Blíží se konec platnosti: {$this->service->label}",
            'body'  => "Vaše služba vyprší za {$this->daysLeft} dní.",
            'url'   => route('panel.services.show', $this->service),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Blíží se konec platnosti: {$this->service->label}")
            ->greeting('Vážený zákazníku,')
            ->line("Vaše služba **{$this->service->label}** vyprší za **{$this->daysLeft} dní**.")
            ->action('Zobrazit službu', route('panel.services.show', $this->service))
            ->line('V případě otázek nás prosím kontaktujte.');
    }
}
