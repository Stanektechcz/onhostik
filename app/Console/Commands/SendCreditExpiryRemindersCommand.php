<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Billing\Enums\CreditTransactionType;
use App\Domains\Billing\Models\CreditExpiryReminder;
use App\Domains\Billing\Models\CreditTransaction;
use App\Notifications\CreditExpiryReminderNotification;
use Illuminate\Console\Command;

/**
 * Sends credit-expiry reminder emails at 30 days and 7 days before the
 * expires_at date on Deposit transactions.
 * Safe to re-run daily — fires once per (transaction, days_before) pair.
 */
class SendCreditExpiryRemindersCommand extends Command
{
    protected $signature   = 'billing:send-credit-expiry-reminders';
    protected $description = 'Send credit expiry reminder emails at 30d and 7d before expiration';

    /** @var list<int> */
    private const THRESHOLDS = [30, 7];

    public function handle(): int
    {
        $sent = 0;

        foreach (self::THRESHOLDS as $days) {
            $targetDate = now()->addDays($days)->toDateString();

            CreditTransaction::query()
                ->where('type', CreditTransactionType::Deposit)
                ->whereDate('expires_at', $targetDate)
                ->whereDoesntHave('expiryReminders', function ($q) use ($days): void {
                    $q->where('days_before', $days);
                })
                ->with(['customer.user'])
                ->each(function (CreditTransaction $tx) use ($days, &$sent): void {
                    $user = $tx->customer?->user;

                    if ($user === null) {
                        return;
                    }

                    try {
                        $user->notify(new CreditExpiryReminderNotification($tx, $days));

                        CreditExpiryReminder::create([
                            'credit_transaction_id' => $tx->id,
                            'days_before'           => $days,
                            'sent_at'               => now(),
                        ]);

                        $sent++;
                    } catch (\Throwable $e) {
                        report($e);
                    }
                });
        }

        $this->info("Sent {$sent} credit expiry reminder(s).");

        return self::SUCCESS;
    }
}
