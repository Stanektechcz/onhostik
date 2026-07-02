<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Provisioning\Models\DomainRegistration;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DomainExpiringNotification extends Notification
{
    public function __construct(
        private readonly DomainRegistration $domain,
        private readonly int $daysLeft,
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
            'icon'  => $this->daysLeft <= 7 ? 'alert-triangle' : 'clock',
            'color' => $this->daysLeft <= 7 ? 'danger' : 'warning',
            'title' => "Doména expiruje za {$this->daysLeft}d — {$this->domain->fqdn()}",
            'body'  => $this->domain->auto_renew
                ? 'Doména bude automaticky obnovena.'
                : 'Ruční obnova nutná — doména není nastavena na auto-obnovu.',
            'url'   => route('panel.domains.index'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $urgent = $this->daysLeft <= 7;
        $fqdn   = $this->domain->fqdn();

        return (new MailMessage)
            ->subject(($urgent ? '🔴 ' : '⚠️ ') . "Doména {$fqdn} expiruje za {$this->daysLeft} dní")
            ->greeting('Dobrý den,')
            ->line(($urgent ? '🔴 ' : '') . "Vaše doména **{$fqdn}** expiruje za **{$this->daysLeft} " .
                ($this->daysLeft === 1 ? 'den' : ($this->daysLeft <= 4 ? 'dny' : 'dní')) . "** " .
                "({$this->domain->expires_at?->format('d.m.Y')}).")
            ->when($this->domain->auto_renew, fn ($m) => $m
                ->line('✅ **Auto-obnova je aktivní** — doména bude automaticky obnovena.')
            )
            ->when(!$this->domain->auto_renew, fn ($m) => $m
                ->line('⚠️ **Auto-obnova není aktivní.** Bez ruční obnovy doména expiruje a může být zabrána.')
                ->action('Spravovat domény', route('panel.domains.index'))
            )
            ->when($urgent && !$this->domain->auto_renew, fn ($m) => $m
                ->line('🔴 **Urgentní:** Pokud doménu neobnovíte do ' . $this->domain->expires_at?->format('d.m.Y') . ', přijdete o ni.')
            )
            ->salutation('Tým Onhost.cz');
    }
}
