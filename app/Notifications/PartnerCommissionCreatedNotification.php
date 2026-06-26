<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Partner\Models\PartnerCommission;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PartnerCommissionCreatedNotification extends Notification
{
    public function __construct(private readonly PartnerCommission $commission) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $commission = $this->commission;
        $amount     = number_format($commission->amount / 100, 0, ',', ' ') . ' ' . $commission->currency;
        $holdDays   = config('partner.commission_hold_days', 14);

        return (new MailMessage)
            ->subject("Nová provize {$amount} — Onhost Partner Program")
            ->greeting('Dobrý den,')
            ->line("Evidujeme novou provizi ve výši **{$amount}** za referral objednávku.")
            ->line("Provize bude k dispozici pro výplatu po uplynutí ochranné lhůty {$holdDays} dní.")
            ->line('Po schválení administrátorem vás budeme kontaktovat s detaily výplaty.')
            ->action('Zobrazit provize', route('partner.commissions'))
            ->salutation('Děkujeme za spolupráci, tým Onhost.cz');
    }
}
