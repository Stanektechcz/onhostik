<?php

declare(strict_types=1);

namespace App\Http\Navigation;

use App\Http\Navigation\NavItem as N;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\StaffActor;
use Onhost\Domain\Identity\Models\User;
use Onhost\Platform\Commands\CommandScope;

/**
 * The staff console's navigation, driven by permissions (audit 2026-10 P1-1, package B2, owner decision R10).
 *
 * Every staff endpoint the console reads belongs to exactly the items that call it; an item is shown to whoever holds its
 * `required` permissions at the global scope, and the boot object (`ONHOST_BOOT.user.nav`) carries each visible item with
 * the API entries narrowed to the ones the person may call — the console hydrates nothing else. The API keeps checking
 * every request on its own; the navigation only stops offering what would be refused.
 *
 * Console views reuse prototype view ids (the surface stays byte-identical); a view whose prototype label says something
 * else gets the item's label through the sidebar seam (SurfaceRenderer, `OnhostAdmin.label`). Server pages are the system
 * settings pages (SystemSettingsController), which authorize against the same items.
 */
final class StaffNavigation
{
    /** Section order in the boot list. */
    public const SECTIONS = ['overview', 'support', 'customers', 'operations', 'infrastructure', 'commerce', 'finance', 'product', 'security', 'iam'];

    /**
     * Staff GET routes that are deliberately no navigation read. Each needs a reason.
     *
     * @var array<string, string>
     */
    public const API_ALLOW_LIST = [
        // D2 left this empty (the staff panel sign-on is POST only, P1-10). G2: the seller's VAT mode is API-only — finance reads it
        // (billing.tax_rule.manage) before the four-eyes switch, the doctor row "VAT payer mode" shows it to operators; no console screen
        'staff/tax/vat-payer-mode' => 'G2: VAT payer mode report before the four-eyes switch (API-only, billing.tax_rule.manage; doctor shows it)',
    ];

    /**
     * Staff permissions no item requires or reads with (audit 2026-10 B4: "no permission with an endpoint stays without an item,
     * API-only excepted"). None of them guards a staff GET endpoint: they are asked by the command bus on a write the console
     * starts from an item that is shown by its read permission (retry, cancel, evacuate, refunds, wallet credit…), or no
     * endpoint uses them yet. A new staff key must land in an item or here, with its reason.
     *
     * @var array<string, string>
     */
    public const PERMISSIONS_WITHOUT_ITEM = [
        'staff.service.delete' => 'bus: deleting a customer service (staff action endpoint)',
        'staff.console' => 'bus: the staff-side server console of one service (/sprava/konzole/{service})',
        'support.customer_impersonate' => 'dormant: no endpoint (R8: withdrawn until impersonation runs through the bus with four eyes)',
        'provisioning.operation.retry' => 'bus: retry from the fleet and game queues',
        'provisioning.operation.cancel' => 'bus: cancel from the fleet board',
        'provisioning.drift.resolve' => 'bus: resolving a drift from the drifts overview',
        'provider.secret.view' => 'dormant: provider secrets are never revealed through the platform (PermissionCatalog::DORMANT)',
        'capacity.manage' => 'bus: deciding capacity requests and the budget',
        'node.manage' => 'bus: node state, evacuation and migration writes',
        'ipam.manage' => 'dormant: no endpoint yet',
        'backup.policy.manage' => 'bus: backup policy writes',
        'dns.global.write' => 'bus: platform DNS writes (four eyes)',
        'domain.critical.manage' => 'bus: critical domain operations (four eyes)',
        'billing.invoice.manage' => 'bus: invoice writes (mark paid, credit notes) from the customer detail',
        'billing.refund.execute_large' => 'bus: large refunds (four eyes)',
        'billing.credit.adjust_mass' => 'bus: mass credit (four eyes)',
        'billing.tax_rule.manage' => 'bus: a VAT status set by hand and the VAT payer mode switch (four eyes); its one GET, the mode report, is in API_ALLOW_LIST',
        'billing.credit_line.manage' => 'bus: credit lines',
        'incident.publish' => 'bus: publishing to the status page from the incidents view',
        'notification.mass.send' => 'dormant: no mass notice endpoint yet',
        'security.event.read' => 'dormant: no staff endpoint yet',
        'compliance.legal_hold.manage' => 'bus: legal hold (four eyes)',
        'iam.user.manage' => 'dormant: no staff endpoint yet',
        'iam.role.manage' => 'no staff endpoint yet (four eyes)',
        'iam.jit.request' => 'dormant: no JIT elevation flow is built',
        'iam.jit.approve' => 'dormant: no JIT elevation flow is built',
        'iam.access_review.manage' => 'dormant: no staff endpoint yet',
        'iam.break_glass' => 'dormant: break-glass procedure, no console screen',
        'secret.rotate' => 'dormant: secrets are rotated by operator commands on the host',
        'audit.read.global' => 'dormant: no staff endpoint yet',
        'ai.policy.manage' => 'no staff endpoint yet',
        'ai.ops.read' => 'dormant: no staff endpoint yet',
        'feature_flag.manage' => 'dormant: no staff endpoint yet',
        'billing.limit_raise.waive' => 'bus: a free limit raise from the customer detail (four eyes)',
        'staff.backup.read' => 'no staff endpoint yet',
    ];

    public function __construct(private readonly Authorizer $authorizer) {}

    /** @return list<N> */
    public static function items(): array
    {
        $tickets = 'staff.support.ticket.read';
        $ops = 'provisioning.operation.read';
        $instances = 'provider.instance.read';

        return [
            // ── overview: the shift's numbers from what the person may read ──
            new N('dash', 'overview', 10, 'gauge', ['cs' => 'Přehled', 'en' => 'Overview'], N::SCREEN_VIEW, 'dash', [
                N::get('staff/orders', 'staff.order.manage'), N::get('staff/tickets', $tickets), N::get('staff/incidents', 'incident.manage'),
                N::get('staff/maintenance', 'maintenance.manage'), N::get('staff/customers', 'staff.customer.read'),
                N::get('staff/outbox', 'notification.template.manage'), N::get('notifications', ['staff.customer.read', 'staff.inbox.read']),
            ]),
            // ── support ──
            new N('queue', 'support', 10, 'inbox', ['cs' => 'Fronta tiketů', 'en' => 'Ticket queue'], N::SCREEN_VIEW, 'queue', [
                N::get('staff/tickets', $tickets), N::get('staff/tickets/clusters', 'support.queue.manage'), N::get('staff/tickets/macros', 'support.ticket.manage'),
                N::write('post', 'staff/tickets/sla-tick', 'support.queue.manage'),
                // the desk's own settings (TASK-0054): macros, queues and SLA policies, read and changed with support.queue.manage
                ...self::deskSettings(),
            ], all: [$tickets]),
            new N('ticket', 'support', 20, 'message', ['cs' => 'Detail tiketu', 'en' => 'Ticket'], N::SCREEN_VIEW, 'ticket', [
                N::get('staff/tickets/{ticket}', $tickets), N::get('staff/tickets/{ticket}/work-offers', 'support.ticket.manage'),
                N::write('post', 'staff/tickets/{ticket}/messages', 'support.ticket.manage'), N::write('post', 'staff/tickets/{ticket}/transition', 'support.ticket.manage'),
                N::write('post', 'staff/tickets/{ticket}/draft', 'support.ticket.manage'), N::write('post', 'staff/tickets/{ticket}/assign', 'support.ticket.assign'),
            ], all: [$tickets]),
            // ── customers ──
            new N('customers', 'customers', 10, 'users', ['cs' => 'Zákazníci', 'en' => 'Customers'], N::SCREEN_VIEW, 'customers', [
                N::get('staff/customers', 'staff.customer.read'), N::get('staff/customers/{organization}', 'staff.customer.read'),
                N::get('staff/services', 'staff.service.manage'), N::get('staff/domains', 'staff.service.manage'), N::get('staff/orders', 'staff.order.manage'),
                N::write('post', 'staff/assistant/chat', 'staff.customer.read'),
            ], all: ['staff.customer.read']),
            new N('leads', 'customers', 20, 'handshake', ['cs' => 'Poptávky', 'en' => 'Leads'], N::SCREEN_VIEW, 'onboardcust', [
                N::get('staff/leads', 'staff.customer.read'), N::write('post', 'staff/leads/{lead}/transition', 'staff.order.manage'),
            ], all: ['staff.customer.read', 'staff.order.manage']),
            // ── operations: incidents, maintenance, on-call, the status page ──
            new N('incidents', 'operations', 10, 'siren', ['cs' => 'Incidenty', 'en' => 'Incidents'], N::SCREEN_VIEW, 'incidents', [
                N::get('staff/incidents', 'incident.manage'), N::get('staff/incidents/{incident}', 'incident.manage'), N::get('staff/incidents/components', 'incident.manage'),
                N::get('staff/incidents/metrics', 'report.read'),
            ], all: ['incident.manage']),
            new N('maintenance', 'operations', 20, 'calendar', ['cs' => 'Kalendář odstávek', 'en' => 'Maintenance calendar'], N::SCREEN_VIEW, 'maintenance', [
                N::get('staff/maintenance', 'maintenance.manage'),
            ], all: ['maintenance.manage']),
            new N('oncall', 'operations', 30, 'pager', ['cs' => 'Pohotovost (on-call)', 'en' => 'On-call'], N::SCREEN_VIEW, 'skills', [
                N::get('staff/oncall/alerts', 'incident.manage'), N::get('staff/oncall/shifts', 'incident.manage'), N::get('staff/oncall/shifts.ics', 'incident.manage'),
            ], all: ['incident.manage']),
            new N('probes', 'operations', 40, 'radar', ['cs' => 'Sondy a komponenty', 'en' => 'Probes and components'], N::SCREEN_VIEW, 'statuspg', [
                N::get('staff/probes', 'incident.manage'), N::get('staff/incidents/components', 'incident.manage'),
            ], all: ['incident.manage']),
            new N('slo', 'operations', 50, 'target', ['cs' => 'SLO a dostupnost', 'en' => 'SLO and availability'], N::SCREEN_VIEW, 'quality', [
                N::get('staff/reports/slo', 'report.read'), N::get('staff/incidents/metrics', 'report.read'),
            ], all: ['report.read']),
            // ── infrastructure ──
            new N('fleet', 'infrastructure', 10, 'server', ['cs' => 'Infrastruktura', 'en' => 'Fleet'], N::SCREEN_VIEW, 'fleet', [
                N::get('staff/provisioning/board', $ops), N::get('staff/provisioning/rebalance', $ops), N::get('staff/provisioning/jobs', $ops),
                N::get('staff/provisioning/jobs/{operation}', $ops), N::get('staff/provisioning/load', $ops),
                N::write('post', 'staff/provisioning/freeze', 'provisioning.freeze'), N::write('post', 'staff/provisioning/thaw', 'provisioning.freeze'),
            ], all: [$ops]),
            new N('jobsadm', 'infrastructure', 20, 'list', ['cs' => 'Běhové úlohy', 'en' => 'Jobs'], N::SCREEN_VIEW, 'jobsadm', [
                N::get('staff/jobs', $ops), N::get('staff/bulk-jobs', $ops), N::get('staff/bulk-jobs/{job}', $ops),
            ], all: [$ops]),
            new N('automation', 'infrastructure', 30, 'bolt', ['cs' => 'Automatizace', 'en' => 'Automation'], N::SCREEN_VIEW, 'automation', [
                N::get('staff/automation', $ops), N::get('staff/orders/risk-review', 'staff.order.manage'),
            ], all: [$ops]),
            new N('drifts', 'infrastructure', 40, 'diff', ['cs' => 'Odchylky a úklid', 'en' => 'Drifts and clean-up'], N::SCREEN_VIEW, 'infralog', [
                N::get('staff/resource-mappings', $ops), N::get('staff/provisioning/deletions', $ops), N::get('staff/provisioning/ssh-key-revocations', $ops),
            ], all: [$ops]),
            new N('gnodes', 'infrastructure', 50, 'gamepad', ['cs' => 'Herní uzly', 'en' => 'Game nodes'], N::SCREEN_VIEW, 'gnodes', [
                N::get('staff/game', $instances), N::get('staff/game/operator-variables', $instances),
            ], all: [$instances]),
            new N('geggs', 'infrastructure', 51, 'layers', ['cs' => 'Šablony her', 'en' => 'Game templates'], N::SCREEN_VIEW, 'geggs', [N::get('staff/game', $instances)], all: [$instances]),
            new N('galloc', 'infrastructure', 52, 'plug', ['cs' => 'Alokace a porty', 'en' => 'Allocations and ports'], N::SCREEN_VIEW, 'galloc', [N::get('staff/game', $instances)], all: [$instances]),
            new N('gprov', 'infrastructure', 53, 'queue', ['cs' => 'Provisioning fronta', 'en' => 'Provisioning queue'], N::SCREEN_VIEW, 'gprov', [N::get('staff/game', $instances)], all: [$instances]),
            new N('capacity', 'infrastructure', 60, 'chart', ['cs' => 'Kapacita a nákup uzlů', 'en' => 'Capacity and node purchases'], N::SCREEN_VIEW, 'nodecost', [
                N::get('staff/capacity', 'capacity.read'), N::get('staff/capacity/requests', 'capacity.read'), N::get('staff/capacity/budget', 'capacity.read'),
            ], all: ['capacity.read']),
            new N('integrations', 'infrastructure', 70, 'cable', ['cs' => 'Integrace providerů', 'en' => 'Provider integrations'], N::SCREEN_PAGE, '/sprava/nastaveni/integrace', [
                N::get('staff/integrations', $instances), N::get('staff/integrations/schema', $instances), N::get('staff/integrations/{instance}', $instances),
            ], all: [$instances]),
            new N('placements', 'infrastructure', 71, 'pin', ['cs' => 'Umístění tarifů', 'en' => 'Plan placements'], N::SCREEN_PAGE, '/sprava/nastaveni/integrace', [
                N::get('staff/placements', $instances),
            ], all: [$instances]),
            new N('registrars', 'infrastructure', 72, 'globe', ['cs' => 'Registrátoři domén', 'en' => 'Domain registrars'], N::SCREEN_PAGE, '/sprava/nastaveni/integrace', [
                N::get('staff/registrars', $instances), N::get('staff/registrar-connections', 'domain.registrar.manage'),
            ], any: [$instances, 'domain.registrar.manage']),
            new N('operations', 'infrastructure', 80, 'activity', ['cs' => 'Provoz a operace', 'en' => 'Operations board'], N::SCREEN_PAGE, '/sprava/nastaveni/provoz', [
                N::get('staff/provisioning/board', $ops), N::get('staff/provisioning/jobs', $ops),
            ], all: [$ops]),
            new N('bulk', 'infrastructure', 90, 'stack', ['cs' => 'Hromadné akce', 'en' => 'Bulk actions'], N::SCREEN_PAGE, '/sprava/nastaveni/hromadne-akce', [
                N::get('staff/bulk-jobs', $ops),
            ], all: [$ops]),
            // ── commerce ──
            new N('renewals', 'commerce', 10, 'refresh', ['cs' => 'Obnovy a expirace', 'en' => 'Renewals'], N::SCREEN_VIEW, 'renewals', [
                N::get('staff/renewals', 'staff.order.manage'),
            ], all: ['staff.order.manage']),
            new N('chargebacks', 'commerce', 20, 'undo', ['cs' => 'Kredity a platby', 'en' => 'Credits and payments'], N::SCREEN_VIEW, 'money', [
                N::get('staff/chargebacks', ['billing.credit.adjust', 'staff.chargeback.decide']), N::get('staff/chargebacks/settings', ['billing.credit.adjust', 'staff.chargeback.decide']),
                N::get('staff/chargebacks/analytics', 'staff.service.manage'), N::get('staff/marketplace/orders', 'partner.manage'),
                N::write('post', 'staff/chargebacks/{chargeback}/decide', 'staff.chargeback.decide'),
            ], any: ['billing.credit.adjust', 'staff.chargeback.decide', 'staff.service.manage']),
            new N('loyalty', 'commerce', 30, 'star', ['cs' => 'Věrnost a kampaně', 'en' => 'Loyalty and campaigns'], N::SCREEN_VIEW, 'coupons', [
                N::get('staff/loyalty/campaigns', 'staff.customer.manage'), N::get('staff/loyalty/levels', 'staff.customer.manage'), N::get('staff/loyalty/missions', 'staff.customer.manage'),
                N::get('staff/loyalty/campaigns/{campaign}/analytics', 'staff.customer.manage'), N::get('staff/referrals', 'staff.customer.manage'),
            ], all: ['staff.customer.manage']),
            new N('marketplace', 'commerce', 40, 'store', ['cs' => 'Marketplace', 'en' => 'Marketplace'], N::SCREEN_VIEW, 'shopadm', [
                N::get('staff/marketplace/listings', 'partner.manage'), N::get('staff/marketplace/orders', 'partner.manage'),
            ], all: ['partner.manage']),
            // ── finance ──
            new N('bank', 'finance', 10, 'bank', ['cs' => 'Bankovní platby', 'en' => 'Bank payments'], N::SCREEN_PAGE, '/sprava/nastaveni/integrace', [
                N::get('staff/payments/bank', 'billing.reconcile'),
            ], all: ['billing.reconcile']),
            // H-R8: the Comgate gateway check (credentials present?, mode, connection, 1 Kč test payment) in the settings page
            new N('comgate', 'finance', 15, 'bank', ['cs' => 'Platební brána Comgate', 'en' => 'Comgate gateway'], N::SCREEN_PAGE, '/sprava/nastaveni/integrace', [
                N::get('staff/payments/comgate', 'provider.instance.read'), N::write('post', 'staff/payments/comgate/check', 'provider.instance.manage'), N::write('put', 'staff/payments/comgate/test-mode', 'provider.instance.manage'),
            ], all: ['provider.instance.read']),
            new N('dunning', 'finance', 20, 'receipt', ['cs' => 'Upomínky a pohledávky', 'en' => 'Dunning and receivables'], N::SCREEN_VIEW, 'invoices', [
                N::get('staff/dunning', 'billing.dunning.manage'), N::write('post', 'staff/dunning/run', 'billing.dunning.manage'),
            ], all: ['billing.dunning.manage']),
            new N('withdrawals', 'finance', 30, 'file', ['cs' => 'Odstoupení od smlouvy', 'en' => 'Contract withdrawals'], N::SCREEN_VIEW, 'contracts', [
                N::get('staff/withdrawals', 'staff.billing.read'),
                // G6: an order payment back to its source when the consumer did not take credit; bank payouts confirmed by finance
                N::get('staff/payments/refunds', 'staff.billing.read'), N::get('staff/payments/refunds/not-paid-out', 'staff.billing.read'), N::write('post', 'staff/payments/{payment}/refund', 'billing.refund.execute'), N::write('post', 'staff/payments/refunds/{refund}/confirm', 'billing.refund.execute'), N::write('post', 'staff/payments/refunds/{refund}/cancel', 'billing.refund.execute'),
            ], all: ['staff.billing.read']),
            new N('sla_credits', 'finance', 40, 'shield', ['cs' => 'SLA kredity', 'en' => 'SLA credits'], N::SCREEN_VIEW, 'slapol', [
                N::get('staff/sla-credits', 'sla.credit.manage'),
            ], all: ['sla.credit.manage']),
            new N('partners', 'finance', 50, 'briefcase', ['cs' => 'Partneři', 'en' => 'Partners'], N::SCREEN_VIEW, 'resadm', [
                N::get('staff/partners', 'partner.manage'), N::get('staff/partners/{partner}', 'partner.manage'), N::get('staff/partners/requests', 'partner.manage'),
            ], all: ['partner.manage']),
            new N('payouts', 'finance', 60, 'wallet', ['cs' => 'Výplaty partnerům', 'en' => 'Partner payouts'], N::SCREEN_VIEW, 'kudos', [
                N::get('staff/partners/payouts', 'partner.manage'),
            ], all: ['partner.manage']),
            new N('reports', 'finance', 70, 'chart', ['cs' => 'Reporty a výhled', 'en' => 'Reports'], N::SCREEN_VIEW, 'finance', [
                N::get('staff/reports/mrr', 'report.read'), N::get('staff/reports/collections', 'report.read'), N::get('staff/reports/churn', 'report.read'), N::get('staff/reports/revenue', 'report.read'),
            ], all: ['report.read']),
            // four eyes: whoever decides sees every request, everybody else the ones they opened (ApprovalService::visibleTo)
            new N('approvals', 'iam', 10, 'check', ['cs' => 'Schvalování', 'en' => 'Approvals'], N::SCREEN_PAGE, '/sprava/nastaveni/schvalovani', [
                N::get('staff/approvals', null), N::write('post', 'staff/approvals/{approval}/decision', 'iam.approval.decide'),
            ]),
            new N('mfa_reset', 'iam', 20, 'key', ['cs' => 'Reset MFA', 'en' => 'MFA reset'], N::SCREEN_VIEW, 'users', [
                N::write('post', 'staff/users/{user}/mfa-reset', 'iam.mfa.reset'),
            ], all: ['iam.mfa.reset']),
            // ── product ──
            new N('pricing', 'product', 10, 'tag', ['cs' => 'Ceník, slevy a doplňky', 'en' => 'Pricing, discounts and add-ons'], N::SCREEN_PAGE, '/sprava/nastaveni/integrace', [
                N::get('staff/pricing', 'catalog.manage'), N::get('staff/pricing/penpot', 'catalog.manage'), // H-R7: Penpot per web hosting tariff
            ], all: ['catalog.manage']),
            new N('plans', 'product', 20, 'versions', ['cs' => 'Tarify a verze', 'en' => 'Plans and versions'], N::SCREEN_PAGE, '/sprava/nastaveni/tarify', [
                N::get('staff/pricing', 'catalog.manage'), N::get('staff/pricing/plans/{product}/{plan}/versions', 'catalog.manage'),
            ], all: ['catalog.manage']),
            new N('panel_nav', 'product', 30, 'menu', ['cs' => 'Navigace klientského panelu', 'en' => 'Customer panel navigation'], N::SCREEN_PAGE, '/sprava/nastaveni/integrace', [
                N::get('staff/settings/panel-nav', 'catalog.manage'),
            ], all: ['catalog.manage']),
            new N('lifecycle', 'product', 40, 'hourglass', ['cs' => 'Životní cyklus služeb', 'en' => 'Service lifecycle'], N::SCREEN_PAGE, '/sprava/nastaveni/zivotni-cyklus', [
                N::get('staff/settings/lifecycle', 'catalog.manage'), N::get('staff/provisioning/deletions', $ops),
            ], all: ['catalog.manage']),
            new N('templates', 'product', 50, 'mail', ['cs' => 'Šablony zpráv a odchozí pošta', 'en' => 'Message templates and outbox'], N::SCREEN_VIEW, 'mailtpl', [
                N::get('staff/templates', 'notification.template.manage'), N::get('staff/outbox', 'notification.template.manage'),
            ], all: ['notification.template.manage']),
            new N('content', 'product', 60, 'pen', ['cs' => 'Obsah webu', 'en' => 'Website content'], N::SCREEN_VIEW, 'content', [
                N::get('posts', null), N::get('kb', null), N::get('changelog', null),
                N::write('put', 'staff/content/posts', 'content.manage'), N::write('put', 'staff/content/changelog', 'content.manage'), N::write('put', 'staff/content/kb', 'support.kb.manage'),
            ], any: ['content.manage', 'support.kb.manage']),
            // ── security and compliance ──
            new N('security_incidents', 'security', 10, 'lock', ['cs' => 'Bezpečnostní incidenty a lhůty', 'en' => 'Security incidents and deadlines'], N::SCREEN_VIEW, 'opsaudit', [
                N::get('staff/security/incidents', 'security.incident.manage'), N::get('staff/security/incidents/{case}', 'security.incident.manage'),
                N::get('staff/compliance/timers', ['compliance.case.manage', 'security.incident.manage']),
            ], any: ['security.incident.manage', 'compliance.case.manage']),
            new N('abuse', 'security', 20, 'flag', ['cs' => 'Zneužití (DSA)', 'en' => 'Abuse (DSA)'], N::SCREEN_VIEW, 'segments', [
                N::get('staff/abuse-cases', 'abuse.case.manage'), N::get('staff/abuse-cases/{case}', 'abuse.case.manage'),
            ], all: ['abuse.case.manage']),
            new N('data_requests', 'security', 30, 'database', ['cs' => 'Žádosti o data', 'en' => 'Data requests'], N::SCREEN_VIEW, 'gdpr', [
                N::get('staff/data-requests', 'compliance.case.manage'),
            ], all: ['compliance.case.manage']),
        ];
    }

    /**
     * `/v1/staff/support/{macros|queues|sla-policies}` (SupportSettingsController): every read and write with support.queue.manage.
     *
     * @return list<array{method:string, path:string, permission:string}>
     */
    private static function deskSettings(): array
    {
        $entries = [];
        foreach (['support/macros', 'support/queues', 'support/sla-policies'] as $path) {
            $entries = [...$entries,
                N::get("staff/{$path}", 'support.queue.manage'), N::write('post', "staff/{$path}", 'support.queue.manage'),
                N::write('patch', "staff/{$path}/{id}", 'support.queue.manage'), N::write('delete', "staff/{$path}/{id}", 'support.queue.manage'),
            ];
        }

        return $entries;
    }

    /**
     * The items this person sees, in boot shape, each with only the API entries the person may call.
     *
     * @return list<array<string, mixed>>
     */
    public function for(User $user): array
    {
        if (! StaffActor::account($user)) {
            return [];
        }
        $held = $this->authorizer->permissionsAt($user, CommandScope::global());
        $out = [];
        foreach (self::sorted() as $item) {
            if (! self::visibleWith($item, $held)) {
                continue;
            }
            $api = array_values(array_filter($item->api, fn (array $entry) => self::callableWith($entry, $held)));
            $out[] = $item->toArray($api);
        }

        return $out;
    }

    /** Whether a member of staff holds any global permission at all; one who holds none is the console role `none` (R10). */
    public function holdsAnything(User $user): bool
    {
        return StaffActor::account($user) && $this->authorizer->permissionsAt($user, CommandScope::global()) !== [];
    }

    /** Whether the person may open a server page of the navigation (any visible item that targets it). */
    public function canOpenPage(User $user, string $path): bool
    {
        $path = self::pathOf($path);
        foreach ($this->for($user) as $item) {
            if ($item['screen']['type'] === N::SCREEN_PAGE && self::pathOf($item['screen']['target']) === $path) {
                return true;
            }
        }

        return false;
    }

    /** The first server page the person may open, or null. */
    public function firstPage(User $user): ?string
    {
        foreach ($this->for($user) as $item) {
            if ($item['screen']['type'] === N::SCREEN_PAGE) {
                return self::pathOf($item['screen']['target']);
            }
        }

        return null;
    }

    /** @param list<string> $held */
    public static function visibleWith(N $item, array $held): bool
    {
        if ($item->any !== [] && array_intersect($item->any, $held) === []) {
            return false;
        }

        return array_diff($item->all, $held) === [];
    }

    /** @param list<string> $held */
    public static function callableWith(array $entry, array $held): bool
    {
        $accepts = N::accepts($entry);

        return $accepts === [] || array_intersect($accepts, $held) !== [];
    }

    /** @return list<N> */
    public static function sorted(): array
    {
        $items = self::items();
        usort($items, fn (N $a, N $b) => [array_search($a->section, self::SECTIONS, true), $a->order] <=> [array_search($b->section, self::SECTIONS, true), $b->order]);

        return $items;
    }

    private static function pathOf(string $target): string
    {
        return rtrim((string) (parse_url($target, PHP_URL_PATH) ?? $target), '/');
    }
}
