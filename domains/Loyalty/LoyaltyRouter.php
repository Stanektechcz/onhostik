<?php

declare(strict_types=1);

namespace Onhost\Domain\Loyalty;

use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxEventDispatched;

/**
 * Domain events → loyalty points. Every award is idempotent (rule + reference), so a redelivered event never counts
 * twice; events without an organization (staff, platform) earn nothing.
 */
final class LoyaltyRouter
{
    public function __construct(private readonly LoyaltyService $loyalty, private readonly ReferralService $referrals, private readonly LoyaltyClawback $clawback, private readonly LoyaltyRedemptions $redemptions) {}

    public function __invoke(OutboxEventDispatched $event): void
    {
        $m = $event->message;
        $org = $m->organization_id ? (string) $m->organization_id : null;
        if ($org === null) {
            return;
        }
        if ((bool) data_get(Organization::query()->find($org)?->feature_flags, 'sandbox', false)) {
            return; // sandbox tenants earn nothing (audit §5j-9)
        }
        $p = (array) $m->payload;
        $rules = (array) config('onhost.loyalty.points', []);
        $ctx = CommandContext::system('loyalty');
        match ($m->name) {
            'order.paid' => (function () use ($org, $m, $p, $rules, $ctx) {
                $order = Order::query()->find((string) $m->aggregate_id);
                $per100 = (int) ($rules['order.paid_per_100'] ?? 1);
                $qualifies = $order !== null && $this->loyalty->qualifies((int) $order->total_minor, (string) $order->currency); // R6: a payment below the minimum earns nothing
                $points = $qualifies ? intdiv(max(0, (int) $order->total_minor), $this->loyalty->minimumPaymentMinor((string) $order->currency)) * $per100 : 0; // one point per minimum payment of the order's currency (CZK 100)
                if ($points > 0 && empty($p['released'])) {
                    $this->loyalty->award($org, 'order.paid', (string) $m->aggregate_id, $points, 'Objednávka '.($p['number'] ?? ''), $ctx);
                }
            })(),
            'invoice.paid' => (function () use ($m) { // the referred organization's first paid document settles its referral (audit §5j-2)
                $invoice = Invoice::query()->find((string) $m->aggregate_id);
                if ($invoice !== null) {
                    $this->referrals->onInvoicePaid($invoice);
                }
            })(),
            'chargeback.approved' => $this->referrals->onChargeback($org), // a rewarded referral charged back claws back and teaches the weights (audit §5l-4)
            'payment.succeeded' => $this->loyalty->qualifies((int) data_get($p, 'amount.minor', 0), (string) data_get($p, 'amount.currency', 'CZK'))
                ? $this->loyalty->award($org, 'payment.on_time', (string) $m->aggregate_id, (int) ($rules['payment.on_time'] ?? 10), 'Platba přijata', $ctx) // R6: only a payment of 100 CZK or more earns points
                : null,
            'invoice.issued' => $this->clawback->onCreditNote($org, (string) $m->aggregate_id, $ctx) // R6: a credit note (a chargeback refund is one) takes the points back
                + $this->redemptions->onCreditNote($org, (string) $m->aggregate_id, $ctx), // G3 (G-R2): and gives back the redeemed points of what it credited
            'payment.refunded' => $this->clawback->onPaymentRefunded($org, (string) $m->aggregate_id, $ctx), // F12b: a payment refunded to its source without a credit note
            'security.mfa' => (function () use ($org, $p, $rules, $ctx) {
                if (($p['action'] ?? $p['state'] ?? 'enabled') === 'disabled') {
                    return;
                }
                if ($this->loyalty->award($org, 'mfa.enabled', 'org', (int) ($rules['mfa.enabled'] ?? 50), 'Dvoufázové přihlášení zapnuto', $ctx)['awarded']) {
                    $this->loyalty->badge($org, 'guardian', $ctx);
                }
            })(),
            'service.activated' => (function () use ($org, $rules, $ctx) {
                if (Service::query()->withTrashed()->where('organization_id', $org)->count() <= 1 && $this->loyalty->award($org, 'service.first', 'first', (int) ($rules['service.first'] ?? 100), 'První aktivní služba', $ctx)['awarded']) {
                    $this->loyalty->badge($org, 'first-service', $ctx);
                }
            })(),
            'backup.completed', 'backup.succeeded' => (function () use ($org, $rules, $ctx) {
                if ($this->loyalty->award($org, 'backups.enabled', 'org', (int) ($rules['backups.enabled'] ?? 30), 'První dokončená záloha', $ctx)['awarded']) {
                    $this->loyalty->badge($org, 'archivist', $ctx);
                }
            })(),
            'monitoring.up', 'monitoring.enabled' => (function () use ($org, $rules, $ctx) {
                if ($this->loyalty->award($org, 'monitoring.enabled', 'org', (int) ($rules['monitoring.enabled'] ?? 20), 'Monitoring hlídá web', $ctx)['awarded']) {
                    $this->loyalty->badge($org, 'watchman', $ctx);
                }
            })(),
            'partner.approved' => (function () use ($org, $rules, $ctx) {
                if ($this->loyalty->award($org, 'referral', 'partner', (int) ($rules['referral'] ?? 200), 'Partnerský program', $ctx)['awarded']) {
                    $this->loyalty->badge($org, 'ambassador', $ctx);
                }
            })(),
            default => null,
        };
    }
}
