<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Provisioning\Models\ProvisioningTask;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ProvisioningFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ProvisioningTask $task,
        private readonly Service $service,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject("[OnHost] Provisioning selhalo — {$this->service->label}")
            ->error()
            ->greeting('Chyba provisooningu')
            ->line("Služba **{$this->service->label}** nedokázala být zprovozněna ani po {$this->task->max_attempts} pokusech.")
            ->line("Operace: `{$this->task->operation}`")
            ->line("Poslední chyba: " . ($this->task->error_message ?? 'neznámá'))
            ->action('Zobrazit task', url(route('admin.provisioning.show', $this->task)))
            ->line('Zkontrolujte konfiguraci serveru nebo kontaktujte tým OnHost.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'task_id'     => $this->task->id,
            'service_id'  => $this->service->id,
            'service'     => $this->service->label,
            'operation'   => $this->task->operation,
            'error'       => $this->task->error_message,
            'attempts'    => $this->task->attempts,
        ];
    }
}
