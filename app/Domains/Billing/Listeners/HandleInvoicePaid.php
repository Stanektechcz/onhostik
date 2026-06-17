<?php

declare(strict_types=1);

namespace App\Domains\Billing\Listeners;

use App\Domains\Billing\Actions\IssueTaxDocumentAction;
use App\Domains\Billing\Enums\CreditTransactionType;
use App\Domains\Billing\Enums\InvoiceType;
use App\Domains\Billing\Enums\OrderStatus;
use App\Domains\Billing\Events\InvoicePaid;
use App\Domains\Billing\Exceptions\IncompleteBillingDetailsException;
use App\Domains\Billing\Models\CreditTransaction;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Order;
use App\Domains\Billing\Models\OrderItem;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Jobs\ChangeServiceStateJob;
use App\Domains\Provisioning\Jobs\ProvisionHostingServiceJob;
use App\Domains\Provisioning\Jobs\RegisterDomainJob;
use App\Domains\Provisioning\Models\Server;
use App\Domains\Provisioning\Models\Service;
use App\Notifications\InvoicePaidNotification;
use Illuminate\Support\Facades\DB;

/**
 * Reacts to InvoicePaid — the single event path shared by the mock payment,
 * the credit payment and (later) the real Comgate webhook.
 *
 * Responsibilities (all idempotent — the event itself fires exactly once,
 * but every step also tolerates replays defensively):
 *  1. order: Pending → Processing + paid_at, exactly once,
 *  2. one Service per order item (keyed by order_item_id) on first purchase,
 *  3. queue the aaPanel/Proxmox provisioning job for new hosting/VPS items,
 *  4. queue the WEDOS domain registration job when the item carries a domain,
 *  5. for renewal invoices (purpose=renewal): extend the service's
 *     next_due_date and unsuspend it if it was suspended for non-payment —
 *     instead of (re-)provisioning, which only applies to first purchase.
 *
 * Runs synchronously inside the payment transaction: the state transitions
 * commit atomically with the payment; jobs land in the queue table within
 * the same transaction and are picked up by the worker after commit.
 */
final class HandleInvoicePaid
{
    public function handle(InvoicePaid $event): void
    {
        $invoice = $event->invoice;

        // Credit top-ups deposit into the wallet — no order, no provisioning.
        if ($invoice->purpose === 'credit_topup') {
            $this->creditWallet($invoice);
            $this->notifyCustomer($invoice);

            return;
        }

        $order = $invoice->order;

        if ($order === null) {
            $this->issueTaxDocument($invoice);
            $this->notifyCustomer($invoice);

            return;
        }

        $this->markOrderPaid($order, $invoice->id);

        if ($invoice->purpose === 'renewal') {
            $this->applyRenewal($invoice);
        } else {
            $this->provisionOrderItems($order);
        }

        $this->issueTaxDocument($invoice);

        $this->notifyCustomer($invoice);
    }

    /**
     * Extends the renewed service's next_due_date by its plan's billing
     * cycle, and unsuspends it if it was suspended for non-payment.
     *
     * Idempotency: renewal_applied_at is checked and set under row lock —
     * a replayed InvoicePaid for the same invoice never extends twice.
     *
     * Anchor date: if the service's current next_due_date is still in the
     * future (on-time/early payment), extend from THAT date so paying
     * early never shrinks the customer's paid-for period. Otherwise
     * (overdue/missing), anchor to the invoice's paid_at so a very late
     * payment starts a fresh cycle from today rather than compounding a
     * stale date.
     */
    private function applyRenewal(Invoice $invoice): void
    {
        $serviceId = $invoice->renewal_service_id;

        if ($serviceId === null) {
            return; // defensive — purpose=renewal always carries this
        }

        DB::transaction(function () use ($invoice, $serviceId): void {
            $lockedInvoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($lockedInvoice->renewal_applied_at !== null) {
                return; // replay — already extended once
            }

            $service = Service::whereKey($serviceId)->lockForUpdate()->first();

            if ($service === null) {
                return;
            }

            $cycleMonths = $service->orderItem?->pricingPlan?->billing_cycle?->months() ?? 1;
            $oldDueDate  = $service->next_due_date;

            $base = ($oldDueDate !== null && $oldDueDate->isFuture())
                ? $oldDueDate
                : ($invoice->paid_at ?? now())->copy()->startOfDay();

            $newDueDate = $base->copy()->addMonths($cycleMonths);

            $wasSuspended = $service->status === ServiceStatus::Suspended;

            $service->update(['next_due_date' => $newDueDate]);
            $lockedInvoice->update(['renewal_applied_at' => now()]);

            activity('service')
                ->performedOn($service)
                ->withProperties([
                    'invoice_id'        => $invoice->id,
                    'previous_due_date' => $oldDueDate?->toDateString(),
                    'new_due_date'      => $newDueDate->toDateString(),
                    'was_suspended'     => $wasSuspended,
                ])
                ->log('service.renewed');

            if ($wasSuspended) {
                ChangeServiceStateJob::dispatch($service->id, 'unsuspend', 'renewal_paid');
            }
        });
    }

    private function provisionOrderItems(Order $order): void
    {
        foreach ($order->items as $item) {
            $service = $this->ensureService($order, $item);

            if ($service === null) {
                continue;
            }

            /** @var array<string, mixed> $config */
            $config = $item->config ?? [];

            // AAPanel (webhosting/mailhosting) and Proxmox (VPS) both have
            // working drivers and provision through the same job. Pterodactyl
            // (gamehosting) is intentionally excluded — no driver exists yet.
            if (in_array($service->provisioning_driver, [ProvisioningDriver::AAPanel, ProvisioningDriver::Proxmox], true)) {
                ProvisionHostingServiceJob::dispatch($service->id);
            }

            $domain = $config['domain'] ?? null;

            if (is_string($domain) && $domain !== '' && ($config['register_domain'] ?? false) === true) {
                RegisterDomainJob::dispatch($service->id, $domain);
            }
        }
    }

    /**
     * Deposits a paid top-up into the credit ledger — exactly once.
     * Idempotency: one deposit per invoice reference, checked before the
     * ledger write (the ledger itself is append-only on top of that).
     */
    private function creditWallet(Invoice $invoice): void
    {
        $customer = $invoice->customer;
        $total    = $invoice->total;

        if ($customer === null) {
            return;
        }

        $alreadyCredited = CreditTransaction::query()
            ->where('reference_type', Invoice::class)
            ->where('reference_id', $invoice->id)
            ->where('type', CreditTransactionType::Deposit->value)
            ->exists();

        if ($alreadyCredited) {
            return; // replay — never double-credit
        }

        $entry = app(CreditLedger::class)->deposit(
            customer: $customer,
            amount: $total,
            description: "Dobití kreditu dle {$invoice->number}",
            reference: $invoice,
        );

        activity('credit')
            ->performedOn($invoice)
            ->withProperties([
                'credit_transaction_id' => $entry->id,
                'amount'                => $total->getMinorAmount()->toInt(),
            ])
            ->log('credit.topup_completed');
    }

    /**
     * Issues the post-payment tax document where safe; incomplete billing
     * details only block the document (audited), never the payment flow.
     */
    private function issueTaxDocument(Invoice $invoice): void
    {
        if ($invoice->type !== InvoiceType::Proforma) {
            return;
        }

        try {
            app(IssueTaxDocumentAction::class)->execute($invoice);
        } catch (IncompleteBillingDetailsException $e) {
            activity('invoice')
                ->performedOn($invoice)
                ->withProperties(['reason' => 'incomplete_billing_details'])
                ->log('invoice.tax_document_blocked');
        }
    }

    private function notifyCustomer(Invoice $invoice): void
    {
        $user = $invoice->customer?->user;

        $user?->notify(new InvoicePaidNotification($invoice));
    }

    private function markOrderPaid(Order $order, int $invoiceId): void
    {
        DB::transaction(function () use ($order, $invoiceId): void {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->paid_at !== null) {
                return; // replay — already transitioned
            }

            $locked->update([
                'status'  => OrderStatus::Processing,
                'paid_at' => now(),
            ]);

            activity('order')
                ->performedOn($locked)
                ->withProperties(['transition' => 'paid', 'invoice_id' => $invoiceId])
                ->log('order.paid');
        });
    }

    private function ensureService(Order $order, OrderItem $item): ?Service
    {
        $plan    = $item->pricingPlan;
        $product = $plan?->product;

        if ($plan === null || $product === null) {
            return null; // defensive — Phase 2 items always reference a plan
        }

        $driver = $product->provisioning_driver ?? ProvisioningDriver::AAPanel;

        /** @var array<string, mixed> $config */
        $config = $item->config ?? [];
        $domain = is_string($config['domain'] ?? null) ? $config['domain'] : null;

        $server = Server::query()
            ->where('driver', $driver->value)
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->first();

        $service = Service::firstOrCreate(
            ['order_item_id' => $item->id],
            [
                'customer_id'         => $order->customer_id,
                'product_id'          => $product->id,
                'server_id'           => $server?->id,
                'provisioning_driver' => $driver,
                'status'              => ServiceStatus::Pending,
                'label'               => $domain ?? mb_strtolower((string) $plan->name) . '-' . $item->id,
                'resources'           => $plan->resources,
                'next_due_date'       => $item->period_to,
            ],
        );

        if ($service->wasRecentlyCreated) {
            activity('service')
                ->performedOn($service)
                ->withProperties(['order_id' => $order->id, 'order_item_id' => $item->id])
                ->log('service.created');
        }

        return $service;
    }
}
