<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeUserNotification extends Notification
{
    public function __construct(private readonly User $user) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'icon'  => 'user-check',
            'color' => 'success',
            'title' => 'Vítejte na Onhost.cz!',
            'body'  => 'Váš účet byl úspěšně vytvořen. Prozkoumejte dostupné služby.',
            'url'   => route('panel.dashboard'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Vítejte na Onhost.cz — váš účet je připraven')
            ->greeting("Dobrý den, {$this->user->name}!")
            ->line('Váš účet na **Onhost.cz** byl úspěšně vytvořen.')
            ->line('Nyní můžete objednat hosting, doménu nebo VPS server a mít web online během minut.')
            ->line('**Co vás čeká v klientské zóně:**')
            ->line('✅ Přehled vašich služeb a domén')
            ->line('✅ Správa faktur a plateb')
            ->line('✅ AI asistent pro technické otázky')
            ->line('✅ Zákaznická podpora 24/7')
            ->action('Přejít do klientské zóny', route('panel.dashboard'))
            ->line('Pokud jste tento účet nevytvářeli, kontaktujte nás neprodleně.')
            ->salutation('Vítejte na palubě, tým Onhost.cz');
    }
}
