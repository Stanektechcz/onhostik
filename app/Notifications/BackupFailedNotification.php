<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Backups\Models\BackupJob;
use Illuminate\Notifications\Messages\MailMessage;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Notifications\Notification;

class BackupFailedNotification extends Notification
{
    use RespectsNotificationPreferences;

    public function __construct(private readonly BackupJob $job) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channelsFor($notifiable, 'backup', ['mail', 'database']);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'icon'       => 'alert-triangle',
            'color'      => 'danger',
            'title'      => 'Záloha selhala',
            'body'       => "Záloha (job #{$this->job->id}) nebyla dokončena: {$this->job->error_message}",
            'backup_job' => $this->job->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $label = $this->job->service->label ?? "Service #{$this->job->service_id}";

        return (new MailMessage)
            ->subject("Záloha selhala — {$label}")
            ->greeting('Dobrý den,')
            ->line("Záloha pro službu **{$label}** (job #{$this->job->id}) se nezdařila.")
            ->when($this->job->error_message, fn ($m) => $m
                ->line("**Chyba:** {$this->job->error_message}")
            )
            ->line('Zkontrolujte nastavení zálohovacího poskytovatele a zkuste znovu.')
            ->salutation('Tým OnHost');
    }
}
