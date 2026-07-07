<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Models\User;
use App\Notifications\RenewalFailureAdminSummaryNotification;
use App\Notifications\RenewalPaymentFailedNotification;
use Illuminate\Console\Command;

class HandleRenewalPaymentFailuresCommand extends Command
{
    protected $signature   = 'billing:handle-renewal-failures';
    protected $description = 'Send targeted renewal-failure notifications for overdue renewal invoices.';

    public function handle(): int
    {
        $graceDays = (int) config('billing.renewal_failure_grace_days', 3);
        $threshold = (int) config('billing.renewal_failure_admin_threshold', 3);

        $threshold_date = now()->subDays($graceDays);

        $invoices = Invoice::query()
            ->where('purpose', 'renewal')
            ->where('status', InvoiceStatus::Overdue->value)
            ->whereNull('renewal_failure_notified_at')
            ->whereNotNull('renewal_service_id')
            ->where('due_date', '<=', $threshold_date)
            ->with(['renewalService', 'customer.user'])
            ->get();

        if ($invoices->isEmpty()) {
            $this->line('No overdue renewal invoices pending failure notification.');
            return self::SUCCESS;
        }

        $notified = 0;

        foreach ($invoices as $invoice) {
            $service = $invoice->renewalService;
            $user    = $invoice->customer?->user;

            if ($service === null || $user === null) {
                $invoice->update(['renewal_failure_notified_at' => now()]);
                continue;
            }

            $user->notify(new RenewalPaymentFailedNotification($invoice, $service));
            $invoice->update(['renewal_failure_notified_at' => now()]);
            $notified++;
        }

        $this->info("Sent renewal failure notifications for {$notified} invoice(s).");

        if ($notified >= $threshold) {
            User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))
                ->each(fn (User $admin) => $admin->notify(
                    new RenewalFailureAdminSummaryNotification($notified),
                ));

            $this->info("Admin summary notification dispatched ({$notified} failures >= threshold {$threshold}).");
        }

        return self::SUCCESS;
    }
}
