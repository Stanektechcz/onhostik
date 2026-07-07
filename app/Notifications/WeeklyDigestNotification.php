<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Shared\Support\MoneyFormatter;
use Brick\Money\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class WeeklyDigestNotification extends Notification
{
    /**
     * @param Collection<int, Invoice> $overdueInvoices
     * @param Collection<int, Invoice> $upcomingRenewals
     * @param int                      $openTickets
     */
    public function __construct(
        private readonly Money $creditBalance,
        private readonly Collection $overdueInvoices,
        private readonly Collection $upcomingRenewals,
        private readonly int $openTickets,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Váš týdenní přehled — Onhost.cz')
            ->greeting('Dobrý den,')
            ->line('Přinášíme vám váš týdenní přehled stavu účtu.');

        // Overdue invoices
        if ($this->overdueInvoices->isNotEmpty()) {
            $mail->line('⚠️ **Nezaplacené faktury (' . $this->overdueInvoices->count() . ')**');
            foreach ($this->overdueInvoices->take(5) as $invoice) {
                $mail->line('• Faktura ' . $invoice->number . ' — ' . MoneyFormatter::format($invoice->total));
            }
            $mail->action('Zaplatit faktury', route('panel.billing.invoices'));
        }

        // Upcoming renewals
        if ($this->upcomingRenewals->isNotEmpty()) {
            $mail->line('🔄 **Blížící se obnovy (' . $this->upcomingRenewals->count() . ')**');
            foreach ($this->upcomingRenewals->take(5) as $invoice) {
                $dueDate = $invoice->due_date?->format('d.m.Y') ?? '—';
                $mail->line('• ' . $invoice->number . ' — splatnost ' . $dueDate);
            }
        }

        // Credit balance
        $mail->line('💳 **Kredit:** ' . MoneyFormatter::format($this->creditBalance));

        // Open tickets
        if ($this->openTickets > 0) {
            $mail->line('🎫 **Otevřené tikety:** ' . $this->openTickets);
            $mail->action('Přejít na podporu', route('panel.support.index'));
        }

        if ($this->overdueInvoices->isEmpty() && $this->openTickets === 0) {
            $mail->line('✅ Vše je v pořádku — žádné nezaplacené faktury ani otevřené tikety.');
        }

        return $mail->salutation('Tým Onhost.cz');
    }
}
