<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Dns\DnsService;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\DomainConsent;
use Onhost\Domain\Domains\Models\DomainRenewalJob;
use Onhost\Domain\Domains\Models\DomainTransferSecret;
use Onhost\Domain\Domains\Models\RegistrarContact;
use Onhost\Domain\Domains\Workflows\RegisterDomainWorkflow;
use Onhost\Domain\Domains\Workflows\RenewDomainWorkflow;
use Onhost\Domain\Domains\Workflows\TransferDomainInWorkflow;
use Onhost\Domain\Domains\Workflows\UpdateNameserversWorkflow;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Orders\Models\Consent;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\OrderFulfilmentService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\OperationService;
use Onhost\Domain\Tax\TaxEngine;
use Onhost\Domain\WalletLedger\Models\WalletHold;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Currency;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Support\Hostname;
use Onhost\Providers\Contracts\RegistrarProvider;

/**
 * Domain platform (blueprint §46): search, registration with consent evidence,
 * renewals with domain-priority wallet holds, transfers with step-up, registrar
 * reconciliation and the Critical Domain Policy.
 */
final class DomainService
{
    public function __construct(
        private readonly RegistrarClient $registrar,
        private readonly RegistrarSelector $selector,
        private readonly CatalogService $catalog,
        private readonly DnsService $dns,
        private readonly OperationService $operations,
        private readonly WalletService $wallets,
        private readonly TaxEngine $tax,
        private readonly InvoiceService $invoices,
        private readonly OrderFulfilmentService $fulfilment,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
        private readonly CacheRepository $cache,
    ) {}

    // ── search ───────────────────────────────────────────────────────────────

    /**
     * Availability + price for up to `search_max` names. Results are cached briefly so
     * a debouncing UI never burns the domain-family quota (§45.5).
     *
     * @param  list<string>  $names
     * @return list<array<string,mixed>>
     */
    public function search(array $names, Currency|string $currency, ?Organization $organization = null): array
    {
        $currency = $currency instanceof Currency ? $currency : Currency::fromString($currency);
        $max = (int) config('onhost.domains.search_max', 20);
        $fqdns = [];
        foreach (array_slice(array_values(array_unique(array_map(fn ($n) => strtolower(trim((string) $n)), $names))), 0, $max) as $name) {
            if ($name === '') {
                continue;
            }
            $fqdns[] = $name;
        }
        $out = [];
        $toCheck = [];
        foreach ($fqdns as $raw) {
            $entry = ['input' => $raw, 'fqdn' => null, 'unicode' => null, 'tld' => null, 'available' => null, 'reason' => null, 'price_register' => null, 'price_renew' => null, 'currency' => $currency->value, 'periods' => [1], 'supported' => false];
            try {
                $fqdn = Hostname::canonical($raw);
                if (! Hostname::isRegistrable($fqdn)) {
                    throw new DomainError('domain_invalid', "{$raw} is not a registrable domain name.", 422);
                }
                $tld = Hostname::tld($fqdn);
                $policy = $this->catalog->tld($tld);
                $price = $this->catalog->domainPrice($tld, $currency);
                $entry = array_merge($entry, [
                    'fqdn' => $fqdn, 'unicode' => Hostname::unicode($fqdn), 'tld' => $tld, 'supported' => (bool) $policy->registrable,
                    'price_register' => $price->register()->minor, 'price_renew' => $price->renew()->minor, 'price_transfer' => $price->transfer()->minor,
                    'periods' => (array) ($policy->periods ?: [1]), 'policy' => [
                        'idn' => (bool) $policy->idn, 'dnssec' => (bool) $policy->dnssec_supported, 'nsset_required' => (bool) $policy->nsset_required, 'contact_schema' => $policy->contact_schema, 'transfer_mode' => $policy->transfer_mode,
                        'grace_days' => (int) $policy->grace_days, 'registry_terms_url' => $policy->registry_terms_url, 'registrar_terms_url' => $policy->registrar_terms_url, 'requirements' => (array) data_get($policy->meta, 'requirements', []),
                    ],
                ]);
                $cached = $this->cache->get("onhost:domain:avail:{$fqdn}");
                if (is_array($cached)) {
                    $entry['available'] = $cached['available'];
                    $entry['reason'] = $cached['reason'];
                } elseif ($entry['supported']) {
                    $toCheck[] = $fqdn;
                }
            } catch (DomainError $e) {
                $entry['reason'] = $e->error;
            }
            $out[] = $entry;
        }
        if ($toCheck !== []) {
            $results = $this->checkAvailability($toCheck);
            foreach ($out as &$entry) {
                if ($entry['fqdn'] !== null && isset($results[$entry['fqdn']])) {
                    $entry['available'] = $results[$entry['fqdn']]['available'];
                    $entry['reason'] = $results[$entry['fqdn']]['reason'] ?? null;
                    if ($entry['available'] !== null) {
                        $this->cache->put("onhost:domain:avail:{$entry['fqdn']}", ['available' => $entry['available'], 'reason' => $entry['reason']], (int) config('onhost.domains.search_cache_seconds', 60));
                    }
                }
            }
            unset($entry);
        }

        return $out;
    }

    // ── registration ─────────────────────────────────────────────────────────

    /** Called by order fulfilment for every `domain` order item (idempotent per item). */
    public function createFromOrderItem(OrderItem $item, Order $order, CommandContext $context): Operation
    {
        $config = (array) $item->config;
        $organization = Organization::query()->findOrFail($order->organization_id);
        $request = [
            'period' => (int) ($config['period_years'] ?? 1), 'registrant_contact_id' => $config['registrant_contact_id'] ?? null, 'registrant' => $config['registrant'] ?? null,
            'dns_template' => $config['dns_template'] ?? 'parking', 'dns_vars' => $config['dns_vars'] ?? [], 'nameservers' => $config['nameservers'] ?? null, 'dns_provider' => $config['dns_provider'] ?? 'powerdns',
            'consents' => Consent::query()->where('order_id', $order->id)->whereIn('kind', ['registry_terms', 'registrar_terms'])->get()->all(),
        ];

        return $this->register($organization, (string) ($config['fqdn'] ?? $config['domain'] ?? ''), $request, $context, "order_item:{$item->id}", $item);
    }

    /**
     * @param  array{period?:int, registrant_contact_id?:?string, registrant?:?array, admin_contact_id?:?string, dns_template?:string, dns_vars?:array, nameservers?:?list<string>, dns_provider?:string, consents?:list<Consent>, consent?:?array, test_mode?:bool}  $request
     */
    public function register(Organization $organization, string $fqdn, array $request, CommandContext $context, string $idempotencyKey, ?OrderItem $item = null): Operation
    {
        $fqdn = Hostname::canonical($fqdn);
        if (! Hostname::isRegistrable($fqdn)) {
            throw new DomainError('domain_invalid', "{$fqdn} is not a registrable domain name.", 422);
        }
        $tld = Hostname::tld($fqdn);
        $policy = $this->catalog->tld($tld);
        $period = (int) ($request['period'] ?? 1);
        if (! $policy->allowsPeriod($period)) {
            throw new DomainError('domain_period_not_allowed', "Registration period {$period} is not allowed for .{$tld}.", 422);
        }
        $existingOp = Operation::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existingOp !== null) {
            return $existingOp;
        }

        $testMode = (bool) ($request['test_mode'] ?? config('onhost.wapi.test_mode', false));
        $choice = $this->selector->choose($tld, 'register', $testMode);
        $provider = $choice['provider'];

        $domain = DB::transaction(function () use ($organization, $fqdn, $tld, $period, $request, $context, $item, $provider, $choice) {
            $domain = Domain::query()->where('fqdn_ascii', $fqdn)->first();
            if ($domain !== null && $domain->organization_id !== $organization->id && ! in_array($domain->state, [DomainStateMachine::FAILED, DomainStateMachine::DELETED, DomainStateMachine::TRANSFERRED_OUT], true)) {
                throw new DomainError('domain_taken', "{$fqdn} is already managed by another organization.", 409);
            }
            if ($domain !== null && $domain->isActive()) {
                throw new DomainError('domain_already_registered', "{$fqdn} is already active in this organization.", 409);
            }
            $registrant = $this->resolveContact($organization, $request['registrant_contact_id'] ?? null, $request['registrant'] ?? null, $context, $provider);
            $admin = isset($request['admin_contact_id']) ? $this->resolveContact($organization, $request['admin_contact_id'], null, $context, $provider) : $registrant;
            $attributes = [
                'organization_id' => $organization->id, 'fqdn_ascii' => $fqdn, 'fqdn_unicode' => Hostname::unicode($fqdn), 'tld' => $tld, 'registrar_provider' => $provider,
                'meta' => array_merge((array) ($domain?->meta ?? []), ['registrar_selection' => RegistrarSelector::summary($choice)]),
                'state' => DomainStateMachine::PENDING_REGISTRATION, 'renewal_period' => $period, 'auto_renew' => (bool) ($request['auto_renew'] ?? true),
                'dns_provider' => isset($request['nameservers']) && $request['nameservers'] !== null ? 'external' : (string) ($request['dns_provider'] ?? 'powerdns'),
                'nameservers' => $request['nameservers'] ?? null, 'registrant_contact_id' => $registrant->id, 'admin_contact_id' => $admin->id,
                'transfer_lock' => true, 'critical' => in_array($fqdn, (array) config('onhost.domains.critical', []), true), 'order_item_id' => $item?->id,
            ];
            $domain = $domain === null ? Domain::query()->create($attributes) : tap($domain)->forceFill($attributes)->save();
            $this->recordConsents($domain, $request, $context);
            $item?->forceFill(['domain_id' => $domain->id, 'state' => 'provisioning'])->save();

            return $domain;
        });

        $operation = $this->operations->start(RegisterDomainWorkflow::class, $idempotencyKey, [
            'domain_id' => $domain->id, 'fqdn' => $fqdn, 'tld' => $tld, 'period' => $period, 'dns_template' => (string) ($request['dns_template'] ?? 'parking'), 'dns_vars' => $this->dnsVars((array) ($request['dns_vars'] ?? [])),
            'nameservers' => $request['nameservers'] ?? null, 'test_mode' => $testMode, 'event' => 'domain.registered', 'registrar_provider' => $provider,
        ], $context, null, $organization->id, $item?->id, $choice['instance']->id, $domain->id);
        $this->audit->record($context->withScope($organization->id), 'domain.register', 'succeeded', ['fqdn' => $fqdn, 'period' => $period, 'operation_id' => $operation->id], 'domain', $domain->id);
        $this->outbox->publish(GenericEvent::of('domain.registration_requested', 'domain', $domain->id, ['fqdn' => $fqdn, 'operation_id' => $operation->id, 'order_item_id' => $item?->id], $organization->id));

        return $operation;
    }

    /** Post-registration bookkeeping run by ActivateDomainStep (also for transfers). */
    public function activate(Domain $domain, CommandContext $context, string $event, Operation $operation): void
    {
        $organization = Organization::query()->findOrFail($domain->organization_id);
        $price = $this->catalog->domainPrice($domain->tld, $organization->currency);
        $lead = (int) config('onhost.domains.renew_lead_days', 14);
        $subscription = Subscription::query()->firstOrCreate(['domain_id' => $domain->id], [
            'organization_id' => $domain->organization_id, 'currency' => $organization->currency, 'period' => 'year', 'amount_minor' => $price->renew()->minor, 'state' => Subscription::ACTIVE,
            'current_period_start' => $domain->registered_at ?? now(), 'current_period_end' => $domain->expires_at ?? now()->addYear(), 'next_renewal_at' => ($domain->expires_at ?? now()->addYear())->copy()->subDays($lead),
            'auto_renew' => $domain->auto_renew, 'renewal_priority' => 'domain',
        ]);
        $domain->forceFill(['subscription_id' => $subscription->id])->save();
        if ($operation->order_item_id !== null) {
            $item = OrderItem::query()->find($operation->order_item_id);
            $item?->forceFill(['state' => 'active', 'domain_id' => $domain->id])->save();
            $this->fulfilment->recheck($item?->order_id, $context);
        }
        $this->audit->record($context->withScope($domain->organization_id), $event, 'succeeded', ['fqdn' => $domain->fqdn_ascii, 'expires_at' => $domain->expires_at?->toDateString()], 'domain', $domain->id);
        $this->outbox->publish(GenericEvent::of($event, 'domain', $domain->id, ['fqdn' => $domain->fqdn_ascii, 'expires_at' => $domain->expires_at?->toIso8601String(), 'order_item_id' => $operation->order_item_id, 'subscription_id' => $subscription->id], $domain->organization_id));
    }

    public function fail(Domain $domain, CommandContext $context, string $reason, Operation $operation): void
    {
        if (! in_array($domain->state, [DomainStateMachine::ACTIVE, DomainStateMachine::DELETED], true)) {
            $domain->forceFill(['state' => DomainStateMachine::FAILED, 'meta' => array_merge((array) $domain->meta, ['last_error' => mb_substr($reason, 0, 500)])])->save();
        }
        if ($operation->order_item_id !== null) {
            $item = OrderItem::query()->find($operation->order_item_id);
            $item?->forceFill(['state' => 'failed'])->save();
            $this->fulfilment->recheck($item?->order_id, $context);
        }
        $this->audit->record($context->withScope($domain->organization_id), 'domain.registration_failed', 'failed', ['fqdn' => $domain->fqdn_ascii, 'reason' => $reason], 'domain', $domain->id);
        $this->outbox->publish(GenericEvent::of('domain.registration_failed', 'domain', $domain->id, ['fqdn' => $domain->fqdn_ascii, 'reason' => $reason, 'order_item_id' => $operation->order_item_id, 'operation_id' => $operation->id], $domain->organization_id));
    }

    // ── renewals ─────────────────────────────────────────────────────────────

    /** @return array{net:Money,tax:Money,gross:Money,rate:string,category:string,calculation_id:string} */
    public function renewalPrice(Domain $domain, int $years, Organization $organization): array
    {
        $price = $this->catalog->domainPrice($domain->tld, $organization->currency);
        $net = $price->renew()->multiply($years);
        $calc = $this->tax->calculate(['country' => $organization->country, 'customer_class' => $organization->customer_class, 'vat_status' => $organization->vat_status], [['key' => 'renew', 'net' => $net, 'product_class' => 'domain']], $organization->currency, $organization->id);
        $line = $calc['lines'][0];

        return ['net' => $net, 'tax' => $line['tax'], 'gross' => $line['total'], 'rate' => (string) $line['rate'], 'category' => (string) $line['category'], 'calculation_id' => $calc['calculation']->id];
    }

    /** Holds the gross amount with domain priority and starts the renewal saga. */
    public function renew(Domain $domain, int $years, CommandContext $context, string $idempotencyKey, ?DomainRenewalJob $job = null): Operation
    {
        $this->assertNotMirrored($domain, 'renew');
        if (! in_array($domain->state, [DomainStateMachine::ACTIVE, DomainStateMachine::EXPIRED, DomainStateMachine::GRACE], true)) {
            throw new DomainError('domain_not_renewable', "{$domain->fqdn_ascii} is {$domain->state}; only active, expired or grace-period domains can be renewed.", 409);
        }
        if (! $this->catalog->tld($domain->tld)->allowsPeriod($years)) {
            throw new DomainError('domain_period_not_allowed', "Renewal period {$years} is not allowed for .{$domain->tld}.", 422);
        }
        $existing = Operation::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }
        $organization = Organization::query()->findOrFail($domain->organization_id);
        $price = $this->renewalPrice($domain, $years, $organization);
        $instance = $this->registrar->instanceForDomain($domain); // before any money is touched: no registrar, no reservation
        // One renewal of a domain at a time, and all of it or nothing. The reminder mail sends the customer to „Prodloužit“ in the
        // same hour the scheduler renews: two operations with two keys each reserved the money and each sent its own renewal —
        // two years, two charges. And a registrar that could not be reached AFTER the reservation left the job in HOLD_PLACED,
        // a state nothing ever picks up again: that domain silently never renewed by itself any more.
        $operation = DB::transaction(function () use ($domain, $years, $context, $idempotencyKey, $job, $organization, $price, $instance) {
            Domain::query()->whereKey($domain->id)->lockForUpdate()->first();
            $running = Operation::query()->where('domain_id', $domain->id)->where('kind', RenewDomainWorkflow::kind())->whereIn('state', [Operation::PENDING, Operation::RUNNING, Operation::WAITING])->first();
            if ($running !== null) {
                throw new DomainError('domain_renewal_in_progress', "{$domain->fqdn_ascii} is being renewed right now; wait for that renewal to finish.", 409, ['operation_id' => $running->id]);
            }
            $hold = $this->wallets->hold($organization, $price['gross'], 'domain_renewal', "domain_renewal:{$idempotencyKey}", $context, 'domain', $domain->id, 'domain', 60 * 24 * 7);
            $operation = $this->operations->start(RenewDomainWorkflow::class, $idempotencyKey, [
                'domain_id' => $domain->id, 'fqdn' => $domain->fqdn_ascii, 'period' => $years, 'wallet_hold_id' => $hold->id, 'renewal_job_id' => $job?->id,
                'net_minor' => $price['net']->minor, 'tax_minor' => $price['tax']->minor, 'gross_minor' => $price['gross']->minor, 'tax_rate' => $price['rate'], 'tax_category' => $price['category'], 'currency' => $organization->currency,
                'test_mode' => (bool) config('onhost.wapi.test_mode', false),
            ], $context, null, $organization->id, null, $instance->id, $domain->id, dispatch: false);
            $job?->forceFill(['state' => DomainRenewalJob::SENT, 'wallet_hold_id' => $hold->id, 'operation_id' => $operation->id, 'attempts' => $job->attempts + 1])->save();
            $this->audit->record($context->withScope($organization->id), 'domain.renew', 'succeeded', ['fqdn' => $domain->fqdn_ascii, 'years' => $years, 'hold_id' => $hold->id], 'domain', $domain->id);

            return $operation;
        }, 3);
        $this->operations->dispatch($operation); // after the job row points at the operation, so the saga's bookkeeping is never overwritten

        return $operation;
    }

    /** Capture + statement + subscription advance, after the registry confirmed the new expiry (ConfirmRenewalStep). */
    public function settleRenewal(Domain $domain, Operation $operation, CommandContext $context): void
    {
        $desired = (array) $operation->desired;
        $organization = Organization::query()->findOrFail($domain->organization_id);
        $hold = isset($desired['wallet_hold_id']) ? WalletHold::query()->find($desired['wallet_hold_id']) : null;
        $currency = (string) ($desired['currency'] ?? $organization->currency);
        $tax = Money::minor((int) ($desired['tax_minor'] ?? 0), $currency);
        if ($hold !== null && $hold->isActive()) {
            $this->wallets->capture($hold, 'domain', $context, null, $tax, "Prodloužení domény {$domain->fqdn_ascii}");
        }
        $statementExists = Invoice::query()->where('type', 'statement')->where('meta->operation_id', $operation->id)->exists();
        if (! $statementExists) {
            $years = (int) ($desired['period'] ?? 1);
            $net = (int) ($desired['net_minor'] ?? 0);
            $line = [
                'sku' => "domain-renew-{$domain->tld}", 'description' => "Prodloužení domény {$domain->fqdn_unicode} ({$years} ".($years === 1 ? 'rok' : 'roky').')', 'qty' => 1, 'unit' => 'ks',
                'unit_net' => $net, 'discount' => 0, 'net' => $net, 'tax_rate' => (string) ($desired['tax_rate'] ?? '0'), 'tax_category' => (string) ($desired['tax_category'] ?? 'S'), 'tax' => $tax->minor, 'total' => $net + $tax->minor,
                'period_from' => $domain->expires_at?->copy()->subYears($years)->toDateString(), 'period_to' => $domain->expires_at?->toDateString(),
            ];
            $draft = $this->invoices->draft($organization, 'statement', $currency, [$line], $context, null, ['payment_method' => 'wallet', 'operation_id' => $operation->id, 'domain_id' => $domain->id]);
            $invoice = $this->invoices->issue($draft, $context, dueDays: 0);
            $this->invoices->markPaid($invoice, $invoice->total(), 'wallet', $context, postLedger: false);
        }
        $lead = (int) config('onhost.domains.renew_lead_days', 14);
        Subscription::query()->where('domain_id', $domain->id)->update([
            'current_period_start' => now(), 'current_period_end' => $domain->expires_at, 'next_renewal_at' => $domain->expires_at?->copy()->subDays($lead), 'last_renewed_at' => now(), 'renewal_failures' => 0, 'state' => Subscription::ACTIVE,
        ]);
        if (! empty($desired['renewal_job_id'])) {
            DomainRenewalJob::query()->whereKey($desired['renewal_job_id'])->update(['state' => DomainRenewalJob::SUCCEEDED]);
        }
        if (in_array($domain->state, [DomainStateMachine::EXPIRED, DomainStateMachine::GRACE], true)) {
            $domain->forceFill(['state' => DomainStateMachine::ACTIVE])->save();
        }
        $this->audit->record($context->withScope($domain->organization_id), 'domain.renewed', 'succeeded', ['fqdn' => $domain->fqdn_ascii, 'expires_at' => $domain->expires_at?->toDateString()], 'domain', $domain->id);
        $this->outbox->publish(GenericEvent::of('domain.renewed', 'domain', $domain->id, ['fqdn' => $domain->fqdn_ascii, 'expires_at' => $domain->expires_at?->toIso8601String(), 'years' => (int) ($desired['period'] ?? 1)], $domain->organization_id));
    }

    public function renewalFailed(Domain $domain, Operation $operation, CommandContext $context, string $reason, bool $registryRenewed): void
    {
        $desired = (array) $operation->desired;
        $hold = isset($desired['wallet_hold_id']) ? WalletHold::query()->find($desired['wallet_hold_id']) : null;
        if ($hold !== null && $hold->isActive() && ! $registryRenewed) {
            $this->wallets->release($hold, "renewal failed: {$reason}", $context);
        }
        if (! empty($desired['renewal_job_id'])) {
            DomainRenewalJob::query()->whereKey($desired['renewal_job_id'])->update(['state' => $registryRenewed ? DomainRenewalJob::PENDING_REGISTRY : DomainRenewalJob::FAILED, 'last_error' => mb_substr($reason, 0, 250)]);
        }
        Subscription::query()->where('domain_id', $domain->id)->increment('renewal_failures');
        $this->audit->record($context->withScope($domain->organization_id), 'domain.renewal_failed', 'failed', ['fqdn' => $domain->fqdn_ascii, 'reason' => $reason, 'registry_renewed' => $registryRenewed], 'domain', $domain->id);
        $this->outbox->publish(GenericEvent::of('domain.renewal_failed', 'domain', $domain->id, ['fqdn' => $domain->fqdn_ascii, 'reason' => $reason, 'registry_renewed' => $registryRenewed, 'operation_id' => $operation->id], $domain->organization_id));
    }

    // ── transfers & registry settings ────────────────────────────────────────

    public function transferIn(Organization $organization, string $fqdn, string $authInfo, array $request, CommandContext $context, string $idempotencyKey): Operation
    {
        $this->assertStepUp($context, 'domain transfer');
        $fqdn = Hostname::canonical($fqdn);
        if (! Hostname::isRegistrable($fqdn)) {
            throw new DomainError('domain_invalid', "{$fqdn} is not a registrable domain name.", 422);
        }
        $existing = Operation::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }
        $tld = Hostname::tld($fqdn);
        $this->catalog->tld($tld);
        $choice = $this->selector->choose($tld, 'transfer');
        $provider = $choice['provider'];
        $domain = DB::transaction(function () use ($organization, $fqdn, $tld, $request, $authInfo, $context, $provider, $choice) {
            $domain = Domain::query()->where('fqdn_ascii', $fqdn)->first();
            if ($domain !== null && $domain->organization_id !== $organization->id && $domain->isActive()) {
                throw new DomainError('domain_taken', "{$fqdn} is already managed by another organization.", 409);
            }
            $registrant = $this->resolveContact($organization, $request['registrant_contact_id'] ?? null, $request['registrant'] ?? null, $context, $provider);
            $attributes = [
                'organization_id' => $organization->id, 'fqdn_ascii' => $fqdn, 'fqdn_unicode' => Hostname::unicode($fqdn), 'tld' => $tld, 'state' => DomainStateMachine::TRANSFER_IN_PENDING, 'registrar_provider' => $provider,
                'meta' => array_merge((array) ($domain?->meta ?? []), ['registrar_selection' => RegistrarSelector::summary($choice)]),
                'registrant_contact_id' => $registrant->id, 'admin_contact_id' => $registrant->id, 'dns_provider' => (string) ($request['dns_provider'] ?? 'external'), 'nameservers' => $request['nameservers'] ?? null, 'renewal_period' => 1,
            ];
            $domain = $domain === null ? Domain::query()->create($attributes) : tap($domain)->forceFill($attributes)->save();
            DomainTransferSecret::query()->create(['domain_id' => $domain->id, 'auth_info' => $authInfo, 'direction' => 'in', 'requested_by' => $context->actorId, 'step_up_method' => $context->stepUpMethod, 'expires_at' => now()->addDays((int) config('onhost.domains.transfer_secret_ttl_days', 7))]);
            $this->recordConsents($domain, $request, $context);

            return $domain;
        });
        $operation = $this->operations->start(TransferDomainInWorkflow::class, $idempotencyKey, ['domain_id' => $domain->id, 'fqdn' => $fqdn, 'tld' => $tld, 'period' => (int) ($request['period'] ?? 1), 'nameservers' => $request['nameservers'] ?? null, 'event' => 'domain.transferred_in', 'registrar_provider' => $provider], $context, null, $organization->id, null, $choice['instance']->id, $domain->id);
        $this->audit->record($context->withScope($organization->id), 'domain.transfer_in', 'succeeded', ['fqdn' => $fqdn, 'operation_id' => $operation->id], 'domain', $domain->id, stepUp: $context->stepUpMethod);

        return $operation;
    }

    /** Outbound transfer: the registrar mails the AUTH-ID to the registrant. Step-up + critical policy + transfer lock (§46.5). */
    /** @return array{delivery:string, auth_info?:string, expires_at?:string} `registrar_email` (the registrar mails the holder) or `inline` (shown once to the verified user, kept encrypted for 7 days) */
    public function requestAuthInfo(Domain $domain, CommandContext $context): array
    {
        $this->assertStepUp($context, 'AUTH-ID request');
        $this->assertActionAllowed($domain, 'transfer_out', $context);
        if ($domain->transfer_lock) {
            throw new DomainError('domain_transfer_locked', 'Transfer lock is enabled; disable it first (this is audited).', 409);
        }
        $result = $this->registrar->mutate('domain-send-auth-info', $domain, ['name' => $domain->fqdn_ascii], fn (RegistrarProvider $a, string $clTrid) => $a->sendAuthInfo($domain->fqdn_ascii, $clTrid), null, $domain->organization_id);
        $domain->forceFill(['meta' => array_merge((array) $domain->meta, ['auth_info_requested_at' => now()->toIso8601String(), 'auth_info_requested_by' => $context->actorId])])->save();
        $out = ['delivery' => 'registrar_email'];
        $authInfo = (string) ($result->data['auth_info'] ?? '');
        if (($result->data['delivery'] ?? null) === 'inline' && $authInfo !== '') {
            $ttl = (int) config('onhost.domains.transfer_secret_ttl_days', 7);
            $secret = DomainTransferSecret::query()->create(['domain_id' => $domain->id, 'auth_info' => $authInfo, 'direction' => 'out', 'requested_by' => $context->actorId, 'step_up_method' => $context->stepUpMethod, 'expires_at' => now()->addDays($ttl)]);
            $out = ['delivery' => 'inline', 'auth_info' => $authInfo, 'expires_at' => $secret->expires_at->toIso8601String()];
        }
        $this->audit->record($context->withScope($domain->organization_id), 'domain.auth_info_request', 'succeeded', ['fqdn' => $domain->fqdn_ascii], 'domain', $domain->id, stepUp: $context->stepUpMethod, approvalIds: $context->approvalIds);
        $this->outbox->publish(GenericEvent::of('domain.auth_info_requested', 'domain', $domain->id, ['fqdn' => $domain->fqdn_ascii, 'by' => $context->actorId, 'delivery' => $out['delivery']], $domain->organization_id));

        return $out;
    }

    /** @param list<string> $nameservers */
    public function updateNameservers(Domain $domain, array $nameservers, CommandContext $context, string $idempotencyKey, string $dnsProvider = 'external'): Operation
    {
        $this->assertActionAllowed($domain, 'nameservers', $context);
        $nameservers = array_values(array_unique(array_map(fn ($ns) => strtolower(rtrim(trim((string) $ns), '.')), $nameservers)));
        if (count($nameservers) < 2 || count($nameservers) > 8) {
            throw new DomainError('nameservers_count', 'Provide between 2 and 8 nameservers.', 422);
        }
        foreach ($nameservers as $ns) {
            if (! Hostname::isRegistrable($ns) && ! filter_var($ns, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                throw new DomainError('nameserver_invalid', "{$ns} is not a valid nameserver hostname.", 422);
            }
        }
        $existing = Operation::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }
        $operation = $this->operations->start(UpdateNameserversWorkflow::class, $idempotencyKey, ['domain_id' => $domain->id, 'fqdn' => $domain->fqdn_ascii, 'nameservers' => $nameservers, 'dns_provider' => $dnsProvider], $context, null, $domain->organization_id, null, $this->registrar->instanceForDomain($domain)->id, $domain->id);
        $this->audit->record($context->withScope($domain->organization_id), 'domain.update_ns', 'succeeded', ['fqdn' => $domain->fqdn_ascii, 'nameservers' => $nameservers], 'domain', $domain->id, approvalIds: $context->approvalIds);

        return $operation;
    }

    /** Switch a domain to ONhost DNS: create the canonical zone, then delegate to the ONhost nameservers. */
    public function useOnhostDns(Domain $domain, CommandContext $context, string $idempotencyKey, string $template = 'parking', array $vars = []): Operation
    {
        $this->assertNotMirrored($domain, 'use_onhost_dns');
        $zone = $this->dns->ensureZone($domain->organization_id, $domain->fqdn_ascii, $context, $domain->id, $template, $this->dnsVars($vars));
        $domain->forceFill(['dns_zone_id' => $zone->id])->save();

        return $this->updateNameservers($domain, (array) $zone->nameservers, $context, $idempotencyKey, 'powerdns');
    }

    public function publishDs(Domain $domain, CommandContext $context): void
    {
        $zone = $domain->dns_zone_id ? DnsZone::query()->find($domain->dns_zone_id) : null;
        if ($zone === null || ! $zone->dnssec || empty($zone->dnssec_ds)) {
            throw new DomainError('dnssec_not_enabled', 'Enable DNSSEC on the ONhost zone before publishing DS records.', 409);
        }
        $this->assertActionAllowed($domain, 'dnssec', $context);
        $this->registrar->mutate('domain-update-keyset', $domain, ['name' => $domain->fqdn_ascii, 'ds' => $zone->dnssec_ds], fn (RegistrarProvider $a, string $clTrid) => $a->updateKeyset($domain->fqdn_ascii, ['ds' => $zone->dnssec_ds], $clTrid), null, $domain->organization_id);
        $domain->forceFill(['dnssec' => true, 'keyset_ref' => 'ds:'.substr(sha1(implode('|', (array) $zone->dnssec_ds)), 0, 16)])->save();
        $this->audit->record($context->withScope($domain->organization_id), 'domain.dnssec.publish', 'succeeded', ['fqdn' => $domain->fqdn_ascii, 'ds' => $zone->dnssec_ds], 'domain', $domain->id);
        $this->outbox->publish(GenericEvent::of('domain.dnssec_published', 'domain', $domain->id, ['fqdn' => $domain->fqdn_ascii], $domain->organization_id));
    }

    public function setAutoRenew(Domain $domain, bool $enabled, CommandContext $context): Domain
    {
        $this->assertNotMirrored($domain, 'auto_renew');
        if (! $enabled && $domain->critical) {
            $this->assertActionAllowed($domain, 'auto_renew_off', $context);
        }
        $domain->forceFill(['auto_renew' => $enabled])->save();
        Subscription::query()->where('domain_id', $domain->id)->update(['auto_renew' => $enabled]);
        if (! $enabled) {
            DomainRenewalJob::query()->where('domain_id', $domain->id)->whereIn('state', [DomainRenewalJob::SCHEDULED])->update(['state' => DomainRenewalJob::SKIPPED, 'last_error' => 'auto-renew disabled']);
        }
        $this->audit->record($context->withScope($domain->organization_id), 'domain.auto_renew', 'succeeded', ['fqdn' => $domain->fqdn_ascii, 'enabled' => $enabled], 'domain', $domain->id);
        $this->outbox->publish(GenericEvent::of('domain.auto_renew_changed', 'domain', $domain->id, ['fqdn' => $domain->fqdn_ascii, 'enabled' => $enabled], $domain->organization_id));

        return $domain;
    }

    public function setTransferLock(Domain $domain, bool $locked, CommandContext $context): Domain
    {
        if (! $locked) {
            $this->assertStepUp($context, 'transfer unlock');
            $this->assertActionAllowed($domain, 'transfer_unlock', $context);
        }
        $domain->forceFill(['transfer_lock' => $locked])->save();
        $this->audit->record($context->withScope($domain->organization_id), 'domain.transfer_lock', 'succeeded', ['fqdn' => $domain->fqdn_ascii, 'locked' => $locked], 'domain', $domain->id, stepUp: $context->stepUpMethod);

        return $domain;
    }

    // ── contacts ─────────────────────────────────────────────────────────────

    /** @param array<string,mixed> $data */
    public function createContact(Organization $organization, array $data, CommandContext $context, ?string $provider = null): RegistrarContact
    {
        foreach (['name', 'email', 'street', 'city', 'postal_code', 'country'] as $required) {
            if (empty($data[$required])) {
                throw new DomainError('contact_incomplete', "Registrant contact field {$required} is required.", 422);
            }
        }
        if (filter_var((string) $data['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainError('contact_email_invalid', 'Registrant e-mail is invalid.', 422);
        }
        $contact = RegistrarContact::query()->create([
            'organization_id' => $organization->id, 'registrar_provider' => $provider ?? $this->registrar->defaultProviderKey(), 'schema' => (string) ($data['schema'] ?? 'generic'), 'kind' => (string) ($data['kind'] ?? 'registrant'),
            'name' => (string) $data['name'], 'organization_name' => $data['organization_name'] ?? null, 'email' => (string) $data['email'], 'phone' => $data['phone'] ?? null,
            'street' => (string) $data['street'], 'city' => (string) $data['city'], 'postal_code' => (string) $data['postal_code'], 'country' => strtoupper((string) $data['country']),
            'ico' => $data['ico'] ?? null, 'dic' => $data['dic'] ?? null, 'privacy' => (string) ($data['privacy'] ?? 'hidden'), 'state' => 'draft', 'registry_fields' => $data['registry_fields'] ?? null,
        ]);
        $this->audit->record($context->withScope($organization->id), 'domain.contact.create', 'succeeded', ['contact_id' => $contact->id, 'kind' => $contact->kind], 'registrar_contact', $contact->id);

        return $contact;
    }

    // ── registry truth ───────────────────────────────────────────────────────

    /** How many domains one reconciliation asks the registry about one by one (those a status-less listing cannot explain). */
    private const RECONCILE_INFO_LIMIT = 100;

    /** @param array<string,mixed> $info normalised domain-info */
    public function applyRegistryInfo(Domain $domain, array $info): Domain
    {
        $expires = ! empty($info['expires_at']) ? new \DateTimeImmutable((string) $info['expires_at']) : null;
        $registered = ! empty($info['registered_at']) ? new \DateTimeImmutable((string) $info['registered_at']) : null;
        $patch = ['registry_status' => $info['raw'] ?? $info, 'last_reconciled_at' => now()];
        if ($expires !== null) {
            $patch['expires_at'] = $expires;
        }
        if ($registered !== null && $domain->registered_at === null) {
            $patch['registered_at'] = $registered;
        }
        if (! empty($info['nameservers']) && $domain->dns_provider !== 'powerdns') {
            $patch['nameservers'] = array_values(array_map(fn ($ns) => is_array($ns) ? (string) ($ns['name'] ?? '') : (string) $ns, (array) $info['nameservers']));
        }
        if (array_key_exists('dnssec', $info)) {
            $patch['dnssec'] = (bool) $info['dnssec'];
        }
        // a LISTING that carries no status (Subreg's does not) says nothing about the state: '' used to read as "active", so the nightly
        // reconciliation revived a domain in redemption, or on its way out, as ACTIVE. Dates are taken from it, the state is not.
        if (empty($info['status_unknown']) && ! in_array($domain->state, [DomainStateMachine::DELETED, DomainStateMachine::TRANSFERRED_OUT, DomainStateMachine::FAILED], true)) {
            $patch['state'] = DomainStateMachine::fromRegistryStatus((string) ($info['status'] ?? ''), $expires);
            if ($domain->state === DomainStateMachine::TRANSFER_IN_PENDING && $patch['state'] === DomainStateMachine::PENDING_REGISTRY) {
                $patch['state'] = DomainStateMachine::TRANSFER_IN_PENDING;
            }
        }
        $domain->forceFill($patch)->save();

        return $domain;
    }

    /**
     * Daily registrar reconciliation (blueprint §46.6): registrar listing vs local, expiry sweep.
     *
     * @return array{checked:int, updated:int, missing_remote:list<string>, unknown_remote:list<string>, expired:int}
     */
    public function reconcile(CommandContext $context): array
    {
        $remote = [];
        $registrars = [];
        foreach ($this->registrar->instances() as $instance) {
            $registrars[] = $instance->provider;
            foreach ($this->registrar->adapterFor($instance)->listDomains() as $row) {
                $remote[strtolower((string) $row['name'])] = $row + ['registrar_provider' => $instance->provider];
            }
        }
        $checked = 0;
        $updated = 0;
        $asked = 0;
        $missing = [];
        $locals = Domain::query()->whereNotIn('state', [DomainStateMachine::DELETED, DomainStateMachine::TRANSFERRED_OUT, DomainStateMachine::FAILED, DomainStateMachine::PENDING_REGISTRATION])->get();
        foreach ($locals as $domain) {
            $checked++;
            $row = $remote[$domain->fqdn_ascii] ?? null;
            if ($row === null) {
                $missing[] = $domain->fqdn_ascii;
                $this->outbox->publish(GenericEvent::of('domain.reconcile.missing_remote', 'domain', $domain->id, ['fqdn' => $domain->fqdn_ascii, 'state' => $domain->state], $domain->organization_id));

                continue;
            }
            $before = [$domain->state, $domain->expires_at?->toDateString()];
            if ($domain->registrar_provider !== $row['registrar_provider']) { // registry truth: the domain lives at another of our registrars (transfer between accounts)
                $domain->forceFill(['registrar_provider' => $row['registrar_provider']])->save();
                $this->outbox->publish(GenericEvent::of('domain.reconcile.registrar_changed', 'domain', $domain->id, ['fqdn' => $domain->fqdn_ascii, 'registrar' => $row['registrar_provider']], $domain->organization_id));
            }
            $statusUnknown = trim((string) ($row['status'] ?? '')) === '';
            $rowExpired = ! empty($row['expires_at']) && new \DateTimeImmutable((string) $row['expires_at']) < new \DateTimeImmutable('now');
            if ($statusUnknown && ($domain->state !== DomainStateMachine::ACTIVE || $rowExpired) && $asked < self::RECONCILE_INFO_LIMIT) {
                // the listing cannot explain this one: the registry is asked about the domain itself (a handful per night, not the whole portfolio)
                $asked++;
                try {
                    $this->applyRegistryInfo($domain, $this->registrar->forDomain($domain)->domainInfo($domain->fqdn_ascii));
                } catch (ProviderException $e) {
                    $this->applyRegistryInfo($domain, ['status_unknown' => true, 'expires_at' => $row['expires_at'] ?? null, 'registered_at' => $row['registered_at'] ?? null, 'raw' => $row]);
                }
            } else {
                $this->applyRegistryInfo($domain, ['status' => $row['status'] ?? '', 'status_unknown' => $statusUnknown, 'expires_at' => $row['expires_at'] ?? null, 'registered_at' => $row['registered_at'] ?? null, 'raw' => $row]);
            }
            if ($before !== [$domain->state, $domain->expires_at?->toDateString()]) {
                $updated++;
                $this->outbox->publish(GenericEvent::of('domain.reconciled', 'domain', $domain->id, ['fqdn' => $domain->fqdn_ascii, 'state' => $domain->state, 'expires_at' => $domain->expires_at?->toIso8601String()], $domain->organization_id));
            }
            unset($remote[$domain->fqdn_ascii]);
        }
        $unknown = array_keys($remote);
        foreach ($unknown as $fqdn) {
            $this->outbox->publish(GenericEvent::of('domain.reconcile.unknown_remote', 'registrar', (string) $remote[$fqdn]['registrar_provider'], ['fqdn' => $fqdn, 'row' => $remote[$fqdn]]));
        }
        $expired = Domain::query()->where('state', DomainStateMachine::ACTIVE)->whereNotNull('expires_at')->where('expires_at', '<', now())->get();
        foreach ($expired as $domain) {
            $domain->forceFill(['state' => DomainStateMachine::EXPIRED])->save();
            $this->outbox->publish(GenericEvent::of('domain.expired', 'domain', $domain->id, ['fqdn' => $domain->fqdn_ascii, 'expires_at' => $domain->expires_at?->toIso8601String()], $domain->organization_id));
        }
        $this->audit->record($context, 'domain.reconcile', 'succeeded', ['checked' => $checked, 'updated' => $updated, 'missing_remote' => count($missing), 'unknown_remote' => count($unknown), 'expired' => $expired->count(), 'registrars' => $registrars], 'registrar', implode(',', $registrars) ?: 'none');

        return ['checked' => $checked, 'updated' => $updated, 'missing_remote' => $missing, 'unknown_remote' => $unknown, 'expired' => $expired->count()];
    }

    /** Resolve an operation left PENDING_REGISTRY/UNKNOWN after a timeout by asking the registry (S34). */
    public function resolveUnknown(Domain $domain): Domain
    {
        try {
            return $this->applyRegistryInfo($domain, $this->registrar->forDomain($domain)->domainInfo($domain->fqdn_ascii));
        } catch (ProviderException $e) {
            if ($e->errorCode === ProviderErrorCode::NOT_FOUND) {
                return $domain;
            }
            throw $e;
        }
    }

    // ── policies ─────────────────────────────────────────────────────────────

    /** Critical Domain Policy (§46.7): infrastructure domains need a second approver for destructive changes. */
    public function assertActionAllowed(Domain $domain, string $action, CommandContext $context): void
    {
        $this->assertNotMirrored($domain, $action);
        if (! $domain->critical) {
            return;
        }
        if ($context->actorType === 'ai') {
            throw new DomainError('critical_domain_ai_forbidden', "AI actors may not change critical domain {$domain->fqdn_ascii}.", 403);
        }
        if ($context->approvalIds === []) {
            throw new DomainError('critical_domain_approval_required', "{$action} on critical domain {$domain->fqdn_ascii} requires a two-person approval.", 403, ['domain_id' => $domain->id, 'action' => $action]);
        }
    }

    /** Domains mirrored from a customer's connected registrar account are managed there: the platform never renews, transfers or re-delegates them (pairing covers hosting). */
    private function assertNotMirrored(Domain $domain, string $action): void
    {
        if (data_get($domain->meta, 'source') === 'connection') {
            throw new DomainError('domain_external', "{$domain->fqdn_ascii} is managed through the connected registrar account; {$action} happens at the registrar (pair it with a hosting plan here).", 409, ['domain_id' => $domain->id, 'action' => $action, 'connection_id' => data_get($domain->meta, 'connection_id')]);
        }
    }

    private function assertStepUp(CommandContext $context, string $what): void
    {
        if ($context->actorType === 'user' && $context->stepUpMethod === null) {
            throw new DomainError('step_up_required', "A fresh step-up (WebAuthn/TOTP) is required for {$what}.", 403, ['step_up' => 'required']);
        }
    }

    private function resolveContact(Organization $organization, ?string $contactId, ?array $data, CommandContext $context, ?string $provider = null): RegistrarContact
    {
        if ($contactId !== null) {
            $contact = RegistrarContact::query()->find($contactId);
            if ($contact === null || $contact->organization_id !== $organization->id) {
                throw new DomainError('contact_not_found', 'Registrant contact does not exist in this organization.', 404);
            }

            return $contact;
        }
        if ($data !== null) {
            return $this->createContact($organization, $data, $context, $provider);
        }
        $existing = RegistrarContact::query()->where('organization_id', $organization->id)->where('kind', 'registrant')->latest()->first();
        if ($existing !== null) {
            return $existing;
        }

        return $this->createContact($organization, [
            'name' => $organization->name, 'organization_name' => $organization->isB2b() ? $organization->name : null, 'email' => $organization->billing_email ?: ($organization->owner?->email ?? ''),
            'street' => $organization->street ?? '', 'city' => $organization->city ?? '', 'postal_code' => $organization->postal_code ?? '', 'country' => $organization->country ?: 'CZ', 'ico' => $organization->ico, 'dic' => $organization->dic ?: $organization->vat_id,
        ], $context);
    }

    private function recordConsents(Domain $domain, array $request, CommandContext $context): void
    {
        $consents = (array) ($request['consents'] ?? []);
        if (isset($request['consent']) && is_array($request['consent'])) {
            $c = $request['consent'];
            DomainConsent::query()->create(['domain_id' => $domain->id, 'tld' => $domain->tld, 'registrar_terms_url' => $c['registrar_terms_url'] ?? null, 'registry_terms_url' => $c['registry_terms_url'] ?? null, 'document_version' => $c['document_version'] ?? null, 'document_hash' => $c['document_hash'] ?? null, 'language' => $c['language'] ?? 'cs', 'person' => (string) ($c['person'] ?? ''), 'user_id' => $context->actorType === 'user' ? $context->actorId : null, 'registrant_contact_id' => $domain->registrant_contact_id, 'ip' => $c['ip'] ?? $context->ip, 'accepted_at' => isset($c['accepted_at']) ? new \DateTimeImmutable((string) $c['accepted_at']) : now()]);
        }
        foreach ($consents as $consent) {
            if (! $consent instanceof Consent) {
                continue;
            }
            DomainConsent::query()->firstOrCreate(['domain_id' => $domain->id, 'consent_id' => $consent->id], [
                'tld' => $domain->tld, 'registry_terms_url' => $consent->kind === 'registry_terms' ? $consent->document_url : null, 'registrar_terms_url' => $consent->kind === 'registrar_terms' ? $consent->document_url : null,
                'document_version' => $consent->document_version, 'document_hash' => $consent->document_hash, 'language' => $consent->language, 'person' => (string) ($consent->person ?? ''), 'user_id' => $consent->user_id,
                'registrant_contact_id' => $domain->registrant_contact_id, 'ip' => $consent->ip, 'accepted_at' => $consent->accepted_at,
            ]);
            if ($consent->domain_id === null) {
                $consent->forceFill(['domain_id' => $domain->id])->save();
            }
        }
        if (! DomainConsent::query()->where('domain_id', $domain->id)->exists()) {
            throw new DomainError('consent_required', 'Registry and registrar terms must be accepted before a domain can be registered.', 422);
        }
    }

    /**
     * Availability at the registrar that would register each name (cheapest for its TLD); a registrar
     * outage degrades to `available: null` for its names instead of failing the whole search.
     *
     * @param  list<string>  $fqdns  @return array<string, array{available:bool|null, reason?:string}>
     */
    private function checkAvailability(array $fqdns): array
    {
        $groups = [];
        $instances = [];
        foreach ($fqdns as $fqdn) {
            try {
                $instance = $this->selector->instanceForTld(Hostname::tld($fqdn));
            } catch (DomainError) {
                $instance = null;
            }
            $key = $instance?->key ?? '';
            $instances[$key] = $instance;
            $groups[$key][] = $fqdn;
        }
        $out = [];
        foreach ($groups as $key => $names) {
            $instance = $instances[$key];
            if ($instance === null) {
                foreach ($names as $name) {
                    $out[$name] = ['available' => null, 'reason' => 'registrar_unavailable'];
                }

                continue;
            }
            try {
                $out += $this->registrar->adapterFor($instance)->checkAvailability($names);
            } catch (ProviderException|DomainError $e) {
                foreach ($names as $name) {
                    $out[$name] = ['available' => null, 'reason' => $e instanceof ProviderException ? (string) ($e->context['normalized'] ?? 'error') : $e->error];
                }
            }
        }

        return $out;
    }

    /** @param array<string,mixed> $vars */
    private function dnsVars(array $vars): array
    {
        return array_merge(array_filter([
            'parking_ipv4' => config('onhost.dns.parking_ipv4'), 'mail_host' => config('onhost.dns.mail_host'), 'spf_include' => config('onhost.dns.spf_include'),
        ], fn ($v) => $v !== null && $v !== ''), $vars);
    }
}
