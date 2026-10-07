<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Onhost\Domain\Compliance\Models\AbuseCase;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff READING a customer's data is an event too. Every write goes through the command bus and leaves a trail; a look at
 * a customer's account — the members, the credit, the documents, a ticket's conversation, the mail queue — left none, so
 * "who opened this customer's account last week" had no answer, for us or for the customer.
 *
 * One event per person and thing per quarter of an hour (a console that refreshes itself does not flood the trail). An
 * event scoped to an organization is part of THAT organization's audit trail: the customer sees that support opened
 * their account, when, and from which screen — the same way they see what support changed.
 *
 * G7 (TASK-0115): four controllers recorded their look; every other staff screen over customer data (orders, services,
 * domains, payments, partners, withdrawals, leads …) left nothing. The staff guard (EnsureStaff) now asks `afterResponse()`
 * for every staff request: a successful GET of a route in ROUTES is recorded, unless its controller already recorded a finer
 * event of its own. Every GET under /v1/staff is either in ROUTES or in EXEMPT with the reason it holds no customer data —
 * StaffReadAuditSweepTest fails on a new route that is in neither, so a new staff screen cannot be forgotten silently.
 */
final class StaffReadAudit
{
    private const WINDOW_SECONDS = 900;

    /** Request attribute set once a read of this request is recorded: the guard's general record does not repeat a controller's. */
    private const RECORDED = 'onhost.staff_read_recorded';

    /**
     * GET routes under /v1/staff that show customer data → [what, resource type, model whose row names the organization].
     * The model, given, is looked up by the route's only parameter: the event lands in that organization's trail too.
     *
     * @var array<string, array{0:string, 1:?string, 2:?class-string<Model>}>
     */
    public const ROUTES = [
        'v1/staff/abuse-cases' => ['abuse_cases', null, null],
        'v1/staff/abuse-cases/{case}' => ['abuse_case', 'abuse_case', AbuseCase::class],
        'v1/staff/approvals' => ['approvals', null, null], // a request carries the customer's action and its payload
        'v1/staff/bulk-jobs' => ['bulk_jobs', null, null],
        'v1/staff/bulk-jobs/{job}' => ['bulk_job', 'bulk_job', null],
        'v1/staff/capacity/requests' => ['capacity_requests', null, null],
        'v1/staff/chargebacks' => ['chargebacks', null, null],
        'v1/staff/compliance/timers' => ['compliance_timers', null, null],
        'v1/staff/customers' => ['customers', null, null],
        'v1/staff/customers/{organization}' => ['customer', 'organization', Organization::class], // the controller records it itself
        'v1/staff/data-requests' => ['data_requests', null, null],
        'v1/staff/domains' => ['domains', null, null],
        'v1/staff/dunning' => ['dunning', null, null],
        'v1/staff/integrations/{instance}' => ['integration', 'provider_instance', null], // the controller records it itself
        'v1/staff/leads' => ['leads', null, null], // a lead is a person's name and contact
        'v1/staff/marketplace/listings' => ['marketplace_listings', null, null],
        'v1/staff/marketplace/orders' => ['marketplace_orders', null, null],
        'v1/staff/orders' => ['orders', null, null],
        'v1/staff/orders/risk-review' => ['orders_risk_review', null, null],
        'v1/staff/outbox' => ['mail_outbox', 'mail_outbox', null], // the controller records it itself
        'v1/staff/partners' => ['partners', null, null],
        'v1/staff/partners/payouts' => ['partner_payouts', null, null],
        'v1/staff/partners/requests' => ['partner_requests', null, null],
        'v1/staff/partners/{partner}' => ['partner', 'partner', Partner::class],
        'v1/staff/payments/bank' => ['bank_payments', null, null],
        'v1/staff/payments/refunds' => ['payment_refunds', null, null], // G6: refunds of customers' payments, the bank payouts still to send
        'v1/staff/payments/refunds/not-paid-out' => ['payment_refunds_not_paid_out', null, null], // H3: cancelled payouts the customers are still owed
        'v1/staff/provisioning/board' => ['operations_board', null, null],
        'v1/staff/provisioning/deletions' => ['deletions', null, null],
        'v1/staff/provisioning/jobs' => ['operations', null, null],
        'v1/staff/provisioning/jobs/{operation}' => ['operation', 'operation', Operation::class],
        'v1/staff/provisioning/ssh-key-revocations' => ['ssh_key_revocations', null, null],
        'v1/staff/referrals' => ['referrals', null, null],
        'v1/staff/renewals' => ['renewals', null, null],
        'v1/staff/resource-mappings' => ['resource_mappings', null, null],
        'v1/staff/security/incidents' => ['cyber_incidents', null, null],
        'v1/staff/security/incidents/{case}' => ['cyber_incident', 'cyber_incident', null],
        'v1/staff/services' => ['services', null, null],
        'v1/staff/sla-credits' => ['sla_credits', null, null],
        'v1/staff/tickets' => ['tickets', null, null],
        'v1/staff/tickets/clusters' => ['ticket_clusters', null, null], // clusters quote the customers' subjects
        'v1/staff/tickets/{ticket}' => ['ticket', 'ticket', Ticket::class], // the controller records it itself
        'v1/staff/tickets/{ticket}/work-offers' => ['ticket_work_offers', 'ticket', Ticket::class],
        'v1/staff/withdrawals' => ['withdrawals', null, null],
        // G2 (#106): the seller's VAT payer mode — no customer's row, but finance-sensitive (it decides every document's VAT): a
        // look at it is recorded like a look at customer data
        'v1/staff/tax/vat-payer-mode' => ['vat_payer_mode', null, null],
    ];

    /** GET routes under /v1/staff that show no customer's data, and why. @var array<string, string> */
    public const EXEMPT = [
        'v1/staff/automation' => 'platform automation rules',
        'v1/staff/capacity' => 'platform capacity, numbers per node',
        'v1/staff/capacity/budget' => 'platform capacity budget',
        'v1/staff/chargebacks/analytics' => 'aggregates over all chargebacks, no customer named',
        'v1/staff/chargebacks/settings' => 'platform settings',
        'v1/staff/game' => 'game panels, nodes and templates; servers only as counts',
        'v1/staff/game/operator-variables' => 'platform template variables',
        'v1/staff/incidents' => 'platform incidents',
        'v1/staff/incidents/components' => 'status page components',
        'v1/staff/incidents/metrics' => 'incident metrics',
        'v1/staff/incidents/{incident}' => 'a platform incident',
        'v1/staff/integrations' => 'provider instances of the platform',
        'v1/staff/integrations/schema' => 'provider configuration schema',
        'v1/staff/jobs' => 'scheduler and queue state',
        'v1/staff/loyalty/campaigns' => 'loyalty campaign definitions',
        'v1/staff/loyalty/campaigns/{campaign}/analytics' => 'campaign aggregates, no customer named',
        'v1/staff/loyalty/levels' => 'loyalty level definitions',
        'v1/staff/loyalty/missions' => 'loyalty mission catalogue',
        'v1/staff/maintenance' => 'platform maintenance windows',
        'v1/staff/oncall/alerts' => 'on-call alerts of the platform',
        'v1/staff/oncall/shifts' => 'staff on-call shifts',
        'v1/staff/oncall/shifts.ics' => 'staff on-call shifts (calendar)',
        'v1/staff/placements' => 'plan placement rules',
        'v1/staff/pricing' => 'the price list',
        'v1/staff/pricing/penpot' => 'Penpot rules per web hosting tariff (H-R7): catalogue data, no customer',
        'v1/staff/pricing/plans/{product}/{plan}/versions' => 'versions of a plan',
        'v1/staff/probes' => 'platform probes',
        'v1/staff/provisioning/load' => 'node load',
        'v1/staff/provisioning/rebalance' => 'a rebalance plan over nodes',
        'v1/staff/registrar-connections' => 'registrar connections of the platform',
        'v1/staff/registrars' => 'registrars of the platform',
        'v1/staff/reports/churn' => 'monthly aggregates',
        'v1/staff/reports/collections' => 'daily aggregates',
        'v1/staff/reports/mrr' => 'aggregates',
        'v1/staff/reports/revenue' => 'monthly aggregates',
        'v1/staff/reports/slo' => 'platform SLO numbers',
        'v1/staff/settings/lifecycle' => 'platform settings',
        'v1/staff/settings/panel-nav' => 'platform settings',
        'v1/staff/support/macros' => 'support settings',
        'v1/staff/support/queues' => 'support settings',
        'v1/staff/support/sla-policies' => 'support settings',
        'v1/staff/templates' => 'notification templates',
        'v1/staff/tickets/macros' => 'reply macros',
    ];

    public function __construct(private readonly AuditRecorder $audit, private readonly CacheRepository $cache) {}

    /** @param array<string,mixed> $detail */
    public function record(Request $request, CommandContext $context, string $what, ?string $organizationId = null, ?string $resourceType = null, ?string $resourceId = null, array $detail = []): void
    {
        if ($context->actorId === null) {
            return;
        }
        $request->attributes->set(self::RECORDED, true);
        $key = 'onhost:staff-read:'.sha1(implode('|', [$context->actorId, $what, (string) $organizationId, (string) $resourceType, (string) $resourceId]));
        if (! $this->cache->add($key, 1, self::WINDOW_SECONDS)) {
            return;
        }
        $this->audit->record($organizationId !== null ? $context->withScope($organizationId) : $context, 'staff.read.'.$what, 'succeeded', $detail + ['screen' => mb_substr($request->path(), 0, 120)], $resourceType, $resourceId);
    }

    /**
     * The staff guard's general record (G7): a successful read of a staff route that shows customer data, when its controller did
     * not record a finer event itself. A refused or failed request read nothing and is not recorded.
     */
    public function afterResponse(Request $request, Response $response, CommandContext $context): void
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return;
        }
        if ($response->getStatusCode() >= 300 || $request->attributes->get(self::RECORDED) === true) {
            return;
        }
        $route = $request->route();
        $uri = is_object($route) && method_exists($route, 'uri') ? (string) $route->uri() : '';
        $entry = self::ROUTES[$uri] ?? null;
        if ($entry === null) {
            return;
        }
        [$what, $resourceType, $model] = $entry;
        // a parameter is the id from the address, or — with implicit route-model binding — the model itself: its key is the id and
        // the bound row names the organization, so neither is lost (review LOW: a string-only filter dropped bound parameters)
        $parameters = is_object($route) && method_exists($route, 'parameters') ? array_values(array_filter($route->parameters(), fn ($p) => is_string($p) || $p instanceof Model)) : [];
        $parameter = $resourceType !== null && count($parameters) === 1 ? $parameters[0] : null;
        $resourceId = match (true) {
            $parameter instanceof Model => mb_substr((string) $parameter->getKey(), 0, 64),
            is_string($parameter) => mb_substr($parameter, 0, 64),
            default => null,
        };
        $organizationId = $parameter instanceof Model ? self::organizationOfModel($parameter) : self::organizationOf($model, $resourceId);
        $this->record($request, $context, $what, $organizationId, $resourceType, $resourceId);
    }

    /** @param class-string<Model>|null $model */
    private static function organizationOf(?string $model, ?string $id): ?string
    {
        if ($model === null || $id === null) {
            return null;
        }
        if ($model === Organization::class) {
            return Organization::query()->whereKey($id)->exists() ? $id : null;
        }
        $organizationId = $model::query()->whereKey($id)->value('organization_id');

        return is_string($organizationId) && $organizationId !== '' ? $organizationId : null;
    }

    /** The organization a bound row belongs to: the organization itself, or its `organization_id`. */
    private static function organizationOfModel(Model $model): ?string
    {
        if ($model instanceof Organization) {
            return (string) $model->getKey();
        }
        $organizationId = $model->getAttribute('organization_id');

        return is_string($organizationId) && $organizationId !== '' ? $organizationId : null;
    }
}
