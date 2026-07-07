<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Notifications\LateFeeAppliedNotification;
use Illuminate\Console\Command;

class ApplyLateFeesCommand extends Command
{
    protected $signature   = 'billing:apply-late-fees';
    protected $description = 'Apply a flat late fee to overdue invoices past the configured grace period.';

    public function handle(): int
    {
        $feeMinor  = (int) config('billing.late_fee_minor', 0);
        $feeDays   = (int) config('billing.late_fee_days', 7);

        if ($feeMinor <= 0) {
            $this->line('Late fee is disabled (billing.late_fee_minor = 0). Skipping.');
            return self::SUCCESS;
        }

        $threshold = now()->subDays($feeDays);

        $invoices = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Overdue->value])
            ->whereNull('late_fee_applied_at')
            ->where('due_date', '<=', $threshold)
            ->with('customer.user')
            ->get();

        if ($invoices->isEmpty()) {
            $this->line('No overdue invoices eligible for late fee.');
            return self::SUCCESS;
        }

        $applied = 0;

        foreach ($invoices as $invoice) {
            $invoice->update([
                'late_fee_amount'    => $feeMinor,
                'late_fee_applied_at' => now(),
            ]);

            $user = $invoice->customer?->user;
            if ($user !== null) {
                $user->notify(new LateFeeAppliedNotification($invoice, $feeMinor));
            }

            $applied++;
        }

        $this->info("Applied late fee ({$feeMinor} minor units) to {$applied} invoice(s).");

        return self::SUCCESS;
    }
}
