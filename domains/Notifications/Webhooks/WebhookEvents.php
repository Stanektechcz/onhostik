<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications\Webhooks;

use Onhost\Platform\Errors\DomainError;

/**
 * Which platform events a customer webhook may carry, and which fields of each (D4). The list is an ALLOW-list: an event
 * that is not named here never leaves the platform, and of a named event only the named fields do. The outbox payload is
 * written for the platform's own consumers — operation ids, node and vendor names, staff names, provider error text, fraud
 * scores — and used to go on the wire as it was, to any HTTPS address a customer typed in.
 *
 * Field specs: `name` copies a scalar, a list of scalars or a money value ({minor, currency}); any other nested value is
 * dropped. `name:code` copies only a short machine code (`dunning`, `year`), never free text. `parent.child` picks one value
 * out of a nested array (`access.ipv4`).
 *
 * Kept out on purpose: reconciliation and drift events, provider/registry notices, the staff side of an event (escalations,
 * assignments, settlement and fulfilment failures, SLA burn), node moves and leftovers, and every `error` string of
 * the platform's own work (the one `error` kept is an uptime check's answer from the customer's own address).
 */
final class WebhookEvents
{
    /** Event families a customer may subscribe to with `family.*` (the first segment of the event name). */
    public const FAMILIES = ['order', 'service', 'invoice', 'wallet', 'domain', 'dns', 'subscription', 'dunning', 'ticket', 'incident', 'maintenance', 'app', 'backup', 'sla', 'monitoring', 'deploy', 'staging', 'import', 'certificate', 'cdn'];

    /** Sent by `webhook.ping` only; never subscribable, always delivered to the endpoint that asked for it. */
    public const PING = 'webhook.ping';

    private const ORDER_STATE = ['number', 'from:code'];

    private const SERVICE_STATE = ['product_key:code', 'state:code', 'reason:code'];

    private const TICKET_STATE = ['number', 'subject', 'from:code', 'note'];

    private const DUNNING_STATE = ['invoice_id', 'service_id', 'due_at', 'from:code'];

    private const INCIDENT = ['number', 'title', 'severity:code', 'components', 'impact:code', 'state:code', 'state_label', 'note', 'duration'];

    private const ACCESS_GRANT = ['grant_id', 'service', 'email', 'state:code', 'capabilities', 'dropped', 'expires_at'];

    private const LIMIT_RAISE = ['label', 'metric:code', 'metric_label', 'delta', 'new_value', 'addon_service_id'];

    private const WORK_OFFER = ['offer_id', 'number', 'subject', 'scope', 'price', 'valid_until', 'invoice_id', 'invoice_number', 'payment:code'];

    /** @var array<string, list<string>> */
    private const EVENTS = [
        // ── orders ──
        'order.placed' => ['number', 'state:code', 'total', 'mode:code', 'approval:code'],
        'order.paid' => ['number', 'invoice_id', 'released'],
        'order.provisioning' => self::ORDER_STATE,
        'order.active' => self::ORDER_STATE,
        'order.suspended' => self::ORDER_STATE,
        'order.cancelled' => ['number', 'from:code', 'returned', 'to:code', 'credit_notes'],
        'order.refunded' => ['number', 'amount', 'to:code', 'credit_note', 'items', 'nothing_delivered'],
        'order.approval.required' => ['number', 'total', 'mode:code'],
        'order.approval.approved' => ['number'],
        'order.approval.rejected' => ['number', 'reason'],
        'order.approval.expired' => ['number', 'days'],
        'order.review.required' => ['number'], // the score and its reasons are the intake check's, not the customer's
        'order.review.released' => ['number'],
        'order.review.rejected' => ['number', 'reason'],
        // ── services ──
        'service.created' => ['product_key:code', 'family:code'],
        'service.activated' => ['product_key:code', 'family:code', 'parent_service_id', 'access.domain', 'access.ipv4', 'access.ipv6', 'access.address', 'access.namespace'],
        'service.failed' => ['product_key:code'],
        'service.active' => self::SERVICE_STATE,
        'service.suspended' => self::SERVICE_STATE,
        'service.terminated' => self::SERVICE_STATE,
        'service.plan_changed' => ['product_key:code', 'from_plan:code', 'to_plan:code', 'plan_name', 'hostname', 'label', 'period:code', 'period_change', 'current_period_end'],
        'service.deletion.scheduled' => ['product_key:code', 'grace_until', 'grace_days', 'retention_days', 'reason:code'],
        'service.deletion.cancelled' => ['product_key:code', 'grace_until'],
        'service.reinstated' => ['label', 'amount', 'period_end'],
        'service.reinstatement.awaiting_payment' => ['label', 'amount', 'shortfall', 'grace_until'],
        'service.reinstatement.dropped' => ['label', 'why:code', 'grace_until'],
        'service.migration.scheduled' => ['label', 'starts_at', 'reason:code'],
        'service.migration.rescheduled' => ['label', 'starts_at'],
        'service.migrated' => ['label', 'hostname', 'family:code'],
        'service.usage.high' => ['level:code', 'hostname', 'label', 'top.key', 'top.pct'],
        'service.limit_raised' => self::LIMIT_RAISE,
        'service.limit_raise_ended' => self::LIMIT_RAISE,
        'service.disk_total.announced' => ['effective', 'hostname', 'label', 'files', 'databases', 'mail', 'total', 'limit', 'pct', 'quality:code', 'over'],
        'service.site.created' => ['site_service_id', 'domain', 'php_version'],
        'service.site.removed' => ['site_service_id', 'domain'],
        'service.site.failed' => ['action:code', 'domain'],
        'service.rescue.started' => ['until', 'hours'],
        'service.rescue.ended' => ['reason:code'],
        'service.running_again' => ['minutes'],
        'service.stopped_unexpectedly' => ['status:code'],
        'service.certificate.problem' => ['name'],
        'service.dns.problem' => ['hostname', 'label', 'kinds'],
        'service.integrity.suspicious' => ['hostname', 'label', 'kinds'],
        'service.access.granted' => self::ACCESS_GRANT,
        'service.access.reduced' => self::ACCESS_GRANT,
        'service.access.revoked' => self::ACCESS_GRANT,
        'service.access.expired' => self::ACCESS_GRANT,
        'service.archive.restored' => ['backup_id', 'files', 'databases'],
        'service.archive.downloaded' => ['backup_id', 'bytes', 'fee'],
        'service.final_archive.created' => ['backup_id', 'bytes', 'retention_until'],
        'service.backup.schedule.paused' => ['label', 'frequency:code', 'failures'],
        'service.backup.schedule.stalled' => ['label', 'frequency:code', 'missed'],
        'service.database.import.failed' => ['label', 'database', 'restored'],
        'service.staff_panel_login' => ['ticket_number', 'reason', 'consented', 'at'], // who of the staff stays inside
        // ── invoices and money ──
        'invoice.issued' => ['number', 'type:code', 'total', 'due_at'],
        'invoice.paid' => ['number', 'type:code', 'amount'], // the method can name the payment gateway
        'invoice.overdue' => ['number', 'due_at', 'outstanding'],
        'invoice.cancelled' => ['number', 'type:code'],
        'wallet.topup.completed' => ['topup_id', 'amount', 'purpose:code', 'balance'], // the source can name the payment gateway
        'wallet.charged' => ['hold_id', 'amount', 'purpose:code'],
        'wallet.runway.low' => ['days', 'depletes_at', 'shortfall', 'available'],
        'wallet.refund.requested' => ['refund_id', 'amount'],
        'wallet.frozen' => ['reason:code'],
        'wallet.hold.released' => ['hold_id', 'reason:code'],
        'subscription.created' => ['service_id', 'period:code', 'amount', 'metered', 'current_period_end'],
        'subscription.renewed' => ['service_id', 'invoice_id', 'mode:code', 'period_end', 'amount', 'reinstated'],
        'subscription.renewal_failed' => ['service_id', 'required', 'cause:code', 'period_end', 'attempt'],
        'subscription.expired' => ['service_id'],
        'subscription.plan_changed' => ['service_id', 'period:code', 'amount', 'from_plan:code', 'to_plan:code', 'period_change', 'current_period_end'],
        'subscription.cancel_scheduled' => ['service_id', 'period_end'],
        'subscription.cancel_revoked' => ['service_id', 'period_end'],
        'dunning.opened' => self::DUNNING_STATE,
        'dunning.notice' => self::DUNNING_STATE,
        'dunning.overdue_notice' => self::DUNNING_STATE,
        'dunning.grace' => self::DUNNING_STATE,
        'dunning.suspended' => self::DUNNING_STATE,
        'dunning.termination_scheduled' => self::DUNNING_STATE,
        'dunning.terminated' => self::DUNNING_STATE,
        'dunning.resolved' => ['invoice_id', 'service_id', 'reason:code'],
        'sla.credit.issued' => ['amount', 'percent', 'service_id', 'credit_note_id'],
        // ── domains and DNS ──
        'domain.registration_requested' => ['fqdn'],
        'domain.registration_failed' => ['fqdn'],
        'domain.renewed' => ['fqdn', 'expires_at', 'years'],
        'domain.expired' => ['fqdn', 'expires_at'],
        'domain.renewal_notice' => ['fqdn', 'days_left', 'expires_at', 'auto_renew'],
        'domain.renewal_failed' => ['fqdn'],
        'domain.renewal_payment_failed' => ['fqdn', 'days_left', 'in_grace', 'grace_days_left'],
        'domain.renewal_abandoned' => ['fqdn'],
        'domain.nameservers_changed' => ['fqdn', 'nameservers'],
        'domain.auto_renew_changed' => ['fqdn', 'enabled'],
        'domain.dnssec_published' => ['fqdn'],
        'domain.auth_info_requested' => ['fqdn', 'delivery:code'],
        'domain.transfer.code_needed' => ['fqdn', 'awaiting_since', 'wait_days'],
        'domain.transferred_out' => ['fqdn'],
        'domain.closed' => ['fqdn', 'state:code', 'reason:code'],
        'domain.reopened' => ['fqdn'],
        'domain.imported' => ['fqdn', 'expires_at'],
        'domain.external_expiry_notice' => ['fqdn', 'days_left', 'expires_at'], // the registrar and the account stay inside
        'domain.paired' => ['fqdn', 'service_id', 'hostname'],
        'domain.unpaired' => ['fqdn', 'service_id', 'hostname'],
        'dns.zone.created' => ['name'],
        'dns.zone.deleted' => ['name', 'reason:code'],
        'dns.zone.committed' => ['name', 'version', 'serial'],
        'dns.dnssec.enabled' => ['name', 'ds'],
        'dns.dnssec.disabled' => ['name'],
        // ── support, status ──
        'ticket.created' => ['number', 'subject', 'priority:code', 'category:code', 'channel:code'],
        'ticket.replied' => ['number', 'subject', 'state:code', 'author_type:code'],
        'ticket.handoff' => ['number'],
        'ticket.waiting_customer' => self::TICKET_STATE,
        'ticket.resolved' => self::TICKET_STATE,
        'ticket.closed' => self::TICKET_STATE,
        'ticket.open' => self::TICKET_STATE,
        'ticket.work_offer.proposed' => self::WORK_OFFER,
        'ticket.work_offer.approved' => self::WORK_OFFER,
        'ticket.work_offer.declined' => self::WORK_OFFER,
        'ticket.work_offer.completed' => self::WORK_OFFER,
        'incident.opened' => self::INCIDENT,
        'incident.updated' => self::INCIDENT,
        'incident.resolved' => self::INCIDENT,
        'maintenance.scheduled' => ['number', 'title', 'components', 'starts_at', 'ends_at', 'impact:code'],
        // ── web toolkit ──
        'monitoring.down' => ['monitor_id', 'url', 'error'], // what the customer's own address answered (HTTP 503, a timeout)
        'monitoring.up' => ['monitor_id', 'url', 'minutes'],
        'deploy.started' => ['deployment_id', 'ref', 'trigger:code'],
        'deploy.succeeded' => ['deployment_id', 'ref', 'sha', 'release'],
        'deploy.failed' => ['deployment_id', 'ref', 'sha'],
        'app.deployed' => ['deployment_id', 'digest'],
        'staging.created' => ['staging_service_id', 'domain'],
        'staging.deleted' => ['staging_service_id'],
        'staging.synced' => ['staging_service_id', 'domain', 'mode:code'],
        'staging.pushed' => ['staging_service_id', 'domain', 'mode:code'],
        'staging.failed' => ['action:code'],
        'import.started' => ['import_id', 'kind:code'],
        'import.succeeded' => ['import_id', 'kind:code', 'stats.files', 'stats.databases'],
        'import.failed' => ['import_id', 'kind:code'],
        'backup.deleted' => ['backup_id', 'reason:code'],
        'backup.offsite' => ['backup_id'],
        'certificate.requested' => ['certificate_id', 'domains'],
        'certificate.issued' => ['certificate_id', 'domains', 'expires_at'],
        'certificate.failed' => ['certificate_id', 'domains', 'renewal'],
        'cdn.enabled' => ['domain', 'nameservers', 'nameservers_switched', 'state:code'],
        'cdn.activated' => ['domain'],
        'cdn.disabled' => ['domain', 'nameservers_switched'],
    ];

    public static function isPublic(string $event): bool
    {
        return isset(self::EVENTS[$event]);
    }

    /** @return list<string>|null the field specs of a public event; null for any other event */
    public static function fields(string $event): ?array
    {
        return self::EVENTS[$event] ?? null;
    }

    /** @return list<string> every event a webhook can carry, sorted */
    public static function catalog(): array
    {
        $events = array_keys(self::EVENTS);
        sort($events);

        return $events;
    }

    /**
     * The subscription patterns a customer may store: `*`, `family.*`, or one event of the catalogue.
     *
     * @param  list<mixed>  $patterns
     * @return list<string> the same patterns, de-duplicated (`*` when none were given)
     *
     * @throws DomainError webhook_event_unknown (422) naming what is not an event a webhook can carry
     */
    public static function normalize(array $patterns): array
    {
        $patterns = array_values(array_unique(array_map(fn ($p) => is_string($p) ? trim($p) : '', $patterns)));
        if ($patterns === []) {
            return ['*'];
        }
        $unknown = array_values(array_filter($patterns, fn (string $p) => ! self::validPattern($p)));
        if ($unknown !== []) {
            throw new DomainError('webhook_event_unknown', 'Not an event a webhook can carry: '.implode(', ', array_slice($unknown, 0, 10)), 422, ['unknown' => array_slice($unknown, 0, 10), 'families' => self::FAMILIES]);
        }

        return in_array('*', $patterns, true) ? ['*'] : $patterns;
    }

    private static function validPattern(string $pattern): bool
    {
        if ($pattern === '*' || isset(self::EVENTS[$pattern])) {
            return true;
        }

        return str_ends_with($pattern, '.*') && in_array(substr($pattern, 0, -2), self::FAMILIES, true);
    }
}
