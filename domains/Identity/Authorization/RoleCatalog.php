<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Authorization;

/**
 * Role = set of permissions (blueprint §61.4 staff roles, §61.5 customer roles).
 * Seeded by AuthorizationSeeder; runtime lookups go through the DB so roles can
 * be extended by IAMAdmin without a deploy (critical permission `iam.role.manage`).
 */
final class RoleCatalog
{
    /** @return array<string, array{name:string, description:string, scope:string, staff:bool, permissions:list<string>}> */
    public static function all(): array
    {
        $customerRead = ['organization.read', 'service.read', 'domain.read', 'dns.zone.read', 'backup.read', 'billing.invoice.read', 'billing.wallet.read', 'support.ticket.read', 'audit.read'];
        $allCustomer = array_values(array_filter(PermissionCatalog::keys(), fn ($k) => PermissionCatalog::all()[$k]['audience'] === 'customer'));
        $allStaff = array_values(array_filter(PermissionCatalog::keys(), fn ($k) => PermissionCatalog::all()[$k]['audience'] === 'staff'));

        return [
            // ── customer organization roles ────────────────────────────────
            'owner' => self::role('Owner', 'Full control of the organization', 'organization', false, $allCustomer),
            'org_admin' => self::role('Organization admin', 'Everything except what the owner alone may do (closing the organization, the panel account password)', 'organization', false, array_values(array_diff($allCustomer, self::orgAdminWithheld()))),
            'billing_admin' => self::role('Billing admin', 'Wallet, invoices, payment methods, budgets', 'organization', false, array_merge($customerRead, ['billing.wallet.topup', 'billing.payment_method.manage', 'billing.budget.manage', 'catalog.order.create'], self::BILLING_ADMIN_EXTRA)),
            'domain_manager' => self::role('Domain manager', 'Domains, contacts, renewals, transfers', 'organization', false, array_merge($customerRead, ['domain.manage', 'domain.transfer_out.execute', 'domain.registrant.change', 'dns.zone.write'])),
            'dns_manager' => self::role('DNS manager', 'DNS zones and DNSSEC', 'organization', false, array_merge($customerRead, ['dns.zone.write', 'dns.dnssec.manage'])),
            'developer' => self::role('Developer', 'Apps, deploys, databases, consoles', 'organization', false, array_merge($customerRead, ['service.manage', 'service.console', 'apps.deploy', 'database.manage', 'backup.download', 'dns.zone.write', 'support.ticket.write', 'support.chat.use'])),
            'cloud_operator' => self::role('Cloud operator', 'VPS/VDS lifecycle, snapshots, firewall, backups', 'organization', false, array_merge($customerRead, ['service.manage', 'service.console', 'compute.vm.manage', 'compute.vm.delete', 'backup.restore', 'backup.download', 'support.ticket.write', 'support.chat.use'])),
            'game_operator' => self::role('Game operator', 'Game servers, console, mods, backups', 'organization', false, array_merge($customerRead, ['service.manage', 'service.console', 'game.manage', 'backup.restore', 'backup.download', 'support.ticket.write', 'support.chat.use'])),
            'mail_manager' => self::role('Mail manager', 'Mail domains, mailboxes, DKIM/SPF/DMARC', 'organization', false, array_merge($customerRead, ['mail.manage', 'backup.download', 'dns.zone.write', 'support.ticket.write'])),
            // B6 (audit 2026-10 "nekonzistence rolí"; TASK-0043): an auditor reads — it held the HIGH write `security.settings.manage`
            // (which no endpoint asks for yet, PermissionCatalog::DORMANT); a read key for security settings comes with their screen
            'security_auditor' => self::role('Security auditor', 'Read-only, including the audit log', 'organization', false, $customerRead),
            'support_contact' => self::role('Support contact', 'Open and read tickets, use chat', 'organization', false, ['organization.read', 'service.read', 'support.ticket.read', 'support.ticket.write', 'support.chat.use']),
            'viewer' => self::role('Viewer', 'Read-only', 'organization', false, $customerRead),
            // Somebody a service was shared with (a freelancer, an agency): a member of the organization who sees NOTHING of it by
            // that membership — no invoices, no other services, no team. What they may do comes from the service grants below.
            'guest' => self::role('Guest', 'Sees only the services shared with them', 'organization', false, []),

            // ── capabilities on ONE service (resource scope; handed out by Services\Access\ServiceAccessService, never as an organization or project role) ──
            'svc_view' => self::role('Service: view', 'State, metrics, logs, backups list', 'resource', false, ['service.read', 'backup.read']),
            // TASK-0043 (permission program S1-03, D4, ruling #15): the narrow level the access wizard offers first — keeping the service
            // running without code: nothing it does writes a file, a cron job, a database or a login, or opens a shell
            'svc_operate' => self::role('Service: operate', 'Restart, PHP version, caches, certificates — no files, cron, databases, logins or shell', 'resource', false, ['service.read', 'service.operate']),
            // unchanged in what it may do (PresetsUnchangedTest); its description now says what the program relabelled it to: it runs code
            'svc_manage' => self::role('Service: manage', 'Actions and settings: restart, PHP, databases, cron, files, deploys, mailboxes, deleting them too — this runs code on the service; without backup deletion and without logins that open a shell', 'resource', false, ['service.read', 'service.manage']),
            // a console is more than managing, never less (H334): whoever gets a shell on the service manages it. Root access, rescue
            // mode and game sub-users are the console too (TASK-0029, C13-H1b): each of them hands over the server itself
            'svc_console' => self::role('Service: console', 'Terminal, SSH keys and root access, rescue mode, VNC, game console and its sub-users and console schedules', 'resource', false, ['service.read', 'service.manage', 'service.console']),
            // TASK-0043 (S1-03, audit SE-1): deleting data inside the service is a tick of its own, next to operate; copies are not data here
            // (backup.delete and its kin stay the owner's and the operators', D29.2)
            'svc_data_delete' => self::role('Service: delete data', 'Delete sites, databases, files, mailboxes and game data inside the service — never its backups', 'resource', false, ['service.read', 'service.data.delete']),
            'svc_backups' => self::role('Service: backups', 'Download backup archives', 'resource', false, ['service.read', 'backup.read', 'backup.download']),
            'svc_restore' => self::role('Service: restore', 'Restore the service from a backup', 'resource', false, ['service.read', 'backup.read', 'backup.restore']),
            'svc_assistant' => self::role('Service: assistant', 'Use the AI assistant for the shared service', 'resource', false, ['service.read', 'support.chat.use']),
            // ── staff roles ──────────────────────────────────────────────────
            // B6 role hygiene (audit 2026-10, decision R8; TASK-0043): SS-7 — `support.customer_impersonate` is withdrawn from the
            // support manager until impersonation runs through the bus with four eyes; the support manager reads the operations board
            // it sends tickets to (`provisioning.operation.read`); a chargeback decision is `staff.chargeback.decide` (support desk),
            // no longer every holder of `staff.service.manage`. The content team stays without `staff.customer.read` (Customer 360):
            // AssistantScopeTest pins that staff without the customer view do not reach a customer's account (open decision, B6).
            // TASK-0037 (program IF-18): the support, finance and backup roles gain the staff read keys (staff.support.ticket.read,
            // staff.billing.read, staff.backup.read) NEXT TO the customer keys they held — expand only; P0-15 removes the customer
            // keys once the shadow log shows nothing still needs them
            'platform_owner' => self::role('PlatformOwner / SuperAdmin', 'Break-glass only; never a daily account', 'global', true, array_merge($allStaff, array_values(array_diff($allCustomer, self::STAFF_NEVER)))),
            'iam_admin' => self::role('IAMAdmin', 'Users, roles, SSO, JIT approvals; no refunds', 'global', true, ['iam.user.manage', 'iam.role.manage', 'iam.mfa.reset', 'iam.jit.approve', 'iam.approval.decide', 'iam.access_review.manage', 'audit.read.global', 'security.event.read', 'staff.customer.read']),
            'infrastructure_admin' => self::role('InfrastructureAdmin', 'Proxmox, resources, capacity, operations queue', 'global', true, ['provider.instance.read', 'provider.instance.manage', 'capacity.read', 'capacity.manage', 'node.manage', 'provisioning.operation.read', 'provisioning.operation.retry', 'provisioning.operation.cancel', 'provisioning.drift.resolve', 'provisioning.freeze', 'staff.service.manage', 'staff.console', 'backup.policy.manage', 'staff.customer.read', 'iam.jit.request']),
            'network_admin' => self::role('NetworkAdmin', 'IPAM/BGP/VLAN/firewall/rDNS', 'global', true, ['ipam.manage', 'capacity.read', 'provider.instance.read', 'provisioning.operation.read', 'staff.customer.read', 'iam.jit.request']),
            'domain_dns_admin' => self::role('DomainDNSAdmin', 'WAPI, PowerDNS, DNSSEC', 'global', true, ['domain.registrar.manage', 'dns.global.write', 'domain.critical.manage', 'provisioning.operation.read', 'provisioning.operation.retry', 'staff.customer.read', 'iam.jit.request']),
            'shared_hosting_admin' => self::role('SharedHostingAdmin', 'ISPConfig / web / db', 'global', true, ['staff.service.manage', 'staff.console', 'provisioning.operation.read', 'provisioning.operation.retry', 'provisioning.drift.resolve', 'provider.instance.read', 'staff.customer.read', 'iam.jit.request']),
            'managed_hosting_admin' => self::role('ManagedHostingAdmin', 'aaPanel managed pool', 'global', true, ['staff.service.manage', 'staff.console', 'provisioning.operation.read', 'provisioning.operation.retry', 'provisioning.drift.resolve', 'provider.instance.read', 'staff.customer.read', 'iam.jit.request']),
            'apps_platform_admin' => self::role('AppsPlatformAdmin', 'RKE2/build/registry', 'global', true, ['staff.service.manage', 'provisioning.operation.read', 'provisioning.operation.retry', 'provisioning.drift.resolve', 'provider.instance.read', 'capacity.read', 'staff.customer.read', 'iam.jit.request']),
            'cloud_vps_admin' => self::role('CloudVPSAdmin', 'VPS/VDS lifecycle', 'global', true, ['staff.service.manage', 'staff.service.delete', 'staff.console', 'provisioning.operation.read', 'provisioning.operation.retry', 'provider.instance.read', 'capacity.read', 'staff.customer.read', 'iam.jit.request']),
            'game_admin' => self::role('GameAdmin', 'Pterodactyl/Wings', 'global', true, ['staff.service.manage', 'staff.console', 'provisioning.operation.read', 'provisioning.operation.retry', 'provider.instance.read', 'capacity.read', 'staff.customer.read', 'iam.jit.request']),
            'mail_admin' => self::role('MailAdmin', 'Mailboxes/relay/reputation', 'global', true, ['staff.service.manage', 'provisioning.operation.read', 'provisioning.operation.retry', 'provider.instance.read', 'staff.customer.read', 'iam.jit.request']),
            'database_admin' => self::role('DatabaseAdmin', 'DBaaS', 'global', true, ['staff.service.manage', 'staff.console', 'provisioning.operation.read', 'provisioning.operation.retry', 'staff.customer.read', 'iam.jit.request']),
            'ai_platform_admin' => self::role('AIPlatformAdmin', 'Models, gateway, GPU policies', 'global', true, ['ai.policy.manage', 'ai.ops.read', 'staff.service.manage', 'staff.customer.read', 'iam.jit.request']),
            'backup_dr_admin' => self::role('BackupDRAdmin', 'PBS/restore/retention', 'global', true, ['staff.backup.read', 'backup.read', 'backup.policy.manage', 'backup.restore', 'backup.delete', 'provider.instance.read', 'provisioning.operation.read', 'staff.customer.read', 'iam.jit.request']),
            'sre' => self::role('SRE', 'Metrics/incidents/maintenance', 'global', true, ['incident.manage', 'incident.publish', 'maintenance.manage', 'capacity.read', 'provider.instance.read', 'provisioning.operation.read', 'provisioning.operation.retry', 'provisioning.freeze', 'sla.credit.manage', 'report.read', 'staff.customer.read', 'iam.jit.request']),
            'incident_commander' => self::role('IncidentCommander', 'Temporary incident authority', 'global', true, ['incident.manage', 'incident.publish', 'provisioning.freeze', 'security.incident.manage', 'notification.mass.send', 'staff.customer.read', 'capacity.read', 'provisioning.operation.read']),
            'security_soc' => self::role('SecuritySOC', 'Detections/quarantine/forensics', 'global', true, ['security.incident.manage', 'security.event.read', 'audit.read.global', 'staff.service.manage', 'provisioning.freeze', 'staff.customer.read', 'iam.jit.request']),
            'abuse_trust_safety' => self::role('AbuseTrustSafety', 'DSA/abuse cases', 'global', true, ['abuse.case.manage', 'staff.customer.read', 'staff.service.manage', 'security.event.read']),
            'compliance_legal' => self::role('ComplianceLegal', 'Regulatory cases/evidence', 'global', true, ['compliance.case.manage', 'compliance.legal_hold.manage', 'abuse.case.manage', 'audit.read.global', 'staff.customer.read', 'report.read']),
            'billing_finance_admin' => self::role('BillingFinanceAdmin', 'Invoice config/tax/reconciliation', 'global', true, ['staff.billing.read', 'billing.invoice.read', 'billing.invoice.manage', 'billing.refund.execute', 'billing.refund.execute_large', 'billing.credit.adjust', 'billing.credit.adjust_mass', 'billing.tax_rule.manage', 'billing.reconcile', 'billing.dunning.manage', 'billing.credit_line.manage', 'sla.credit.manage', 'partner.manage', 'staff.customer.manage', 'catalog.manage', 'report.read', 'staff.customer.read', 'iam.approval.decide', 'billing.limit_raise.waive']),
            'billing_operator' => self::role('BillingOperator', 'Invoice ops; limited refunds', 'global', true, ['staff.billing.read', 'billing.invoice.read', 'billing.invoice.manage', 'billing.refund.execute', 'billing.reconcile', 'billing.dunning.manage', 'report.read', 'staff.customer.read']),
            'support_manager' => self::role('SupportManager', 'Queues/SLA/escalations', 'global', true, ['staff.support.ticket.read', 'support.ticket.read', 'support.ticket.assign', 'support.ticket.manage', 'support.queue.manage', 'support.kb.manage', 'staff.customer.read', 'staff.order.manage', 'incident.manage', 'report.read', 'ai.ops.read', 'iam.jit.request', 'provisioning.operation.read', 'staff.chargeback.decide']),
            'support_l1' => self::role('SupportL1', 'Read basics + safe actions', 'global', true, ['staff.support.ticket.read', 'support.ticket.read', 'support.ticket.manage', 'staff.customer.read', 'provisioning.operation.read', 'support.chat.use']),
            'support_l2' => self::role('SupportL2', 'Deeper diagnostics + service actions', 'global', true, ['staff.support.ticket.read', 'support.ticket.read', 'support.ticket.manage', 'support.ticket.assign', 'staff.customer.read', 'staff.service.manage', 'staff.order.manage', 'provisioning.operation.read', 'provisioning.operation.retry', 'staff.console', 'support.chat.use', 'iam.jit.request', 'staff.chargeback.decide']),
            'support_l3' => self::role('SupportL3', 'Engineering escalation', 'global', true, ['staff.support.ticket.read', 'support.ticket.read', 'support.ticket.manage', 'support.ticket.assign', 'staff.customer.read', 'staff.service.manage', 'staff.order.manage', 'provisioning.operation.read', 'provisioning.operation.retry', 'provisioning.drift.resolve', 'staff.console', 'provider.instance.read', 'incident.manage', 'iam.jit.request', 'staff.chargeback.decide']),
            'sales' => self::role('Sales', 'Quotes/CRM without infra admin', 'global', true, ['staff.customer.read', 'staff.order.manage', 'report.read']),
            'marketing_content' => self::role('MarketingContent', 'Public content only', 'global', true, ['content.manage', 'staff.inbox.read']), // TASK-0067: its own staff inbox, still no customer view
            'product_manager' => self::role('ProductManager', 'Catalogue, plan versions and prices, notification templates, feature flags; no customer data beyond the overview', 'global', true, ['catalog.manage', 'notification.template.manage', 'feature_flag.manage', 'content.manage', 'report.read', 'staff.customer.read']),
            'auditor_read_only' => self::role('AuditorReadOnly', 'Immutable audit/evidence read', 'global', true, ['audit.read.global', 'security.event.read', 'report.read', 'staff.customer.read', 'provisioning.operation.read', 'ai.ops.read']),
            // commission only (red-team round of the Phase-0 chain): it never had reseller powers, whatever its old name said; the panel
            // calls it „partner (jen provize)“ (TASK-0035)
            'partner' => self::role('Partner (commission only)', 'Read access, orders and support tickets; earns commission only', 'organization', false, array_merge($customerRead, ['catalog.order.create', 'support.ticket.write', 'support.chat.use'], self::PARTNER_PORTAL)),
        ];
    }

    // ── TASK-0021 ──
    /** Customer permissions no staff account holds, not even the break-glass one (owner decision 15): they are the organization owner's. */
    public const STAFF_NEVER = ['service.panel_account.manage', ...PermissionCatalog::PARTNER_OWNER_ONLY]; // TASK-0040: the partner's payout account too

    /**
     * What an organization admin is NOT given — the one place the org_admin line is decided: the owner-only permissions
     * (PermissionCatalog::OWNER_ONLY). Anything else the admin must not do on the organization's behalf (spending the
     * organization's credit, TASK-0021's credit part) is added to this list, not to the role line.
     *
     * @return list<string>
     */
    public static function orgAdminWithheld(): array
    {
        return array_values(array_unique(array_merge(PermissionCatalog::OWNER_ONLY, self::CREDIT_SPENDING)));
    }

    /**
     * Owner decision 20: the organization's credit is spent by the owner and the billing admin. The organization admin runs the
     * organization day to day and still orders — paid by card or transfer at once, from credit after an owner or billing admin
     * approved it (Orders\CreditOrderPolicy).
     */
    public const CREDIT_SPENDING = ['billing.wallet.spend'];

    /** What the billing admin holds besides the billing line itself (owner decision 20). */
    public const BILLING_ADMIN_EXTRA = [...self::CREDIT_SPENDING, ...self::PARTNER_PORTAL];
    // ── end TASK-0021 ──

    // ── TASK-0040 (permission program IF-14, audit P5) ──
    /**
     * The partner portal (clients, commissions, payouts) is read by the roles that run the partnership: the owner and the
     * organization admin (every customer permission but the owner's), the billing admin and the partner role. It used to
     * need `organization.read` only, which every member holds — a viewer or a developer read the partner's client list.
     */
    public const PARTNER_PORTAL = ['partner.portal.read'];
    // ── end TASK-0040 ──

    // ── TASK-0043 (permission program S1-03, principle 11) ──
    /**
     * What `service.manage` was split into. A role that holds `service.manage` holds both, whatever its line lists, so the actions
     * that ask for them now (ServiceActionCommand::PERMISSIONS) stay exactly where they were for every existing preset and every
     * stored `svc_*` share — frozen by PresetsUnchangedTest against the task base. Only the new presets hold one without the other.
     */
    public const MANAGE_SPLIT = ['service.operate', 'service.data.delete'];

    /** @param list<string> $permissions @return list<string> */
    private static function withManageSplit(array $permissions): array
    {
        return in_array('service.manage', $permissions, true) ? [...$permissions, ...self::MANAGE_SPLIT] : $permissions;
    }
    // ── end TASK-0043 ──

    /** @param list<string> $permissions */
    private static function role(string $name, string $description, string $scope, bool $staff, array $permissions): array
    {
        return ['name' => $name, 'description' => $description, 'scope' => $scope, 'staff' => $staff, 'permissions' => array_values(array_unique(self::withManageSplit($permissions)))];
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    /** @return list<string> roles a person can hold in an organization or a project (not the single-service capabilities) */
    public static function customerRoleKeys(): array
    {
        return array_keys(array_filter(self::all(), fn ($r) => ! $r['staff'] && $r['scope'] !== 'resource'));
    }

    /** A capability on one service is not a role somebody can be given in an organization or a project. */
    public static function isResourceRole(string $key): bool
    {
        return (self::all()[$key]['scope'] ?? null) === 'resource';
    }
}
