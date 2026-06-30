<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Provisioning\Models\Service;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ServiceActivatedNotification extends Notification
{
    public function __construct(private readonly Service $service) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'icon'  => 'server',
            'color' => 'success',
            'title' => "Služba aktivována — {$this->service->label}",
            'body'  => 'Vaše hostingová služba je připravena k použití.',
            'url'   => route('panel.services.show', $this->service),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $service = $this->service;
        $plan    = $service->pricingPlan;
        $product = $plan?->product;

        return (new MailMessage)
            ->subject("Vaše služba je aktivní — {$service->label}")
            ->greeting('Dobrý den,')
            ->line("Vaše hostingová služba **{$service->label}** byla úspěšně aktivována a je připravena k použití.")
            ->when($product, fn ($m) => $m->line("**Produkt:** {$product->name} — {$plan->name}"))
            ->when($service->next_due_date, fn ($m) => $m
                ->line("**Platnost do:** {$service->next_due_date->format('d.m.Y')}")
            )
            ->when(count($service->credentials ?? []) > 0, fn ($m) => $m
                ->line('Přihlašovací údaje byly vygenerovány a jsou dostupné v klientské zóně.')
            )
            ->action('Správa služby', route('panel.services.show', $service))
            ->line('Pokud máte jakékoliv dotazy, náš tým je vám k dispozici.')
            ->salutation('Vítejte na Onhost.cz, tým OnHost');
    }
}
