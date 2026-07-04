<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SlaBreachNotification extends Notification
{
    public function __construct(private readonly int $count) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail', 'database'];
        }

        $channels = [];
        if ($notifiable->wantsNotification('support', 'mail')) {
            $channels[] = 'mail';
        }
        if ($notifiable->wantsNotification('support', 'database')) {
            $channels[] = 'database';
        }

        return $channels === [] ? ['database'] : $channels;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'icon'  => 'alert-triangle',
            'color' => 'danger',
            'title' => "SLA porušení — {$this->count} tiket" . ($this->count > 4 ? 'ů' : ($this->count > 1 ? 'y' : '')),
            'body'  => "Bylo detekováno {$this->count} tiket" . ($this->count > 4 ? 'ů' : ($this->count > 1 ? 'y' : '')) . ' s překročeným SLA termínem. Priority byly automaticky eskalovány.',
            'url'   => route('admin.support.sla-monitor'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $suffix = $this->count > 4 ? 'ů' : ($this->count > 1 ? 'y' : '');

        return (new MailMessage)
            ->subject("[OnHost] SLA porušení — {$this->count} tiket{$suffix}")
            ->greeting('Dobrý den,')
            ->line("Bylo detekováno **{$this->count}** tiket{$suffix} s překročeným SLA termínem.")
            ->line('Priority těchto tiketů byly automaticky eskalovány na vyšší úroveň.')
            ->action('Zobrazit SLA monitor', route('admin.support.sla-monitor'))
            ->salutation('Onhost systém');
    }
}
