<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Communication\Models\SystemAnnouncement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SystemAnnouncementNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly SystemAnnouncement $announcement) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if ($this->announcement->send_email) {
            $channels[] = 'mail';
        }
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject($this->announcement->title)
            ->greeting('Dobrý den,')
            ->line($this->announcement->body)
            ->line('S pozdravem, tým OnHost');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title'           => $this->announcement->title,
            'body'            => $this->announcement->body,
            'icon'            => $this->announcement->icon,
            'color'           => match ($this->announcement->type) {
                'warning'     => 'warning',
                'maintenance' => 'danger',
                'feature'     => 'success',
                default       => 'info',
            },
            'url'             => '#',
            'type'            => 'announcement',
            'announcement_id' => $this->announcement->id,
        ];
    }
}
