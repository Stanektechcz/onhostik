<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Billing\Models\Order;
use App\Domains\Shared\Support\MoneyFormatter;
use Illuminate\Notifications\Messages\MailMessage;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Notifications\Notification;

/**
 * Confirms to the customer that their order was received — sent on order
 * creation, before payment (distinct from InvoicePaidNotification).
 */
class OrderReceivedNotification extends Notification
{
    use RespectsNotificationPreferences;

    public function __construct(private readonly Order $order) {}

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        return $this->channelsFor($notifiable, 'service', ['mail', 'database']);
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $no = '#' . strtoupper(substr($this->order->uuid, 0, 8));

        return (new MailMessage())
            ->subject("Přijali jsme vaši objednávku {$no} — OnHost")
            ->greeting('Dobrý den,')
            ->line("děkujeme za objednávku {$no}.")
            ->line('Celková částka: ' . MoneyFormatter::format($this->order->total) . ' (vč. DPH).')
            ->line('Objednávku aktivujeme ihned po přijetí platby.')
            ->action('Zobrazit objednávku', route('panel.orders.show', $this->order))
            ->line('Děkujeme, že využíváte OnHost.');
    }

    /** @return array<string, mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'type'     => 'order_received',
            'icon'     => 'shopping-bag',
            'color'    => 'primary',
            'title'    => 'Objednávka přijata',
            'body'     => 'Objednávka #' . strtoupper(substr($this->order->uuid, 0, 8)) . ' čeká na úhradu.',
            'url'      => route('panel.orders.show', $this->order),
            'order_id' => $this->order->id,
        ];
    }
}
