<?php

declare(strict_types=1);

namespace App\Http\Presenters;

use Onhost\Domain\Billing\ServiceReinstatement;
use Onhost\Domain\Dns\Models\DnsChange;
use Onhost\Domain\Dns\Models\DnsRecord;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\RegistrarConnection;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Orders\CreditOrderApprovals;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Models\ResourceDrift;
use Onhost\Domain\Provisioning\OperationSecrets;
use Onhost\Domain\Provisioning\ServiceMigrationService;
use Onhost\Domain\Services\ControlPlaneStatus;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceFreshness;
use Onhost\Domain\Services\SuspensionHold;
use Onhost\Domain\Tax\VatStanding;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Observability\Tracer;

/**
 * JSON shapes of the v1 API. Money is always `{minor, currency, decimal}` (integer minor units),
 * ids are prefixed ULIDs, timestamps ISO-8601, states are the platform state machine values with
 * the UI key the surfaces already use (`ui`).
 */
final class Presenters
{
    public static function money(int $minor, string $currency): array
    {
        return Money::minor($minor, $currency)->jsonSerialize();
    }

    public static function user(User $user): array
    {
        return [
            'id' => $user->id, 'email' => $user->email, 'name' => $user->name, 'locale' => $user->locale, 'timezone' => $user->timezone, 'is_staff' => (bool) $user->is_staff,
            'mfa' => ['totp' => $user->hasTotp(), 'webauthn' => $user->hasWebAuthn()], 'email_verified' => $user->email_verified_at !== null, 'since' => $user->created_at?->toIso8601String(),
        ];
    }

    public static function organization(Organization $organization, ?string $role = null): array
    {
        if ($role === 'guest') {
            // somebody a single service was shared with: the name of whose service it is, and nothing of the company behind it —
            // no billing identity, no billing e-mail, no settings, no referral code
            return ['id' => $organization->id, 'slug' => $organization->slug, 'name' => $organization->name, 'locale' => $organization->locale, 'currency' => $organization->currency, 'role' => $role, 'guest' => true];
        }

        return [
            'id' => $organization->id, 'slug' => $organization->slug, 'name' => $organization->name, 'type' => $organization->type, 'country' => $organization->country, 'currency' => $organization->currency, 'locale' => $organization->locale,
            'billing' => ['email' => $organization->billing_email, 'mode' => $organization->billing_mode, 'ico' => $organization->ico, 'dic' => $organization->dic, 'vat_id' => $organization->vat_id, 'vat_status' => VatStanding::effectiveStatus($organization), 'vat_checked_at' => $organization->vat_checked_at?->toIso8601String(), 'street' => $organization->street, 'city' => $organization->city, 'postal_code' => $organization->postal_code],
            'customer_class' => $organization->customer_class, 'state' => $organization->state, 'role' => $role,
            'settings' => ['digest' => ['frequency' => (string) data_get($organization->settings, 'digest.frequency', 'weekly')], 'status_page' => (array) data_get($organization->settings, 'status_page', ['enabled' => false]), 'loyalty_discount' => data_get($organization->settings, 'loyalty_discount')], // digest tuning (audit §5f-5), status page and streak discount (§5j)
            'feature_flags' => ['sandbox' => (bool) data_get($organization->feature_flags, 'sandbox', false)], 'referral_code' => $organization->referral_code,
        ];
    }

    public static function project(Project $project): array
    {
        return [
            'id' => $project->id, 'key' => $project->slug, 'slug' => $project->slug, 'name' => $project->name, 'description' => $project->description, 'cost_center' => $project->cost_center,
            'tags' => array_values((array) ($project->tags ?? [])), 'state' => $project->state ?? 'active', 'created_at' => $project->created_at?->toIso8601String(),
        ];
    }

    public static function service(Service $service): array
    {
        $ui = ServiceStateMachine::machine()->toArray()[$service->state]['ui'] ?? strtolower($service->state);

        return [
            'id' => $service->id, 'product_key' => $service->product_key, 'family' => $service->family, 'name' => $service->name, 'label' => $service->label, 'hostname' => $service->hostname,
            'state' => $service->state, 'ui' => $ui, 'region' => $service->region_code, 'sla_class' => $service->sla_class, 'entitlements' => $service->entitlements, 'access' => $service->tags['access'] ?? [], 'migration' => ServiceMigrationService::status($service),
            'health' => $service->health, 'freshness' => ServiceFreshness::of($service), 'control_plane' => ControlPlaneStatus::of($service), 'suspension' => SuspensionHold::of($service), 'activated_at' => $service->activated_at?->toIso8601String(), 'suspended_at' => $service->suspended_at?->toIso8601String(), 'suspended_reason' => $service->suspended_reason,
            // a cancelled service is only deactivated and waits out its restore window (audit §5ab)
            'deletion' => $service->terminate_at === null ? null : [
                'grace_until' => $service->terminate_at->toIso8601String(), 'days_left' => max(0, (int) now()->diffInDays($service->terminate_at, false)),
                'archive_backup_id' => data_get($service->tags, 'deletion.archive_backup_id'), 'reason' => data_get($service->tags, 'deletion.reason'),
                'pay_to_restore' => app(ServiceReinstatement::class)->enabled(), // TASK-0025: the panel offers "Zaplatit a obnovit" only while the rule is on
            ],
            // what the world answers for the customer's domain, against what the platform published for it (PublicDnsCheck)
            'dns' => self::dnsCheck($service),
            'terminate_at' => $service->terminate_at?->toIso8601String(), 'subscription_id' => $service->subscription_id, 'project_id' => $service->project_id, 'created_at' => $service->created_at?->toIso8601String(),
        ];
    }

    /**
     * The problems the daily look found in public DNS, so the panel can show them without asking a resolver while
     * somebody waits. Null when the domain answers the way it should.
     *
     * @return array{problems:list<array<string,string>>, checked_at:?string}|null
     */
    private static function dnsCheck(Service $service): ?array
    {
        $check = (array) data_get($service->tags, 'dns_check', []);
        $problems = array_values(array_filter((array) ($check['problems'] ?? []), 'is_array'));

        return $problems === [] ? null : ['problems' => $problems, 'checked_at' => isset($check['checked_at']) ? (string) $check['checked_at'] : null];
    }

    /** @param bool $reveal whether the reader manages the service: only then is a secret the run generated shown, and only while its window lasts (OperationSecrets) */
    public static function operation(Operation $operation, bool $staff = false, bool $reveal = false): array
    {
        $out = [
            'id' => $operation->id, 'kind' => $operation->kind, 'state' => $operation->state, 'step' => $operation->step, 'steps_total' => $operation->steps_total, 'step_label' => $operation->step_label,
            'service_id' => $operation->service_id, 'domain_id' => $operation->domain_id, 'order_item_id' => $operation->order_item_id, 'attempts' => $operation->attempts,
            'queued_at' => $operation->queued_at?->toIso8601String(), 'started_at' => $operation->started_at?->toIso8601String(), 'finished_at' => $operation->finished_at?->toIso8601String(), 'next_run_at' => $operation->next_run_at?->toIso8601String(),
            'error' => $operation->error ? ['message' => $staff ? ($operation->error['message'] ?? null) : self::customerError((string) ($operation->error['message'] ?? '')), 'retryable' => $operation->error['retryable'] ?? null] : null,
            'action' => is_array($operation->desired) ? ($operation->desired['action'] ?? null) : null,
            'result' => self::customerResult($operation, $reveal && ! $staff),
        ];
        if ($staff) {
            $out += ['workflow' => $operation->workflow, 'organization_id' => $operation->organization_id, 'provider_instance_id' => $operation->provider_instance_id, 'queue' => $operation->queue, 'correlation_id' => $operation->correlation_id, 'trace_url' => Tracer::urlFor($operation->correlation_id), 'actor' => [$operation->actor_type, $operation->actor_id], 'context' => OperationSecrets::forget((array) $operation->context), // staff work on the run, not on the customer's passwords
                'error_detail' => $operation->error];
        }

        return $out;
    }

    /**
     * The part of an operation's outcome a customer may see: terminal output, export tokens, deploy/staging/import
     * summaries — never raw provider payloads.
     *
     * @return array<string,mixed>|null
     */
    public static function customerResult(Operation $operation, bool $reveal = false): ?array
    {
        $context = is_array($operation->result) ? $operation->result : (is_array($operation->context) ? $operation->context : []);
        $last = is_array($context['last_result'] ?? null) ? $context['last_result'] : [];
        $keys = ['exit_code', 'output', 'duration_ms', 'timed_out', 'download_token', 'download_name', 'size_bytes', 'log', 'release', 'sha', 'staging_domain', 'staging_service_id', 'installed', 'enabled', 'purged', 'plugin', 'op', 'deployment_id', 'import_id', 'certificate_id', 'expires_at', 'nameservers', 'state', 'lines', 'deleted', 'created', 'imported', 'restored', 'backup_id', 'production_version', 'staging_version', 'admin_url', 'admin_user', 'admin_password'];
        $out = array_intersect_key(array_merge($last, $context), array_flip($keys));
        // what the run generated is read once: by somebody who manages the service, while the window lasts. Everybody who may
        // list operations — a read-only viewer included — used to read the administrator password of the site here, for good.
        $until = OperationSecrets::revealUntil($operation);
        foreach (OperationSecrets::REVEALED as $secret) {
            if (! array_key_exists($secret, $out)) {
                continue;
            }
            if (! $reveal || $until === null || $out[$secret] === OperationSecrets::GONE) {
                unset($out[$secret]);
            } else {
                $out['reveal_until'] = $until->toIso8601String();
            }
        }

        return $out === [] ? null : $out;
    }

    /** Customers get the failure class of an operation, never the vendor panel or its raw message. */
    public static function customerError(string $message): ?string
    {
        if ($message === '') {
            return null;
        }
        if (preg_match('/^\[(\w+):(\w+)\]/', $message, $m)) {
            return match ($m[2]) {
                'AUTH' => 'Provozní systém odmítl přístup; řešíme s technikem.',
                'CAPACITY' => 'Na serveru není volná kapacita; požadavek zkusíme znovu.',
                'TRANSIENT', 'RATE_LIMIT', 'CIRCUIT_OPEN' => 'Server dočasně neodpovídá; požadavek zkusíme znovu.',
                'VALIDATION' => 'Server požadavek odmítl kvůli neplatnému nastavení.',
                'NOT_FOUND' => 'Zdroj na serveru už neexistuje.',
                'CONFLICT' => 'Zdroj se stejným názvem už existuje.',
                default => 'Zásah na serveru selhal; řešíme s technikem.',
            };
        }

        return preg_replace('/\b(aapanel|aaPanel|ISPConfig|ispconfig|Proxmox|proxmox|Pterodactyl|pterodactyl|PowerDNS|powerdns|WEDOS|Wedos|wedos|WAPI|Subreg|subreg|SUBREG|Wings|wings)\b/', 'server', $message) ?? $message;
    }

    public static function order(Order $order, bool $withItems = true): array
    {
        $out = [
            'id' => $order->id, 'number' => $order->number, 'state' => $order->state, 'currency' => $order->currency, 'subtotal' => self::money((int) $order->subtotal_minor, $order->currency), 'discount' => self::money((int) $order->discount_minor, $order->currency),
            'tax' => self::money((int) $order->tax_minor, $order->currency), 'total' => self::money((int) $order->total_minor, $order->currency), 'payment_mode' => $order->payment_mode, 'commit_months' => $order->commit_months,
            'placed_at' => $order->placed_at?->toIso8601String(), 'paid_at' => $order->paid_at?->toIso8601String(), 'activated_at' => $order->activated_at?->toIso8601String(), 'invoice_id' => $order->invoice_id, 'payment_intent_id' => $order->payment_intent_id,
        ];
        if ($withItems) {
            $out['items'] = $order->items()->get()->map(fn (OrderItem $i) => self::orderItem($i))->all();
        }
        $out['provisioning'] = self::provisioning($order);
        $review = is_array($order->meta['review'] ?? null) ? $order->meta['review'] : null; // intake pre-check (audit §5f-8): the customer sees "checking", staff see the score and reasons
        $out['review'] = $review === null ? null : ['state' => $review['state'] ?? 'pending', 'score' => $review['score'] ?? null, 'reasons' => $review['reasons'] ?? [], 'opened_at' => $review['opened_at'] ?? null, 'decided_at' => $review['decided_at'] ?? null];
        $approval = CreditOrderApprovals::of($order); // a credit order waiting for the owner or a billing admin (TASK-0021)
        $out['approval'] = $approval === [] ? null : ['state' => $approval['state'] ?? 'pending', 'requester' => isset($approval['requester_id']) ? ['id' => $approval['requester_id'], 'name' => $approval['requester_name'] ?? null] : null,
            'opened_at' => $approval['opened_at'] ?? null, 'decided_at' => $approval['decided_at'] ?? null, 'decided_by' => isset($approval['decided_by']) ? ['id' => $approval['decided_by'], 'name' => $approval['decider_name'] ?? null] : null, 'reason' => $approval['reason'] ?? null];

        return $out;
    }

    /**
     * How the fulfilment of a paid order is going: the operations still in flight and whether one of them is stalled
     * (waiting on a transient node error and retrying) — the panel then says "taking longer than usual, retrying"
     * instead of promising the usual 90 seconds.
     *
     * @return array{active:int, stalled:bool, since:?string, next_run_at:?string}|null
     */
    private static function provisioning(Order $order): ?array
    {
        if ($order->state !== OrderStateMachine::PROVISIONING) {
            return null;
        }
        $active = Operation::query()->whereIn('order_item_id', $order->items()->pluck('id'))->whereIn('state', [Operation::PENDING, Operation::RUNNING, Operation::WAITING])->orderBy('queued_at')->get();
        $stalled = $active->first(fn (Operation $o) => $o->state === Operation::WAITING && ((bool) ($o->error['retryable'] ?? false) || (int) $o->attempts > 1));

        return ['active' => $active->count(), 'stalled' => $stalled !== null, 'since' => $stalled?->queued_at?->toIso8601String(), 'next_run_at' => $stalled?->next_run_at?->toIso8601String()];
    }

    public static function orderItem(OrderItem $item): array
    {
        $currency = (string) ($item->config['currency'] ?? 'CZK');

        return ['id' => $item->id, 'sku' => $item->sku, 'product_key' => $item->product_key, 'name' => $item->name, 'qty' => $item->qty, 'period' => $item->period, 'unit_net' => self::money((int) $item->unit_net_minor, $currency), 'total' => self::money((int) $item->total_minor, $currency), 'state' => $item->state, 'service_id' => $item->service_id, 'domain_id' => $item->domain_id, 'config' => array_diff_key((array) $item->config, array_flip(['entitlements', 'registrant']))];
    }

    public static function invoice(Invoice $invoice, bool $withLines = false, bool $forStaff = false): array
    {
        $out = [
            'id' => $invoice->id, 'number' => $invoice->number, 'type' => $invoice->type, 'series' => $invoice->series, 'state' => $invoice->state, 'currency' => $invoice->currency,
            'subtotal' => self::money((int) $invoice->subtotal_minor, $invoice->currency), 'tax' => self::money((int) $invoice->tax_minor, $invoice->currency), 'total' => self::money((int) $invoice->total_minor, $invoice->currency), 'paid' => self::money((int) $invoice->paid_minor, $invoice->currency),
            'issued_at' => $invoice->issued_at?->toIso8601String(), 'due_at' => $invoice->due_at?->toIso8601String(), 'paid_at' => $invoice->paid_at?->toIso8601String(), 'payment_method' => $invoice->payment_method, 'payment_reference' => $invoice->payment_reference,
            'order_id' => $invoice->order_id, 'corrects_invoice_id' => $invoice->corrects_invoice_id, 'pdf' => $invoice->pdf_hash !== null, 'buyer' => $forStaff ? $invoice->buyer : self::invoiceBuyer((array) $invoice->buyer), 'tax_summary' => $invoice->tax_summary, 'green' => data_get($invoice->meta, 'green'),
            // a tax document in another currency: its VAT in CZK at the national bank's rate (null for CZK documents; `pending` while the rate is not known yet)
            'czk' => data_get($invoice->meta, 'czk'), 'czk_pending' => (bool) data_get($invoice->meta, 'czk_pending', false),
        ];
        if ($withLines) {
            $out['lines'] = $invoice->lines()->get()->map(fn ($l) => ['position' => $l->position, 'sku' => $l->sku, 'description' => $l->description, 'qty' => $l->qty, 'unit' => $l->unit, 'unit_net' => self::money((int) $l->unit_net_minor, $invoice->currency), 'net' => self::money((int) $l->net_minor, $invoice->currency), 'tax_rate' => $l->tax_rate, 'tax_category' => $l->tax_category, 'tax' => self::money((int) $l->tax_minor, $invoice->currency), 'total' => self::money((int) $l->total_minor, $invoice->currency), 'period_from' => $l->period_from, 'period_to' => $l->period_to])->all();
        }

        return $out;
    }

    /**
     * The buyer as the customer sees it (TASK-0031 review round 2): the VIES check reduced to what the document prints — when it
     * was checked, the consultation number, and whether it rests on VIES or on evidence staff accepted. Who VIES names as the
     * holder (name_mismatch), the reason and the stored status are for finance: the cart quote does not tip off somebody using
     * another trader's number, and neither does the invoice.
     *
     * @param  array<string,mixed>  $buyer
     * @return array<string,mixed>
     */
    private static function invoiceBuyer(array $buyer): array
    {
        if (! is_array($buyer['vat_check'] ?? null)) {
            return $buyer;
        }
        $check = $buyer['vat_check'];
        $buyer['vat_check'] = ['checked_at' => $check['checked_at'] ?? null, 'consultation_number' => $check['consultation_number'] ?? null,
            'source' => ($check['source'] ?? null) === 'staff' ? 'staff' : (empty($check['checked_at']) ? null : 'vies')];

        return $buyer;
    }

    public static function domain(Domain $domain): array
    {
        return [
            'id' => $domain->id, 'fqdn' => $domain->fqdn_ascii, 'unicode' => $domain->fqdn_unicode, 'tld' => $domain->tld, 'state' => $domain->state, 'renewal_bucket' => $domain->renewalBucket(), 'days_to_expiry' => $domain->daysToExpiry(),
            'registered_at' => $domain->registered_at?->toIso8601String(), 'expires_at' => $domain->expires_at?->toIso8601String(), 'auto_renew' => (bool) $domain->auto_renew, 'renewal_period' => $domain->renewal_period, 'transfer_lock' => (bool) $domain->transfer_lock, 'dnssec' => (bool) $domain->dnssec,
            'dns_provider' => $domain->dns_provider === null ? null : (in_array($domain->dns_provider, ['external', 'none'], true) ? $domain->dns_provider : (data_get($domain->meta, 'source') === 'connection' ? 'connected' : 'onhost')), // customers see whose DNS it is, never the software behind it
            'source' => (string) data_get($domain->meta, 'source', 'onhost'), 'connection_id' => data_get($domain->meta, 'connection_id'), 'registrar_account' => data_get($domain->meta, 'registrar_account'),
            'paired_service_id' => data_get($domain->meta, 'paired_service_id'), 'pairing' => data_get($domain->meta, 'pairing'), 'missing_since' => data_get($domain->meta, 'missing_since'), 'dns_zone_id' => $domain->dns_zone_id, 'nameservers' => $domain->nameservers, 'registrant_contact_id' => $domain->registrant_contact_id, 'critical' => (bool) $domain->critical, 'subscription_id' => $domain->subscription_id, 'last_reconciled_at' => $domain->last_reconciled_at?->toIso8601String(),
        ];
    }

    public static function registrarConnection(RegistrarConnection $connection, ?int $domains = null): array
    {
        $stats = (array) $connection->stats;

        return [
            'id' => $connection->id, 'provider' => $connection->provider, 'provider_label' => 'WEDOS', 'label' => $connection->label, 'login' => $connection->login, 'customer_number' => $connection->customer_number,
            'state' => $connection->state, 'settings' => array_merge(RegistrarConnection::DEFAULT_SETTINGS, (array) $connection->settings), 'credit' => $stats['credit'] ?? null,
            'domains' => $domains ?? ($stats['domains'] ?? null), 'domains_remote' => $stats['domains_remote'] ?? null, 'zones' => $stats['zones'] ?? null,
            'last_error' => $connection->last_error, 'last_probed_at' => $connection->last_probed_at?->toIso8601String(), 'last_synced_at' => $connection->last_synced_at?->toIso8601String(),
            'staff_disabled' => $stats['staff_disabled'] ?? null, 'created_at' => $connection->created_at?->toIso8601String(),
        ];
    }

    public static function zone(DnsZone $zone, bool $withRecords = true): array
    {
        $out = ['id' => $zone->id, 'name' => $zone->name, 'provider' => $zone->provider, 'state' => $zone->state, 'serial' => $zone->serial, 'version' => $zone->version, 'dnssec' => (bool) $zone->dnssec, 'ds' => $zone->dnssec_ds ?? [], 'nameservers' => $zone->nameservers, 'domain_id' => $zone->domain_id, 'committed_at' => $zone->committed_at?->toIso8601String(), 'pending_changes' => $zone->pendingChanges()->count()];
        $out['drift'] = ['checked_at' => $zone->drift_checked_at?->toIso8601String(), 'differs' => $zone->drift !== null, 'summary' => $zone->drift, 'error' => $zone->getAttribute('drift_error')];
        if ($withRecords) {
            $out['records'] = $zone->records()->get()->map(fn (DnsRecord $r) => self::record($r))->all();
            $out['changes'] = $zone->pendingChanges()->get()->map(fn (DnsChange $c) => ['id' => $c->id, 'op' => $c->op, 'record' => $c->record, 'previous' => $c->previous, 'reason' => $c->reason, 'created_at' => $c->created_at?->toIso8601String()])->all();
        }

        return $out;
    }

    public static function record(DnsRecord $record): array
    {
        return ['id' => $record->id, 'name' => $record->name, 'type' => $record->type, 'content' => $record->content, 'ttl' => $record->ttl, 'prio' => $record->prio, 'managed_by' => $record->managed_by, 'protected' => (bool) $record->protected, 'comment' => $record->comment];
    }

    public static function providerInstance(ProviderInstance $instance, ?array $health = null): array
    {
        $gate = (array) ($instance->version_gate ?? []);

        return ['id' => $instance->id, 'key' => $instance->key, 'provider' => $instance->provider, 'name' => $instance->name, 'region' => $instance->region_code, 'state' => $instance->state, 'capabilities' => $instance->capabilities, 'vendor_version' => $instance->vendor_version, 'adapter_version' => $instance->adapter_version, 'health' => $health ?? $instance->health, 'health_checked_at' => $instance->health_checked_at?->toIso8601String(), 'maintenance_until' => $instance->maintenance_until?->toIso8601String(), 'state_reason' => $instance->state_reason,
            // what the platform concluded about the version the panel runs (PanelVersionGate): held = no new orders go there
            'usable' => $instance->isUsable(), 'version_gate' => $gate === [] ? null : array_intersect_key($gate, array_flip(['state', 'version', 'declared', 'previous', 'since', 'checked_at', 'why', 'reason', 'accepted_by', 'accepted_at', 'evidence']))];
    }

    public static function drift(ResourceDrift $drift): array
    {
        return ['id' => $drift->id, 'service_id' => $drift->service_id, 'field' => $drift->field, 'ownership' => $drift->ownership, 'classification' => $drift->classification, 'expected' => $drift->expected['value'] ?? null, 'actual' => $drift->actual['value'] ?? null, 'state' => $drift->state, 'detected_at' => $drift->detected_at?->toIso8601String(), 'resolved_at' => $drift->resolved_at?->toIso8601String(), 'resolved_by' => $drift->resolved_by, 'resolution' => $drift->resolution];
    }
}
