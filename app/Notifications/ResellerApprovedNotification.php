<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Reseller\Models\ResellerProfile;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResellerApprovedNotification extends Notification
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
            'icon'  => 'check-circle',
            'color' => 'success',
            'title' => 'Váš reseller účet byl schválen',
            'body'  => "Firma {$this->reseller->business_name} je nyní aktivní reseller partner.",
            'url'   => route('reseller.dashboard'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $markup = number_format((float) $this->reseller->markup_percent, 2);

        return (new MailMessage)
            ->subject('Váš reseller účet byl schválen — OnHost')
            ->greeting('Dobrý den,')
            ->line("Gratulujeme! Váš reseller účet pro firmu **{$this->reseller->business_name}** byl schválen a je nyní aktivní.")
            ->line("**Váš markup:** {$markup}%")
            ->when($this->reseller->custom_domain, fn ($m) => $m
                ->line("**Vlastní doména:** {$this->reseller->custom_domain}")
            )
            ->line('Přístup do reseller portálu je nyní aktivní — najdete jej v klientské zóně v sekci **Reseller**.')
            ->action('Otevřít reseller portál', route('reseller.dashboard'))
            ->line('Pokud máte dotazy ohledně reseller programu, kontaktujte náš tým.')
            ->salutation('Vítejte jako reseller partner, tým OnHost');
    }
}
