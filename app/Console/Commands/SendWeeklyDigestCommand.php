<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Customer\Models\Customer;
use App\Domains\Support\Enums\TicketStatus;
use App\Notifications\WeeklyDigestNotification;
use Illuminate\Console\Command;

/**
 * Sends a weekly account summary to each customer who has a user account
 * and has at least one of: overdue invoices, upcoming renewals (14 days),
 * open tickets, or non-zero credit balance.
 *
 * Safe to re-run — intended to be scheduled weekly on Mondays.
 */
class SendWeeklyDigestCommand extends Command
{
    protected $signature   = 'notifications:send-weekly-digest';
    protected $description = 'Send weekly account summary emails to active customers';

    public function __construct(private readonly CreditLedger $ledger)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $sent = 0;

        Customer::query()
            ->whereHas('user')
            ->with('user')
            ->each(function (Customer $customer) use (&$sent): void {
                $user = $customer->user;

                if ($user === null) {
                    return;
                }

                /*
                 | Audit I127. `digest_frequency` had a settings screen and a
                 | database column, and nothing ever read it — a customer who
                 | picked "never" kept receiving the digest every Monday. An
                 | opt-out that does not opt you out is worse than none, because
                 | the customer believes they have already handled it.
                 |
                 | The digest type in the notification catalogue is the other,
                 | coarser control; this is the explicit per-user choice and it
                 | wins.
                 */
                if ($user->digest_frequency === 'never') {
                    return;
                }

                $overdueInvoices = Invoice::query()
                    ->where('customer_id', $customer->id)
                    ->where('status', InvoiceStatus::Overdue)
                    ->get();

                $upcomingRenewals = Invoice::query()
                    ->where('customer_id', $customer->id)
                    ->where('status', InvoiceStatus::Sent)
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<=', now()->addDays(14))
                    ->whereDate('due_date', '>=', now())
                    ->get();

                $openTickets = $customer->supportTickets()
                    ->whereIn('status', [TicketStatus::Open->value, TicketStatus::Answered->value])
                    ->count();

                $creditBalance = $this->ledger->getBalance($customer);

                // Only send if there's something worth reporting
                if ($overdueInvoices->isEmpty()
                    && $upcomingRenewals->isEmpty()
                    && $openTickets === 0
                    && $creditBalance->isZero()
                ) {
                    return;
                }

                try {
                    $user->notify(new WeeklyDigestNotification(
                        creditBalance: $creditBalance,
                        overdueInvoices: $overdueInvoices,
                        upcomingRenewals: $upcomingRenewals,
                        openTickets: $openTickets,
                    ));
                    $sent++;
                } catch (\Throwable $e) {
                    report($e);
                }
            });

        $this->info("Sent {$sent} weekly digest notification(s).");

        return self::SUCCESS;
    }
}
