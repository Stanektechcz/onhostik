<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Reseller\Models\ResellerProfile;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResellerSuspendedNotification extends Notification
{
    public function __construct(private readonly ResellerProfile $reseller) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'icon'  => 'pause-circle',
            'color' => 'warning',
            'title' => 'Váš reseller účet byl pozastaven',
            'body'  => "Reseller přístup pro firmu {$this->reseller->business_name} byl pozastaven.",
            'url'   => route('panel.support.index'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Váš reseller účet byl pozastaven — OnHost')
            ->greeting('Dobrý den,')
            ->line("Váš reseller účet pro firmu **{$this->reseller->business_name}** byl dočasně pozastaven.")
            ->line('Přístup do reseller portálu je deaktivován. Kontaktujte náš tým pro více informací nebo pro obnovení přístupu.')
            ->action('Zákaznická podpora', route('panel.support.index'))
            ->salutation('S pozdravem, tým OnHost');
    }
}
