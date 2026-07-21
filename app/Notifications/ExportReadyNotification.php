<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ExportJob;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the requester their export finished (audit L120).
 *
 * Without this a queued export is a black hole — the admin clicks "export",
 * gets "we'll let you know", and has no idea when to come back.
 */
class ExportReadyNotification extends Notification
{
    use RespectsNotificationPreferences;

    public function __construct(private readonly ExportJob $export) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channelsFor($notifiable, 'account', ['mail', 'database']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Export je připraven ke stažení')
            ->greeting('Dobrý den,')
            ->line('Váš export je hotový a čeká ke stažení.')
            ->line('**Počet položek:** ' . ($this->export->row_count ?? 0))
            ->action('Stáhnout export', route('admin.invoice-batch.download', $this->export))
            ->line('Odkaz je platný do ' . $this->export->expires_at?->format('d.m.Y') . '.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type'  => 'export_ready',
            'icon'  => 'download',
            'color' => 'success',
            'title' => 'Export je připraven',
            'body'  => 'Export ' . ($this->export->row_count ?? 0) . ' položek je ke stažení.',
            'url'   => route('admin.invoice-batch.download', $this->export),
        ];
    }
}
