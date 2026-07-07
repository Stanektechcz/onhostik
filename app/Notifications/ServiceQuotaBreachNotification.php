<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Provisioning\Models\Service;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to admins when one or more services exceed their resource quota threshold.
 *
 * @phpstan-type AlertEntry array{usage: int, limit: int, percent_used: float}
 */
class ServiceQuotaBreachNotification extends Notification
{
    use Queueable;

    /**
     * @param array<string, AlertEntry> $alerts  Resource name → alert data from ServiceResourceService::checkAlerts()
     */
    public function __construct(
        public readonly Service $service,
        public readonly array $alerts,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lines = [];
        foreach ($this->alerts as $resource => $data) {
            $lines[] = "**{$resource}**: {$data['percent_used']} % ({$data['usage']} / {$data['limit']})";
        }

        return (new MailMessage())
            ->subject("[Alert] Překročení kvóty – {$this->service->label}")
            ->greeting('Upozornění administrátora:')
            ->line("Služba **{$this->service->label}** překročila hranici využití zdrojů.")
            ->lines($lines)
            ->action('Zobrazit přehled zdrojů', route('admin.service-resources.index'))
            ->salutation('Monitoring OnHost');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type'       => 'service_quota_breach',
            'service_id' => $this->service->id,
            'label'      => $this->service->label,
            'alerts'     => $this->alerts,
        ];
    }
}
