<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Billing\Actions\IssueRenewalInvoiceAction;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Notifications\RenewalReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

/**
 * Issues renewal proforma invoices for active services whose next_due_date
 * falls exactly billing.lifecycle.renewal_days_before days from now.
 *
 * Idempotency lives in IssueRenewalInvoiceAction (renewal_service_id +
 * due_date) — running this command twice on the same day, or for a
 * service that already has an open renewal invoice for its current cycle,
 * never creates a duplicate.
 *
 * Only ServiceStatus::Active services are considered — Pending, Suspended,
 * Terminated and Failed services are never renewed.
 */
class CreateRenewalInvoicesCommand extends Command
{
    protected $signature   = 'billing:create-renewals';
    protected $description = 'Issue renewal proforma invoices for services approaching their next due date';

    public function handle(IssueRenewalInvoiceAction $action): int
    {
        $daysBefore = Config::integer('billing.lifecycle.renewal_days_before', 7);
        $targetDate = now()->addDays($daysBefore)->toDateString();

        $issued  = 0;
        $skipped = 0;

        Service::query()
            ->where('status', ServiceStatus::Active)
            ->whereDate('next_due_date', $targetDate)
            ->with('orderItem.pricingPlan.product', 'orderItem.order', 'customer.user')
            ->each(function (Service $service) use ($action, &$issued, &$skipped): void {
                $invoice = $action->execute($service);

                if ($invoice === null) {
                    $skipped++;

                    return;
                }

                if ($invoice->wasRecentlyCreated) {
                    $issued++;

                    /* Send renewal reminder email for newly issued invoices. */
                    try {
                        $user = $service->customer?->user;
                        if ($user !== null) {
                            $user->notify(new RenewalReminderNotification(
                                service:  $service,
                                invoice:  $invoice,
                                daysLeft: $daysBefore,
                            ));
                        }
                    } catch (\Throwable $e) {
                        report($e); // non-fatal — invoice was issued
                    }
                }
            });

        $this->info("Issued {$issued} renewal invoice(s), {$skipped} skipped (no order item/plan/customer).");

        return self::SUCCESS;
    }
}
