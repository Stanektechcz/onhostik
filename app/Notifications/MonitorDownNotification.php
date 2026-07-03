<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Monitoring\Models\Monitor;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MonitorDownNotification extends Notification
{
    public function __construct(
        private readonly Monitor $monitor,
        private readonly string $reason = '',
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail', 'database'];
        }
        $channels = [];
        if ($notifiable->wantsNotification('monitor', 'mail')) {
            $channels[] = 'mail';
        }
        if ($notifiable->wantsNotification('monitor', 'database')) {
            $channels[] = 'database';
        }
        return $channels === [] ? ['database'] : $channels;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'icon'  => 'wifi-off',
            'color' => 'danger',
            'title' => "Výpadek — {$this->monitor->name}",
            'body'  => $this->reason ?: 'Monitor nereaguje.',
            'url'   => route('panel.services.show', $this->monitor->service_id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("🔴 Výpadek detekován — {$this->monitor->name}")
            ->greeting('Dobrý den,')
            ->line("Byl detekován výpadek služby **{$this->monitor->name}** (`{$this->monitor->target}`).")
            ->when($this->reason !== '', fn ($m) => $m->line("**Důvod:** {$this->reason}"))
            ->line('Náš tým byl upozorněn a incident je sledován.')
            ->action('Správa služby', route('panel.services.show', $this->monitor->service_id))
            ->line('O obnovení provozu vás budeme informovat.')
            ->salutation('Tým Onhost.cz');
    }
}
