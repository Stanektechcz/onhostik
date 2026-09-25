<?php

declare(strict_types=1);

namespace Onhost\Domain\Marketplace;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Onhost\Domain\Billing\BillingPeriod;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Marketplace\Models\MarketplaceListing;
use Onhost\Domain\Marketplace\Models\MarketplaceOrder;
use Onhost\Domain\Orders\CreditOrderPolicy;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Domain\Partners\Models\PartnerCommission;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Tax\TaxEngine;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Files\FileStore;
use Onhost\Platform\Files\UploadGuard;
use Onhost\Platform\Files\VirusScanner;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Marketplace of partner services (audit §5j-1): approved partners list what they deliver (WordPress care, SEO audits,
 * migrations, backups to the customer's own bucket …), staff publish the listing, customers order it from credit —
 * the platform issues the tax document and keeps its commission, the partner's share becomes a payable commission
 * row once the customer accepts the delivery (or after the auto-accept window). A dispute lands with support, who
 * either refund the credit (with a credit note) or confirm the delivery. Nothing here talks to a provider.
 */
final class MarketplaceService
{
    public const CATEGORIES = ['care', 'seo', 'security', 'backup', 'migration', 'development', 'design', 'content'];

    public function __construct(
        private readonly TaxEngine $tax,
        private readonly WalletService $wallets,
        private readonly InvoiceService $invoices,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    // ── listings (partner) ────────────────────────────────────────────────────

    /** @param  array<string,mixed>  $input */
    public function createListing(Partner $partner, array $input, CommandContext $context): MarketplaceListing
    {
        $this->assertActivePartner($partner);
        $data = $this->listingData($input, null);
        if (MarketplaceListing::query()->where('key', $data['key'])->exists()) {
            throw new DomainError('marketplace_key_taken', 'A listing with this key already exists.', 409, ['field' => 'key']);
        }
        $listing = MarketplaceListing::query()->create($data + ['partner_id' => $partner->id, 'state' => MarketplaceListing::DRAFT, 'currency' => $partner->currency]);
        $this->audit->record($context->withScope($partner->organization_id), 'marketplace.listing.create', 'succeeded', ['listing' => $listing->id, 'key' => $listing->key], 'marketplace_listing', $listing->id);
        $this->outbox->publish(GenericEvent::of('marketplace.listing.submitted', 'marketplace_listing', $listing->id, ['key' => $listing->key, 'title' => $listing->title, 'partner_id' => $partner->id, 'price' => $this->price($listing)], $partner->organization_id));

        return $listing;
    }

    /** @param  array<string,mixed>  $input Price or billing changes send a published listing back to draft (staff publish it again). */
    public function updateListing(Partner $partner, MarketplaceListing $listing, array $input, CommandContext $context): MarketplaceListing
    {
        $this->assertOwner($partner, $listing);
        if ($listing->state === MarketplaceListing::RETIRED) {
            throw new DomainError('marketplace_listing_retired', 'A retired listing cannot be edited.', 409);
        }
        $data = $this->listingData($input, $listing);
        $commercial = $listing->price_minor !== $data['price_minor'] || $listing->billing !== $data['billing'] || $listing->title !== $data['title'];
        $state = $commercial && $listing->state === MarketplaceListing::PUBLISHED ? MarketplaceListing::DRAFT : $listing->state;
        $listing->forceFill(array_diff_key($data, ['key' => true]) + ['state' => $state])->save();
        $this->audit->record($context->withScope($partner->organization_id), 'marketplace.listing.update', 'succeeded', ['listing' => $listing->id, 'state' => $state], 'marketplace_listing', $listing->id);

        return $listing;
    }

    /** Partners pause and resume their own published listings; staff publish, pause and retire any listing. */
    public function setListingState(MarketplaceListing $listing, string $state, ?string $reason, CommandContext $context, ?Partner $partner = null): MarketplaceListing
    {
        if (! in_array($state, MarketplaceListing::STATES, true)) {
            throw new DomainError('marketplace_state_invalid', 'Unknown listing state.', 422, ['field' => 'state']);
        }
        if ($partner !== null) {
            $this->assertOwner($partner, $listing);
            $allowed = match ($listing->state) {
                MarketplaceListing::PUBLISHED => [MarketplaceListing::PAUSED],
                MarketplaceListing::PAUSED => [MarketplaceListing::PUBLISHED],
                default => [],
            };
            if (! in_array($state, $allowed, true)) {
                throw new DomainError('marketplace_state_not_allowed', 'Partners pause and resume published listings; staff publish and retire them.', 409, ['from' => $listing->state, 'to' => $state]);
            }
        } elseif ($state === MarketplaceListing::PUBLISHED && $listing->state === MarketplaceListing::RETIRED) {
            throw new DomainError('marketplace_listing_retired', 'A retired listing stays retired; create a new one.', 409);
        }
        $from = $listing->state;
        $listing->forceFill(['state' => $state, 'meta' => array_merge((array) $listing->meta, ['state_reason' => $reason, 'state_by' => $context->actorId, 'published_at' => $state === MarketplaceListing::PUBLISHED ? now()->toIso8601String() : data_get($listing->meta, 'published_at')])])->save();
        $partnerOrg = Partner::query()->find($listing->partner_id)?->organization_id;
        $this->audit->record($context->withScope($partnerOrg), 'marketplace.listing.state', 'succeeded', ['listing' => $listing->id, 'from' => $from, 'to' => $state, 'reason' => $reason], 'marketplace_listing', $listing->id);
        if ($from !== $state && in_array($state, [MarketplaceListing::PUBLISHED, MarketplaceListing::RETIRED], true) && $partner === null) {
            $this->outbox->publish(GenericEvent::of('marketplace.listing.'.$state, 'marketplace_listing', $listing->id, ['key' => $listing->key, 'title' => $listing->title, 'reason' => $reason], $partnerOrg));
        }

        return $listing;
    }

    // ── orders (customer) ─────────────────────────────────────────────────────

    /** @param  array{brief?:string, service_id?:string}  $input */
    public function order(Organization $organization, ?User $user, MarketplaceListing $listing, array $input, CommandContext $context): MarketplaceOrder
    {
        // owner decision 20 (TASK-0021): a marketplace order is paid from credit at once — by the owner or the billing admin
        app(CreditOrderPolicy::class)->assertMaySpend($organization, $context, 'Požádejte vlastníka, aby službu objednal.');
        if ($listing->state !== MarketplaceListing::PUBLISHED) {
            throw new DomainError('marketplace_listing_unavailable', 'This service is not available right now.', 409, ['state' => $listing->state]);
        }
        $partner = Partner::query()->find($listing->partner_id);
        if ($partner === null || $partner->state !== 'active') {
            throw new DomainError('marketplace_partner_inactive', 'The partner behind this service is not active.', 409);
        }
        if ($partner->organization_id === $organization->id) {
            throw new DomainError('marketplace_own_listing', 'A partner cannot order their own listing.', 422);
        }
        $brief = trim((string) ($input['brief'] ?? ''));
        if (mb_strlen($brief) < 10) {
            throw new DomainError('marketplace_brief_required', 'Describe what you need (at least 10 characters).', 422, ['field' => 'brief']);
        }
        $serviceId = isset($input['service_id']) && $input['service_id'] !== '' ? (string) $input['service_id'] : null;
        if ($serviceId !== null && ! Service::query()->where('organization_id', $organization->id)->whereKey($serviceId)->exists()) {
            throw DomainError::notFound('service');
        }
        if ($listing->currency !== $organization->currency) {
            throw new DomainError('marketplace_currency_mismatch', "The listing is priced in {$listing->currency}; your account runs in {$organization->currency}.", 409, ['listing' => $listing->currency, 'account' => $organization->currency]);
        }
        $net = Money::minor((int) $listing->price_minor, $listing->currency);
        $decision = $this->tax->calculate(['country' => $organization->country, 'customer_class' => $organization->customer_class, 'vat_status' => $organization->vat_status], [['key' => 'mkt', 'net' => $net, 'product_class' => 'service']], $net->currency, $organization->id);
        $line = $decision['lines'][0];
        $gross = Money::minor((int) $net->minor + (int) $line['tax']->minor, $net->currency);
        $commission = $net->percent((string) $listing->commission_pct);

        return DB::transaction(function () use ($organization, $user, $listing, $partner, $brief, $serviceId, $net, $gross, $line, $decision, $commission, $context) {
            $order = MarketplaceOrder::query()->create([
                'listing_id' => $listing->id, 'partner_id' => $partner->id, 'organization_id' => $organization->id, 'ordered_by' => $user?->id, 'service_id' => $serviceId,
                'state' => MarketplaceOrder::ORDERED, 'brief' => mb_substr($brief, 0, 4000), 'price_minor' => $net->minor, 'currency' => $net->currency->value,
                'commission_minor' => $commission->minor, 'partner_minor' => $net->minor - $commission->minor, 'due_at' => now()->addDays(max(1, (int) $listing->delivery_days)),
            ]);
            $ctx = $context->withScope($organization->id);
            $this->wallets->charge($organization, $gross, 'marketplace', "marketplace:{$order->id}", $ctx, 'marketplace_order', $order->id, Money::minor((int) $line['tax']->minor, $net->currency));
            $draft = $this->invoices->draft($organization, 'invoice', $net->currency->value, [[
                'sku' => 'mkt-'.$listing->key, 'description' => 'Marketplace: '.$listing->title, 'qty' => 1, 'unit' => 'ks',
                'unit_net' => $net->minor, 'discount' => 0, 'net' => $net->minor, 'tax_rate' => (string) $line['rate'], 'tax_category' => (string) $line['category'], 'tax' => (int) $line['tax']->minor, 'total' => $gross->minor,
                'service_id' => $serviceId,
            ]], $ctx, null, ['payment_method' => 'wallet', 'marketplace_order_id' => $order->id, 'tax_calculation_id' => $decision['calculation']->id]);
            $invoice = $this->invoices->issue($draft, $ctx, dueDays: 0);
            $this->invoices->markPaid($invoice, $gross, 'wallet', $ctx, postLedger: false);
            $subscriptionId = null;
            if ($listing->billing === 'monthly') { // §5k-2: the listing renews monthly on the subscription engine; the partner's share is booked per period
                $subscription = Subscription::query()->create([
                    'organization_id' => $organization->id, 'service_id' => null, 'currency' => $net->currency->value, 'period' => 'month', 'amount_minor' => $net->minor, 'state' => Subscription::ACTIVE,
                    'current_period_start' => now(), 'current_period_end' => BillingPeriod::end(now(), 'month'), 'next_renewal_at' => BillingPeriod::end(now(), 'month'), 'auto_renew' => true, 'cancel_at_period_end' => false, 'renewal_priority' => 'normal',
                ]);
                $subscriptionId = $subscription->id;
            }
            $order->forceFill(['invoice_id' => $invoice->id, 'subscription_id' => $subscriptionId])->save();
            $this->audit->record($ctx, 'marketplace.order', 'succeeded', ['order' => $order->id, 'listing' => $listing->key, 'total' => $gross, 'partner' => $partner->id], 'marketplace_order', $order->id);
            $payload = ['listing' => $listing->key, 'title' => $listing->title, 'total' => $gross, 'net' => $net, 'due_at' => $order->due_at?->toIso8601String(), 'partner_id' => $partner->id, 'customer' => $organization->name, 'invoice' => $invoice->number];
            $this->outbox->publish(GenericEvent::of('marketplace.ordered', 'marketplace_order', $order->id, $payload, $organization->id));
            $this->outbox->publish(GenericEvent::of('marketplace.assigned', 'marketplace_order', $order->id, $payload + ['brief' => mb_substr($brief, 0, 500)], $partner->organization_id));

            return $order;
        }, 3);
    }

    public function start(MarketplaceOrder $order, Partner $partner, CommandContext $context): MarketplaceOrder
    {
        $this->assertPartnerOrder($partner, $order);
        $this->transition($order, [MarketplaceOrder::ORDERED], MarketplaceOrder::IN_PROGRESS, $context, $partner->organization_id);

        return $order;
    }

    /** @param array<string,mixed> $evidence the checklist the partner ticked for a monthly deliverable (§5o-2) */
    public function deliver(MarketplaceOrder $order, Partner $partner, string $note, CommandContext $context, array $evidence = []): MarketplaceOrder
    {
        $this->assertPartnerOrder($partner, $order);
        if (mb_strlen(trim($note)) < 5) {
            throw new DomainError('marketplace_delivery_note_required', 'Tell the customer what was delivered (at least 5 characters).', 422, ['field' => 'note']);
        }
        if ($order->subscription_id !== null && $order->state === MarketplaceOrder::ACCEPTED) { // §5n-2: the monthly deliverable of a running listing
            return $this->deliverPeriod($order, $partner, trim($note), $context, $evidence);
        }
        $order->forceFill(['delivery_note' => mb_substr(trim($note), 0, 4000), 'delivered_at' => now()])->save();
        $this->transition($order, [MarketplaceOrder::ORDERED, MarketplaceOrder::IN_PROGRESS, MarketplaceOrder::DISPUTED], MarketplaceOrder::DELIVERED, $context, $partner->organization_id);
        $listing = MarketplaceListing::query()->find($order->listing_id);
        $this->outbox->publish(GenericEvent::of('marketplace.delivered', 'marketplace_order', $order->id, ['title' => $listing?->title, 'note' => mb_substr($order->delivery_note, 0, 500), 'auto_accept_days' => $this->autoAcceptDays()], $order->organization_id));

        return $order;
    }

    /** A running monthly listing: the partner reports the period's deliverable; the customer hears it, the period counts as served. */
    private function deliverPeriod(MarketplaceOrder $order, Partner $partner, string $note, CommandContext $context, array $evidence = []): MarketplaceOrder
    {
        $subscription = Subscription::query()->find($order->subscription_id);
        if ($subscription === null || ! in_array($subscription->state, [Subscription::ACTIVE, Subscription::PAST_DUE], true)) {
            throw new DomainError('marketplace_subscription_inactive', 'The listing is not running.', 409);
        }
        $listing = MarketplaceListing::query()->find($order->listing_id);
        $items = [];
        $missing = [];
        $uploads = (array) ($order->period_uploads ?? []);
        foreach ($listing !== null ? $this->checklistFor($listing) : [] as $item) { // §5o-2: the deliverable is a matter of facts — every item ticked, filled or uploaded (§5p-3)
            if ($item['kind'] === 'file') {
                if (empty($uploads[$item['key']]['path'])) {
                    $missing[] = $item['key'];

                    continue;
                }
                $items[$item['key']] = array_intersect_key((array) $uploads[$item['key']], array_flip(['path', 'name', 'size', 'mime', 'at']));

                continue;
            }
            $value = $evidence[$item['key']] ?? null;
            if ($item['kind'] === 'check' ? filter_var($value, FILTER_VALIDATE_BOOL) !== true : trim((string) $value) === '') {
                $missing[] = $item['key'];

                continue;
            }
            $items[$item['key']] = $item['kind'] === 'check' ? true : mb_substr(trim((string) $value), 0, 120);
        }
        if ($missing !== []) {
            throw new DomainError('marketplace_checklist_incomplete', 'Tick or fill every checklist item: '.implode(', ', $missing).'.', 422, ['field' => 'evidence', 'missing' => $missing]);
        }
        $history = array_slice((array) ($order->period_evidence ?? []), -23);
        $history[] = ['period_end' => $subscription->current_period_end?->toIso8601String(), 'at' => now()->toIso8601String(), 'note' => mb_substr($note, 0, 500), 'items' => $items];
        $order->forceFill(['delivery_note' => mb_substr($note, 0, 4000), 'period_delivered_at' => now(), 'period_evidence' => $history, 'period_uploads' => null])->save();
        $this->audit->record($context->withScope($order->organization_id), 'marketplace.period.deliver', 'succeeded', ['order' => $order->id, 'period_end' => $subscription->current_period_end?->toIso8601String()], 'marketplace_order', $order->id);
        $this->outbox->publish(GenericEvent::of('marketplace.period_delivered', 'marketplace_order', $order->id, ['title' => $listing?->title, 'note' => mb_substr($note, 0, 500), 'period_end' => $subscription->current_period_end?->toIso8601String()], $order->organization_id));

        return $order;
    }

    /** The customer confirms the delivery: the partner's share becomes a payable commission row. */
    public function accept(MarketplaceOrder $order, Organization $organization, CommandContext $context): MarketplaceOrder
    {
        $this->assertCustomerOrder($organization, $order);

        return $this->settleAccepted($order, $context, 'customer');
    }

    public function dispute(MarketplaceOrder $order, Organization $organization, string $reason, CommandContext $context): MarketplaceOrder
    {
        $this->assertCustomerOrder($organization, $order);
        if (mb_strlen(trim($reason)) < 10) {
            throw new DomainError('marketplace_dispute_reason_required', 'Say what is wrong with the delivery (at least 10 characters).', 422, ['field' => 'reason']);
        }
        $order->forceFill(['dispute_reason' => mb_substr(trim($reason), 0, 2000)])->save();
        $this->transition($order, [MarketplaceOrder::DELIVERED, MarketplaceOrder::IN_PROGRESS, MarketplaceOrder::ORDERED], MarketplaceOrder::DISPUTED, $context, $organization->id);
        $listing = MarketplaceListing::query()->find($order->listing_id);
        $partnerOrg = Partner::query()->find($order->partner_id)?->organization_id;
        $this->outbox->publish(GenericEvent::of('marketplace.disputed', 'marketplace_order', $order->id, ['title' => $listing?->title, 'reason' => $order->dispute_reason, 'customer' => $organization->name, 'partner_organization_id' => $partnerOrg], $organization->id));

        return $order;
    }

    /** A customer may cancel while nothing started: the credit comes back, the tax document is corrected. */
    public function cancel(MarketplaceOrder $order, Organization $organization, CommandContext $context): MarketplaceOrder
    {
        $this->assertCustomerOrder($organization, $order);
        if ($order->subscription_id !== null && $order->state === MarketplaceOrder::ACCEPTED) { // a running monthly listing ends with its paid period (§5k-2)
            $subscription = Subscription::query()->find($order->subscription_id);
            if ($subscription !== null && in_array($subscription->state, [Subscription::ACTIVE, Subscription::PAST_DUE], true)) {
                $subscription->forceFill(['cancel_at_period_end' => true, 'auto_renew' => false, 'next_renewal_at' => $subscription->current_period_end])->save();
                $this->audit->record($context->withScope($organization->id), 'marketplace.subscription.cancel', 'succeeded', ['order' => $order->id, 'period_end' => $subscription->current_period_end?->toIso8601String()], 'marketplace_order', $order->id);

                return $order;
            }
        }
        if ($order->refund_offered_at !== null && in_array($order->state, [MarketplaceOrder::ORDERED, MarketplaceOrder::IN_PROGRESS], true)) { // §5l-2: past the grace after the due date the customer may take the refund without a dispute
            return $this->refund($order, 'Nedodáno v termínu — vrácení bez sporu', $context);
        }
        if ($order->state !== MarketplaceOrder::ORDERED) {
            throw new DomainError('marketplace_order_started', 'The partner already started; open a dispute instead.', 409, ['state' => $order->state]);
        }

        return $this->refund($order, 'Zrušeno zákazníkem před zahájením', $context);
    }

    /** Support settles a dispute: `refund` returns the credit, `deliver` confirms the delivery for the partner. */
    public function resolveDispute(MarketplaceOrder $order, string $decision, string $reason, CommandContext $context): MarketplaceOrder
    {
        if ($order->state !== MarketplaceOrder::DISPUTED) {
            throw new DomainError('marketplace_not_disputed', 'Only a disputed order can be resolved.', 409, ['state' => $order->state]);
        }
        if (mb_strlen(trim($reason)) < 5) {
            throw new DomainError('marketplace_reason_required', 'Give the customer and the partner a reason.', 422, ['field' => 'reason']);
        }

        return match ($decision) {
            'refund' => $this->refund($order, 'Spor rozhodnut ve prospěch zákazníka: '.trim($reason), $context),
            'deliver' => $this->settleAccepted($order, $context, 'support', trim($reason)),
            default => throw new DomainError('marketplace_decision_invalid', 'Decision must be refund or deliver.', 422, ['field' => 'decision']),
        };
    }

    /** Deliveries nobody answered within the window count as accepted (the partner is paid). */
    public function autoAccept(?int $days = null): int
    {
        $days ??= $this->autoAcceptDays();
        $count = 0;
        foreach (MarketplaceOrder::query()->where('state', MarketplaceOrder::DELIVERED)->where('delivered_at', '<=', now()->subDays($days))->get() as $order) {
            $this->settleAccepted($order, CommandContext::system('marketplace.auto-accept'), 'auto');
            $count++;
        }

        return $count;
    }

    public function autoAcceptDays(): int
    {
        return max(1, (int) config('onhost.marketplace.auto_accept_days', 14));
    }

    // ── presentation ──────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    public function presentListing(MarketplaceListing $listing, bool $internal = false): array
    {
        $partner = Partner::query()->find($listing->partner_id);
        $out = [
            'id' => $listing->id, 'key' => $listing->key, 'title' => $listing->title, 'description' => $listing->description, 'category' => $listing->category,
            'price' => $this->price($listing), 'billing' => $listing->billing, 'delivery_days' => $listing->delivery_days, 'state' => $listing->state,
            'partner' => $partner === null ? null : ['id' => $partner->id, 'name' => Organization::query()->whereKey($partner->organization_id)->value('name'), 'tier' => $partner->tier],
            'checklist' => $this->checklistFor($listing), 'published_at' => data_get($listing->meta, 'published_at'), 'orders' => (int) MarketplaceOrder::query()->where('listing_id', $listing->id)->whereIn('state', [MarketplaceOrder::ACCEPTED])->count(),
        ];
        if ($internal) {
            $out += ['commission_pct' => $listing->commission_pct, 'partner_organization_id' => $partner?->organization_id, 'state_reason' => data_get($listing->meta, 'state_reason'), 'created_at' => $listing->created_at?->toIso8601String()];
        }

        return $out;
    }

    /** @return array<string,mixed> */
    public function presentOrder(MarketplaceOrder $order, bool $internal = false): array
    {
        $listing = MarketplaceListing::query()->find($order->listing_id);
        $out = [
            'id' => $order->id, 'listing' => $listing === null ? null : ['id' => $listing->id, 'key' => $listing->key, 'title' => $listing->title, 'category' => $listing->category],
            'state' => $order->state, 'brief' => $order->brief, 'delivery_note' => $order->delivery_note, 'dispute_reason' => $order->dispute_reason, 'service_id' => $order->service_id,
            'price' => Money::minor((int) $order->price_minor, $order->currency), 'invoice_id' => $order->invoice_id,
            'due_at' => $order->due_at?->toIso8601String(), 'delivered_at' => $order->delivered_at?->toIso8601String(), 'accepted_at' => $order->accepted_at?->toIso8601String(), 'ordered_at' => $order->created_at?->toIso8601String(),
            'auto_accept_at' => $order->state === MarketplaceOrder::DELIVERED && $order->delivered_at ? $order->delivered_at->copy()->addDays($this->autoAcceptDays())->toIso8601String() : null,
            'subscription' => $this->subscriptionInfo($order),
            'sla' => $this->sla($order),
            'checklist' => $listing !== null ? $this->checklistFor($listing) : [], 'period_evidence' => array_values(array_map(fn ($e) => self::presentEvidence((array) $e), array_slice((array) ($order->period_evidence ?? []), -3))), // §5o-2: what the last periods delivered
            'period_uploads' => array_values(array_map(fn ($u) => array_diff_key((array) $u, ['path' => true, 'scan' => true]) + ['scan' => data_get($u, 'scan.result')], (array) ($order->period_uploads ?? []))), // §5t-5: the scan state, never the signature detail // §5p-3: files waiting for the report
        ];
        if ($internal) {
            $out += ['organization_id' => $order->organization_id, 'partner_id' => $order->partner_id, 'commission' => Money::minor((int) $order->commission_minor, $order->currency), 'partner_share' => Money::minor((int) $order->partner_minor, $order->currency)];
        }

        return $out;
    }

    /** Evidence entries never expose storage paths; a file item becomes {file, size, mime}. @param array<string,mixed> $entry @return array<string,mixed> */
    public static function presentEvidence(array $entry): array
    {
        $items = [];
        foreach ((array) ($entry['items'] ?? []) as $key => $value) {
            $items[$key] = is_array($value) ? ['file' => (string) ($value['name'] ?? 'file'), 'size' => (int) ($value['size'] ?? 0), 'mime' => (string) ($value['mime'] ?? ''), 'scan' => data_get($value, 'scan.result')] : $value; // §5t-5
        }

        return ['period_end' => $entry['period_end'] ?? null, 'at' => $entry['at'] ?? null, 'note' => $entry['note'] ?? null, 'items' => $items];
    }

    // ── delivery SLA (§5l-2) ──────────────────────────────────────────────────

    /** @return array{overdue:bool, days_overdue:int, refund_available:bool, grace_days:int} */
    public function sla(MarketplaceOrder $order): array
    {
        $open = in_array($order->state, [MarketplaceOrder::ORDERED, MarketplaceOrder::IN_PROGRESS], true);
        $overdue = $open && $order->due_at !== null && $order->due_at < now();

        return ['overdue' => $overdue, 'days_overdue' => $overdue ? (int) $order->due_at->diffInDays(now()) : 0, 'refund_available' => $open && $order->refund_offered_at !== null, 'grace_days' => $this->overdueGraceDays(), 'days_late' => $this->daysLate($order), 'late_credit' => Money::minor((int) $order->late_credit_minor, $order->currency), 'late_credit_preview' => $order->state === MarketplaceOrder::DELIVERED ? $this->lateCredit($order) : null, 'period' => $this->periodInfo($order)]; // §5n: the customer sees the credit before accepting; a running listing shows its period
    }

    /** Days between the due date and the delivery (0 when on time or not delivered). */
    public function daysLate(MarketplaceOrder $order): int
    {
        if ($order->due_at === null || $order->delivered_at === null || $order->delivered_at <= $order->due_at) {
            return 0;
        }

        return max(1, (int) floor($order->due_at->diffInDays($order->delivered_at)));
    }

    /** The automatic credit for a late delivery (§5m-2): `late_credit_pct_per_day` of the net price per day late, capped at `late_credit_cap_pct`. */
    public function lateCredit(MarketplaceOrder $order): Money
    {
        $days = $this->daysLate($order);
        $perDay = max(0.0, (float) config('onhost.marketplace.late_credit_pct_per_day', 5));
        $cap = max(0.0, min(100.0, (float) config('onhost.marketplace.late_credit_cap_pct', 50)));
        if ($days === 0 || $perDay <= 0) {
            return Money::zero($order->currency);
        }

        return Money::minor((int) $order->price_minor, $order->currency)->percent((string) min($cap, $days * $perDay));
    }

    public function overdueGraceDays(): int
    {
        return max(1, (int) config('onhost.marketplace.overdue_grace_days', 7));
    }

    /**
     * Deliveries past their due date: the partner (and the customer) hear it once; after the grace period the customer
     * is offered a refund they can take without opening a dispute.
     *
     * @return array{warned:int, offered:int}
     */
    public function sweepOverdue(?CarbonInterface $now = null): array
    {
        $now ??= now();
        $stats = ['warned' => 0, 'offered' => 0];
        $grace = $this->overdueGraceDays();
        $orders = MarketplaceOrder::query()->whereIn('state', [MarketplaceOrder::ORDERED, MarketplaceOrder::IN_PROGRESS])->whereNotNull('due_at')->where('due_at', '<', $now)->get();
        foreach ($orders as $order) {
            $listing = MarketplaceListing::query()->find($order->listing_id);
            $partnerOrg = Partner::query()->find($order->partner_id)?->organization_id;
            $days = (int) $order->due_at->diffInDays($now);
            if ($order->overdue_notified_at === null) {
                $order->forceFill(['overdue_notified_at' => $now])->save();
                $this->outbox->publish(GenericEvent::of('marketplace.overdue', 'marketplace_order', $order->id, ['title' => $listing?->title, 'due_at' => $order->due_at->toIso8601String(), 'grace_days' => $grace, 'customer_organization_id' => $order->organization_id], $partnerOrg));
                $this->outbox->publish(GenericEvent::of('marketplace.delayed', 'marketplace_order', $order->id, ['title' => $listing?->title, 'due_at' => $order->due_at->toIso8601String(), 'grace_days' => $grace], $order->organization_id));
                $stats['warned']++;
            }
            if ($order->refund_offered_at === null && $days >= $grace) {
                $order->forceFill(['refund_offered_at' => $now])->save();
                $this->audit->record(CommandContext::system('marketplace.sla')->withScope($order->organization_id), 'marketplace.refund_offered', 'succeeded', ['order' => $order->id, 'days_overdue' => $days], 'marketplace_order', $order->id);
                $this->outbox->publish(GenericEvent::of('marketplace.refund_offered', 'marketplace_order', $order->id, ['title' => $listing?->title, 'days_overdue' => $days, 'partner_organization_id' => $partnerOrg], $order->organization_id));
                $stats['offered']++;
            }
        }

        return $stats;
    }

    /** Reminds a partner whose running period is mostly gone without a deliverable, once per period (§5n-2). @return array{warned:int} */
    public function sweepPeriods(?CarbonInterface $now = null): array
    {
        $now ??= now();
        $stats = ['warned' => 0];
        foreach (MarketplaceOrder::query()->whereNotNull('subscription_id')->where('state', MarketplaceOrder::ACCEPTED)->get() as $running) {
            $info = $this->periodInfo($running);
            if ($info === null || $info['served'] || $now < now()->parse($info['warn_at']) || ($running->period_warned_at !== null && $running->period_warned_at >= now()->parse($info['start']))) {
                continue;
            }
            $running->forceFill(['period_warned_at' => $now])->save();
            $this->outbox->publish(GenericEvent::of('marketplace.period_due', 'marketplace_order', $running->id, ['title' => MarketplaceListing::query()->find($running->listing_id)?->title, 'period_end' => $info['end'], 'credit_pct' => (float) config('onhost.marketplace.subscription_missed_credit_pct', 50), 'customer_organization_id' => $running->organization_id], Partner::query()->find($running->partner_id)?->organization_id));
            $stats['warned']++;
        }

        return $stats;
    }

    // ── monthly listings (§5k-2) ──────────────────────────────────────────────

    /** @return array{id:string, state:string, period_end:?string, cancel_at_period_end:bool, renewal_failures:int}|null */
    public function subscriptionInfo(MarketplaceOrder $order): ?array
    {
        if ($order->subscription_id === null) {
            return null;
        }
        $subscription = Subscription::query()->find($order->subscription_id);

        return $subscription === null ? null : ['id' => $subscription->id, 'state' => $subscription->state, 'period_end' => $subscription->current_period_end?->toIso8601String(), 'cancel_at_period_end' => (bool) $subscription->cancel_at_period_end, 'renewal_failures' => (int) $subscription->renewal_failures];
    }

    /**
     * Renews the monthly listings that are due: the credit is charged (net + VAT), a paid statement is issued, the partner's
     * share of the period becomes a payable commission row; a listing the customer ended stops at its period end; without
     * credit the subscription goes past due and is retried daily until the grace period ends, then the listing ends.
     *
     * @return array{renewed:int, failed:int, ended:int}
     */
    public function renewDue(?CarbonInterface $now = null): array
    {
        $now ??= now();
        $stats = ['renewed' => 0, 'failed' => 0, 'ended' => 0];
        $ctx = CommandContext::system('marketplace.renew');
        $orders = MarketplaceOrder::query()->whereNotNull('subscription_id')->whereIn('state', [MarketplaceOrder::ACCEPTED, MarketplaceOrder::DELIVERED, MarketplaceOrder::IN_PROGRESS, MarketplaceOrder::DISPUTED])->get();
        foreach ($orders as $order) {
            $subscription = Subscription::query()->find($order->subscription_id);
            if ($subscription === null || ! in_array($subscription->state, [Subscription::ACTIVE, Subscription::PAST_DUE], true) || $subscription->next_renewal_at > $now) {
                continue;
            }
            if ($subscription->cancel_at_period_end && $subscription->current_period_end <= $now) {
                $this->endSubscription($order, $subscription, 'ended_by_customer', $ctx);
                $stats['ended']++;

                continue;
            }
            $organization = Organization::query()->find($order->organization_id);
            $listing = MarketplaceListing::query()->find($order->listing_id);
            if ($organization === null || $listing === null) {
                $this->endSubscription($order, $subscription, 'organization_missing', $ctx);
                $stats['ended']++;

                continue;
            }
            $net = Money::minor((int) $subscription->amount_minor, $subscription->currency);
            $decision = $this->tax->calculate(['country' => $organization->country, 'customer_class' => $organization->customer_class, 'vat_status' => $organization->vat_status], [['key' => 'mkt', 'net' => $net, 'product_class' => 'service']], $net->currency, $organization->id);
            $line = $decision['lines'][0];
            $gross = Money::minor((int) $net->minor + (int) $line['tax']->minor, $net->currency);
            $periodKey = $subscription->current_period_end->format('Ymd');
            $scoped = $ctx->withScope($organization->id);
            try {
                $this->wallets->charge($organization, $gross, 'marketplace', "marketplace:renew:{$order->id}:{$periodKey}", $scoped, 'marketplace_order', $order->id, Money::minor((int) $line['tax']->minor, $net->currency));
            } catch (DomainError $e) {
                if ($e->error !== 'insufficient_funds') {
                    throw $e;
                }
                $grace = $subscription->current_period_end->copy()->addDays(max(1, (int) config('onhost.billing.dunning.grace_days', 14)));
                if ($now >= $grace) {
                    $this->endSubscription($order, $subscription, 'unpaid', $ctx);
                    $stats['ended']++;

                    continue;
                }
                $subscription->forceFill(['state' => Subscription::PAST_DUE, 'renewal_failures' => $subscription->renewal_failures + 1, 'next_renewal_at' => $now->copy()->addDay()])->save();
                $this->outbox->publish(GenericEvent::of('marketplace.renewal_failed', 'marketplace_order', $order->id, ['title' => $listing->title, 'required' => $gross, 'grace_until' => $grace->toIso8601String()], $organization->id));
                $stats['failed']++;

                continue;
            }
            $start = $subscription->current_period_end->copy();
            $end = BillingPeriod::end($start, 'month', 1, $subscription->created_at?->day);
            $draft = $this->invoices->draft($organization, 'statement', $net->currency->value, [[
                'sku' => 'mkt-'.$listing->key.'-renewal', 'description' => 'Marketplace: '.$listing->title.' (měsíční)', 'qty' => 1, 'unit' => 'ks',
                'unit_net' => $net->minor, 'discount' => 0, 'net' => $net->minor, 'tax_rate' => (string) $line['rate'], 'tax_category' => (string) $line['category'], 'tax' => (int) $line['tax']->minor, 'total' => $gross->minor,
                'period_from' => $start->toDateString(), 'period_to' => $end->toDateString(), 'service_id' => $order->service_id,
            ]], $scoped, null, ['payment_method' => 'wallet', 'marketplace_order_id' => $order->id, 'subscription_id' => $subscription->id, 'tax_calculation_id' => $decision['calculation']->id]);
            $invoice = $this->invoices->issue($draft, $scoped, dueDays: 0);
            $this->invoices->markPaid($invoice, $gross, 'wallet', $scoped, postLedger: false);
            $missedCredit = $this->missedPeriodCredit($order, $subscription); // §5n-2: the period that just ended had no deliverable → the customer is credited, the partner's next share carries it
            $subscription->forceFill(['state' => Subscription::ACTIVE, 'current_period_start' => $start, 'current_period_end' => $end, 'next_renewal_at' => $end, 'renewal_failures' => 0, 'last_renewed_at' => $now])->save();
            $partner = Partner::query()->find($order->partner_id);
            if ($missedCredit->isPositive()) {
                $this->wallets->topup($order->organization_id, $missedCredit, 'marketplace', "marketplace:missed:{$order->id}:{$periodKey}", $scoped, null, 'Kredit za chybějící měsíční plnění', false, null, 'credit');
                $order->forceFill(['missed_periods' => (int) $order->missed_periods + 1, 'late_credit_minor' => (int) $order->late_credit_minor + $missedCredit->minor])->save();
                $this->audit->record($scoped, 'marketplace.period.missed', 'succeeded', ['order' => $order->id, 'credit' => $missedCredit, 'period_end' => $start->toIso8601String()], 'marketplace_order', $order->id);
                $this->outbox->publish(GenericEvent::of('marketplace.period_missed', 'marketplace_order', $order->id, ['title' => $listing->title, 'credit' => $missedCredit, 'period_end' => $start->toIso8601String(), 'missed_periods' => (int) $order->missed_periods], $organization->id));
                if ($partner !== null) {
                    $this->outbox->publish(GenericEvent::of('marketplace.period_missed_partner', 'marketplace_order', $order->id, ['title' => $listing->title, 'credit' => $missedCredit, 'period_end' => $start->toIso8601String(), 'customer_organization_id' => $organization->id], $partner->organization_id));
                }
            }
            $order->forceFill(['period_delivered_at' => null, 'period_warned_at' => null])->save(); // the new period starts unserved
            if ($partner !== null && $order->partner_minor > 0) {
                PartnerCommission::query()->create([
                    'partner_id' => $partner->id, 'organization_id' => $order->organization_id, 'invoice_id' => $invoice->id, 'period' => $now->format('Y-m'), 'kind' => 'marketplace',
                    'base_minor' => $order->price_minor, 'rate_pct' => max(0, 100 - $this->commissionPct($order)), 'amount_minor' => max(0, (int) $order->partner_minor - $missedCredit->minor), 'currency' => $order->currency, 'state' => 'payable', 'invoice_paid_at' => $now,
                ]);
            }
            $this->audit->record($scoped, 'marketplace.renew', 'succeeded', ['order' => $order->id, 'invoice' => $invoice->number, 'total' => $gross, 'period_end' => $end->toIso8601String()], 'marketplace_order', $order->id);
            $this->outbox->publish(GenericEvent::of('marketplace.renewed', 'marketplace_order', $order->id, ['title' => $listing->title, 'total' => $gross, 'period_end' => $end->toIso8601String(), 'invoice' => $invoice->number, 'partner_organization_id' => $partner?->organization_id], $organization->id));
            $stats['renewed']++;
        }

        return $stats;
    }

    /**
     * A file behind a checklist item (§5p-3): the partner uploads it before the period report; the controller stored the
     * upload under a temporary name, the service moves it next to the order and remembers it until the report consumes it.
     *
     * @return array{key:string, name:string, size:int, mime:string, at:string}
     */
    public function attachEvidence(MarketplaceOrder $order, Partner $partner, string $key, string $tmpPath, string $name, string $mime, int $size, CommandContext $context): array
    {
        $this->assertPartnerOrder($partner, $order);
        $listing = MarketplaceListing::query()->find($order->listing_id);
        $item = collect($listing !== null ? $this->checklistFor($listing) : [])->firstWhere('key', $key);
        $disk = app(FileStore::class)->disk(); // local or S3 (audit §5q-4)
        if ($item === null || $item['kind'] !== 'file') {
            $disk->delete($tmpPath); // nothing keeps a stray upload
            throw new DomainError('marketplace_checklist_item_invalid', 'No file item with that key on the listing checklist.', 422, ['field' => 'key']);
        }
        foreach ($disk->files('marketplace-evidence/tmp') as $stale) { // uploads nobody attached within a day
            if ($stale !== $tmpPath && $disk->lastModified($stale) < now()->subDay()->getTimestamp()) {
                $disk->delete($stale);
            }
        }
        if (! $disk->exists($tmpPath)) {
            throw new DomainError('marketplace_upload_missing', 'The uploaded file is gone; upload it again.', 422, ['field' => 'file']);
        }
        $scanner = app(VirusScanner::class); // §5r-4: nothing infected reaches the customer
        $scan = $scanner->scanPath($tmpPath);
        if ($scan['result'] === VirusScanner::INFECTED) {
            $disk->delete($tmpPath);
            UploadGuard::refused($scan, 'marketplace_order', $order->id, $name, $context->withScope($partner->organization_id));
        }
        $safeName = mb_substr(preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?: 'file', 0, 80);
        $target = "marketplace-evidence/{$order->id}/".now()->format('Ym')."/{$key}-".Str::lower(Str::random(8)).'-'.$safeName;
        $previous = data_get($order->period_uploads, "{$key}.path");
        $disk->move($tmpPath, $target);
        if (is_string($previous) && $previous !== '' && $disk->exists($previous)) {
            $disk->delete($previous); // one file per item and period
        }
        $entry = ['key' => $key, 'path' => $target, 'name' => $safeName, 'size' => $size, 'mime' => $mime, 'at' => now()->toIso8601String(), 'scan' => $scan];
        $order->forceFill(['period_uploads' => array_merge((array) ($order->period_uploads ?? []), [$key => $entry])])->save();
        $this->audit->record($context->withScope($partner->organization_id), 'marketplace.evidence.upload', 'succeeded', ['order' => $order->id, 'key' => $key, 'name' => $safeName, 'size' => $size], 'marketplace_order', $order->id);

        return array_diff_key($entry, ['path' => true]); // carries `scan` so the portal can say what the antivirus found (§5t-5)
    }

    /**
     * The retry pass of the virus scan (audit §5r-4, `onhost:files:scan`): every evidence file whose scan is missing or
     * was `unavailable` is scanned again; an infected one is deleted from the store and reported.
     *
     * @return array{scanned:int, clean:int, infected:int, unavailable:int}
     */
    public function rescanEvidence(): array
    {
        $scanner = app(VirusScanner::class);
        $stats = ['scanned' => 0, 'clean' => 0, 'infected' => 0, 'unavailable' => 0];
        if (! $scanner->enabled()) {
            return $stats;
        }
        $disk = app(FileStore::class)->disk();
        $visit = function (array $file) use ($scanner, $disk, &$stats): array {
            if (empty($file['path']) || in_array(data_get($file, 'scan.result'), [VirusScanner::CLEAN, VirusScanner::INFECTED], true)) {
                return $file;
            }
            $stats['scanned']++;
            $file['scan'] = $scanner->scanPath((string) $file['path']);
            $stats[$file['scan']['result'] === VirusScanner::OFF ? 'unavailable' : $file['scan']['result']]++;
            if ($file['scan']['result'] === VirusScanner::INFECTED) {
                $disk->delete((string) $file['path']);
            }

            return $file;
        };
        MarketplaceOrder::query()->where(fn ($q) => $q->whereNotNull('period_uploads')->orWhereNotNull('period_evidence'))->orderBy('id')->chunk(100, function ($orders) use ($visit, &$stats) {
            foreach ($orders as $order) {
                $before = $stats['infected'];
                $uploads = array_map(fn ($f) => is_array($f) ? $visit($f) : $f, (array) ($order->period_uploads ?? []));
                $history = array_map(function ($period) use ($visit) {
                    if (is_array($period) && is_array($period['items'] ?? null)) {
                        $period['items'] = array_map(fn ($f) => is_array($f) && isset($f['path']) ? $visit($f) : $f, $period['items']);
                    }

                    return $period;
                }, (array) ($order->period_evidence ?? []));
                $order->forceFill(['period_uploads' => $uploads ?: null, 'period_evidence' => $history ?: null])->save();
                if ($stats['infected'] > $before) {
                    $this->outbox->publish(GenericEvent::of('files.infected', 'marketplace_order', $order->id, ['name' => 'evidence', 'subject' => 'marketplace_order', 'malware' => null, 'actor' => 'files.scan'], $order->organization_id));
                }
            }
        });

        return $stats;
    }

    /** The customer (or the partner) reads a file the report carried (§5p-3). @return array{path:string, name:string, mime:string} */
    public function evidenceFile(MarketplaceOrder $order, int $entry, string $key): array
    {
        $history = array_values((array) ($order->period_evidence ?? []));
        $file = data_get($history, "{$entry}.items.{$key}");
        if (! is_array($file) || empty($file['path']) || ! app(FileStore::class)->disk()->exists((string) $file['path'])) {
            throw DomainError::notFound('evidence_file');
        }
        $scan = (string) data_get($file, 'scan.result', '');
        if (! app(VirusScanner::class)->allows($scan !== '' ? $scan : null)) { // §5r-4: an unscanned file waits for the retry pass, an infected one never leaves
            throw new DomainError($scan === VirusScanner::INFECTED ? 'evidence_file_infected' : 'evidence_file_not_scanned', $scan === VirusScanner::INFECTED ? 'The file was found infected and removed.' : 'The file is waiting for its virus scan; try again shortly.', $scan === VirusScanner::INFECTED ? 410 : 409, ['scan' => $scan ?: null]);
        }

        return ['path' => (string) $file['path'], 'name' => (string) ($file['name'] ?? 'file'), 'mime' => (string) ($file['mime'] ?? 'application/octet-stream')];
    }

    /** Whether the running period of a monthly listing was served: the period's report, or the first delivery accepted inside it. */
    public function periodServed(MarketplaceOrder $order, Subscription $subscription): bool
    {
        $start = $subscription->current_period_start;

        return ($order->period_delivered_at !== null && $order->period_delivered_at >= $start) || ($order->accepted_at !== null && $order->accepted_at >= $start) || ($order->delivered_at !== null && $order->delivered_at >= $start);
    }

    /** The credit for a period that ended without a deliverable (§5n-2): `subscription_missed_credit_pct` of the period's net price. */
    public function missedPeriodCredit(MarketplaceOrder $order, Subscription $subscription): Money
    {
        $pct = max(0.0, min(100.0, (float) config('onhost.marketplace.subscription_missed_credit_pct', 50)));
        if ($pct <= 0 || $this->periodServed($order, $subscription)) {
            return Money::zero($order->currency);
        }

        return Money::minor((int) $subscription->amount_minor, $subscription->currency)->percent((string) $pct);
    }

    /** @return array{start:?string, end:?string, served:bool, delivered_at:?string, missed_periods:int, warn_at:?string}|null */
    public function periodInfo(MarketplaceOrder $order): ?array
    {
        if ($order->subscription_id === null) {
            return null;
        }
        $subscription = Subscription::query()->find($order->subscription_id);
        if ($subscription === null || $subscription->current_period_start === null || $subscription->current_period_end === null) {
            return null;
        }
        $span = max(1, (int) $subscription->current_period_start->diffInSeconds($subscription->current_period_end, true));
        $warnAt = $subscription->current_period_start->copy()->addSeconds((int) round($span * max(0.0, min(100.0, (float) config('onhost.marketplace.period_warn_pct', 80))) / 100));

        return ['start' => $subscription->current_period_start->toIso8601String(), 'end' => $subscription->current_period_end->toIso8601String(), 'served' => $this->periodServed($order, $subscription), 'delivered_at' => $order->period_delivered_at?->toIso8601String(), 'missed_periods' => (int) $order->missed_periods, 'warn_at' => $warnAt->toIso8601String()];
    }

    private function endSubscription(MarketplaceOrder $order, Subscription $subscription, string $reason, CommandContext $context): void
    {
        $subscription->forceFill(['state' => Subscription::CANCELLED, 'auto_renew' => false])->save();
        $order->forceFill(['state' => MarketplaceOrder::ENDED])->save();
        $listing = MarketplaceListing::query()->find($order->listing_id);
        $this->audit->record($context->withScope($order->organization_id), 'marketplace.subscription.end', 'succeeded', ['order' => $order->id, 'reason' => $reason], 'marketplace_order', $order->id);
        $this->outbox->publish(GenericEvent::of('marketplace.ended', 'marketplace_order', $order->id, ['title' => $listing?->title, 'reason' => $reason, 'partner_organization_id' => Partner::query()->find($order->partner_id)?->organization_id], $order->organization_id));
    }

    // ── internals ─────────────────────────────────────────────────────────────

    private function settleAccepted(MarketplaceOrder $order, CommandContext $context, string $by, ?string $reason = null): MarketplaceOrder
    {
        if (! in_array($order->state, [MarketplaceOrder::DELIVERED, MarketplaceOrder::DISPUTED], true)) {
            throw new DomainError('marketplace_not_delivered', 'Only a delivered order can be accepted.', 409, ['state' => $order->state]);
        }

        return DB::transaction(function () use ($order, $context, $by, $reason) {
            $lateCredit = $this->lateCredit($order); // §5m-2: a late delivery accepted late carries an automatic partial credit; the partner bears it
            $order->forceFill(['state' => MarketplaceOrder::ACCEPTED, 'accepted_at' => now(), 'late_credit_minor' => $lateCredit->minor, 'partner_minor' => max(0, (int) $order->partner_minor - $lateCredit->minor)])->save();
            if ($lateCredit->isPositive()) {
                $this->wallets->topup($order->organization_id, $lateCredit, 'marketplace', "marketplace:late:{$order->id}", $context->withScope($order->organization_id), null, 'Kredit za pozdní dodání', false, null, 'credit');
                $this->outbox->publish(GenericEvent::of('marketplace.late_credit', 'marketplace_order', $order->id, ['title' => MarketplaceListing::query()->find($order->listing_id)?->title, 'credit' => $lateCredit, 'days_late' => $this->daysLate($order), 'partner_organization_id' => Partner::query()->find($order->partner_id)?->organization_id], $order->organization_id));
            }
            $partner = Partner::query()->find($order->partner_id);
            if ($partner !== null && $order->partner_minor > 0 && ! PartnerCommission::query()->where('kind', 'marketplace')->where('invoice_id', $order->invoice_id)->exists()) {
                PartnerCommission::query()->create([
                    'partner_id' => $partner->id, 'organization_id' => $order->organization_id, 'invoice_id' => $order->invoice_id, 'period' => now()->format('Y-m'), 'kind' => 'marketplace',
                    'base_minor' => $order->price_minor, 'rate_pct' => max(0, 100 - $this->commissionPct($order)), 'amount_minor' => $order->partner_minor, 'currency' => $order->currency, 'state' => 'payable', 'invoice_paid_at' => now(),
                ]);
            }
            $this->audit->record($context->withScope($order->organization_id), 'marketplace.accept', 'succeeded', ['order' => $order->id, 'by' => $by, 'reason' => $reason, 'partner_share' => Money::minor((int) $order->partner_minor, $order->currency)], 'marketplace_order', $order->id);
            $listing = MarketplaceListing::query()->find($order->listing_id);
            $this->outbox->publish(GenericEvent::of('marketplace.accepted', 'marketplace_order', $order->id, ['title' => $listing?->title, 'by' => $by, 'reason' => $reason, 'partner_share' => Money::minor((int) $order->partner_minor, $order->currency), 'customer_organization_id' => $order->organization_id], $partner?->organization_id));

            return $order;
        }, 3);
    }

    private function refund(MarketplaceOrder $order, string $reason, CommandContext $context): MarketplaceOrder
    {
        return DB::transaction(function () use ($order, $reason, $context) {
            $invoice = $order->invoice_id ? Invoice::query()->find($order->invoice_id) : null;
            $gross = $invoice !== null ? $invoice->total() : Money::minor((int) $order->price_minor, $order->currency);
            $ctx = $context->withScope($order->organization_id);
            // the credit note and the money together, against the revenue and the VAT the order had earned — it used to be booked as a
            // top-up from a bank called "marketplace": purchased credit that could be paid out in cash whatever the order was paid with
            if ($invoice !== null && $invoice->isCreditable() && (int) $invoice->credited_minor < (int) $invoice->total_minor) {
                $given = $this->invoices->giveBack($invoice, null, $reason, $ctx);
                $gross = Money::minor($given['to_credit_minor'] + $given['off_document_minor'], $order->currency);
            } elseif ($invoice === null) {
                $this->wallets->returnToCredit($order->organization_id, $gross, WalletService::revenueReturn($gross, Money::zero($gross->currency)), "marketplace:refund:{$order->id}", $ctx, 'marketplace_order', $order->id, $reason);
            }
            $order->forceFill(['state' => MarketplaceOrder::CANCELLED, 'dispute_reason' => $order->dispute_reason ?? $reason])->save();
            if ($order->subscription_id !== null) {
                Subscription::query()->whereKey($order->subscription_id)->update(['state' => Subscription::CANCELLED, 'auto_renew' => false]);
            }
            $this->audit->record($ctx, 'marketplace.refund', 'succeeded', ['order' => $order->id, 'amount' => $gross, 'reason' => $reason], 'marketplace_order', $order->id);
            $listing = MarketplaceListing::query()->find($order->listing_id);
            $this->outbox->publish(GenericEvent::of('marketplace.refunded', 'marketplace_order', $order->id, ['title' => $listing?->title, 'amount' => $gross, 'reason' => $reason, 'partner_organization_id' => Partner::query()->find($order->partner_id)?->organization_id], $order->organization_id));

            return $order;
        }, 3);
    }

    /** @param  list<string>  $from */
    private function transition(MarketplaceOrder $order, array $from, string $to, CommandContext $context, ?string $scope): void
    {
        if (! in_array($order->state, $from, true)) {
            throw new DomainError('marketplace_transition_invalid', "An order in state {$order->state} cannot become {$to}.", 409, ['from' => $order->state, 'to' => $to]);
        }
        $was = $order->state;
        $order->forceFill(['state' => $to])->save();
        $this->audit->record($context->withScope($scope), 'marketplace.order.'.$to, 'succeeded', ['order' => $order->id, 'from' => $was], 'marketplace_order', $order->id);
    }

    private function commissionPct(MarketplaceOrder $order): int
    {
        return $order->price_minor > 0 ? (int) round($order->commission_minor * 100 / $order->price_minor) : 0;
    }

    private function price(MarketplaceListing $listing): Money
    {
        return Money::minor((int) $listing->price_minor, $listing->currency);
    }

    /** @param  array<string,mixed>  $input @return array<string,mixed> */
    private function listingData(array $input, ?MarketplaceListing $existing): array
    {
        $key = strtolower(trim((string) ($input['key'] ?? $existing?->key ?? '')));
        if (! preg_match('/^[a-z0-9][a-z0-9-]{2,58}$/', $key)) {
            throw new DomainError('marketplace_key_invalid', 'The key is 3–60 lowercase letters, digits and dashes.', 422, ['field' => 'key']);
        }
        $title = trim((string) ($input['title'] ?? $existing?->title ?? ''));
        if (mb_strlen($title) < 3 || mb_strlen($title) > 120) {
            throw new DomainError('marketplace_title_invalid', 'The title is 3–120 characters.', 422, ['field' => 'title']);
        }
        $category = (string) ($input['category'] ?? $existing?->category ?? 'care');
        if (! in_array($category, self::CATEGORIES, true)) {
            throw new DomainError('marketplace_category_invalid', 'Unknown category.', 422, ['field' => 'category', 'allowed' => self::CATEGORIES]);
        }
        $price = isset($input['price_minor']) ? (int) $input['price_minor'] : (int) ($existing?->price_minor ?? 0);
        if ($price < 10000 || $price > 100000000) {
            throw new DomainError('marketplace_price_invalid', 'The price is between 100 and 1 000 000 in the partner currency.', 422, ['field' => 'price_minor']);
        }
        $billing = (string) ($input['billing'] ?? $existing?->billing ?? 'oneoff');
        if (! in_array($billing, MarketplaceListing::BILLING, true)) {
            throw new DomainError('marketplace_billing_invalid', 'Billing is oneoff or monthly.', 422, ['field' => 'billing']);
        }
        $days = max(1, min(90, (int) ($input['delivery_days'] ?? $existing?->delivery_days ?? 5)));
        $commission = isset($input['commission_pct']) && $existing === null ? null : null; // partners never set the platform's share
        unset($commission);

        return [
            'key' => $key, 'title' => $title, 'description' => isset($input['description']) ? mb_substr(trim((string) $input['description']), 0, 4000) : ($existing?->description),
            'category' => $category, 'price_minor' => $price, 'billing' => $billing, 'delivery_days' => $days,
            'commission_pct' => $existing?->commission_pct ?? max(0, min(90, (int) config('onhost.marketplace.commission_pct', 20))),
            'meta' => array_merge((array) ($existing?->meta ?? []), array_key_exists('checklist', $input) ? ['checklist' => $this->normalizeChecklist((array) $input['checklist'])] : []), // §5o-2
        ];
    }

    /**
     * The evidence a monthly deliverable must carry (§5o-2): up to ten items, `check` (a tick) or `text` (a value such as an
     * uptime figure); the partner ticks them when reporting the period.
     *
     * @return list<array{key:string, cs:string, en:string, kind:string}>
     */
    public function normalizeChecklist(array $items): array
    {
        $out = [];
        foreach (array_slice(array_values($items), 0, 10) as $item) {
            $item = is_array($item) ? $item : ['key' => (string) $item];
            $key = strtolower(trim((string) ($item['key'] ?? '')));
            if (! preg_match('/^[a-z0-9][a-z0-9_-]{1,30}$/', $key)) {
                throw new DomainError('marketplace_checklist_invalid', 'Checklist keys are 2–31 lowercase letters, digits, dashes.', 422, ['field' => 'checklist']);
            }
            $kind = (string) ($item['kind'] ?? 'check');
            if (! in_array($kind, ['check', 'text', 'file'], true)) {
                throw new DomainError('marketplace_checklist_invalid', 'Checklist items are check, text or file.', 422, ['field' => 'checklist']);
            }
            $out[$key] = ['key' => $key, 'cs' => mb_substr(trim((string) ($item['cs'] ?? $item['label'] ?? $key)), 0, 80), 'en' => mb_substr(trim((string) ($item['en'] ?? $item['cs'] ?? $item['label'] ?? $key)), 0, 80), 'kind' => $kind];
        }

        return array_values($out);
    }

    /** @return list<array{key:string, cs:string, en:string, kind:string}> */
    public function checklistFor(MarketplaceListing $listing): array
    {
        return array_values(array_map(fn ($i) => (array) $i, (array) data_get($listing->meta, 'checklist', [])));
    }

    private function assertActivePartner(Partner $partner): void
    {
        if ($partner->state !== 'active') {
            throw new DomainError('partner_not_active', 'Only approved partners list services.', 403, ['state' => $partner->state]);
        }
    }

    private function assertOwner(Partner $partner, MarketplaceListing $listing): void
    {
        if ($listing->partner_id !== $partner->id) {
            throw DomainError::notFound('marketplace_listing');
        }
    }

    private function assertPartnerOrder(Partner $partner, MarketplaceOrder $order): void
    {
        if ($order->partner_id !== $partner->id) {
            throw DomainError::notFound('marketplace_order');
        }
    }

    private function assertCustomerOrder(Organization $organization, MarketplaceOrder $order): void
    {
        if ($order->organization_id !== $organization->id) {
            throw DomainError::notFound('marketplace_order');
        }
    }
}
