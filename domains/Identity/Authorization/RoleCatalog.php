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
            'org_admin' => self::role('Organization admin', 'Everything except closing the organization', 'organization', false, array_values(array_diff($allCustomer, ['organization.close']))),
            'billing_admin' => self::role('Billing admin', 'Wallet, invoices, payment methods, budgets', 'organization', false, array_merge($customerRead, ['billing.wallet.topup', 'billing.payment_method.manage', 'billing.budget.manage', 'catalog.order.create'])),
            'domain_manager' => self::role('Domain manager', 'Domains, contacts, renewals, transfers', 'organization', false, array_merge($customerRead, ['domain.manage', 'domain.transfer_out.execute', 'domain.registrant.change', 'dns.zone.write'])),
            'dns_manager' => self::role('DNS manager', 'DNS zones and DNSSEC', 'organization', false, array_merge($customerRead, ['dns.zone.write', 'dns.dnssec.manage'])),
            'developer' => self::role('Developer', 'Apps, deploys, databases, consoles', 'organization', false, array_merge($customerRead, ['service.manage', 'service.console', 'apps.deploy', 'database.manage', 'backup.download', 'dns.zone.write', 'support.ticket.write', 'support.chat.use'])),
            'cloud_operator' => self::role('Cloud operator', 'VPS/VDS lifecycle, snapshots, firewall, backups', 'organization', false, array_merge($customerRead, ['service.manage', 'service.console', 'compute.vm.manage', 'compute.vm.delete', 'backup.restore', 'backup.download', 'support.ticket.write', 'support.chat.use'])),
            'game_operator' => self::role('Game operator', 'Game servers, console, mods, backups', 'organization', false, array_merge($customerRead, ['service.manage', 'service.console', 'game.manage', 'backup.restore', 'backup.download', 'support.ticket.write', 'support.chat.use'])),
            'mail_manager' => self::role('Mail manager', 'Mail domains, mailboxes, DKIM/SPF/DMARC', 'organization', false, array_merge($customerRead, ['mail.manage', 'backup.download', 'dns.zone.write', 'support.ticket.write'])),
            'security_auditor' => self::role('Security auditor', 'Read-only incl. audit log and security settings', 'organization', false, array_merge($customerRead, ['security.settings.manage'])),
            'support_contact' => self::role('Support contact', 'Open and read tickets, use chat', 'organization', false, ['organization.read', 'service.read', 'support.ticket.read', 'support.ticket.write', 'support.chat.use']),
            'viewer' => self::role('Viewer', 'Read-only', 'organization', false, $customerRead),

            // ── staff roles ──────────────────────────────────────────────────
            'platform_owner' => self::role('PlatformOwner / SuperAdmin', 'Break-glass only; never a daily account', 'global', true, array_merge($allStaff, $allCustomer)),
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
            'backup_dr_admin' => self::role('BackupDRAdmin', 'PBS/restore/retention', 'global', true, ['backup.policy.manage', 'backup.restore', 'backup.delete', 'provider.instance.read', 'provisioning.operation.read', 'staff.customer.read', 'iam.jit.request']),
            'sre' => self::role('SRE', 'Metrics/incidents/maintenance', 'global', true, ['incident.manage', 'incident.publish', 'maintenance.manage', 'capacity.read', 'provider.instance.read', 'provisioning.operation.read', 'provisioning.operation.retry', 'provisioning.freeze', 'sla.credit.manage', 'report.read', 'staff.customer.read', 'iam.jit.request']),
            'incident_commander' => self::role('IncidentCommander', 'Temporary incident authority', 'global', true, ['incident.manage', 'incident.publish', 'provisioning.freeze', 'security.incident.manage', 'notification.mass.send', 'staff.customer.read', 'capacity.read', 'provisioning.operation.read']),
            'security_soc' => self::role('SecuritySOC', 'Detections/quarantine/forensics', 'global', true, ['security.incident.manage', 'security.event.read', 'audit.read.global', 'staff.service.manage', 'provisioning.freeze', 'staff.customer.read', 'iam.jit.request']),
            'abuse_trust_safety' => self::role('AbuseTrustSafety', 'DSA/abuse cases', 'global', true, ['abuse.case.manage', 'staff.customer.read', 'staff.service.manage', 'security.event.read']),
            'compliance_legal' => self::role('ComplianceLegal', 'Regulatory cases/evidence', 'global', true, ['compliance.case.manage', 'compliance.legal_hold.manage', 'abuse.case.manage', 'audit.read.global', 'staff.customer.read', 'report.read']),
            'billing_finance_admin' => self::role('BillingFinanceAdmin', 'Invoice config/tax/reconciliation', 'global', true, ['billing.invoice.manage', 'billing.refund.execute', 'billing.refund.execute_large', 'billing.credit.adjust', 'billing.credit.adjust_mass', 'billing.tax_rule.manage', 'billing.reconcile', 'billing.dunning.manage', 'billing.credit_line.manage', 'sla.credit.manage', 'partner.manage', 'report.read', 'staff.customer.read', 'iam.approval.decide']),
            'billing_operator' => self::role('BillingOperator', 'Invoice ops; limited refunds', 'global', true, ['billing.invoice.manage', 'billing.refund.execute', 'billing.reconcile', 'billing.dunning.manage', 'report.read', 'staff.customer.read']),
            'support_manager' => self::role('SupportManager', 'Queues/SLA/escalations', 'global', true, ['support.ticket.assign', 'support.ticket.manage', 'support.queue.manage', 'support.kb.manage', 'support.customer_impersonate', 'staff.customer.read', 'staff.order.manage', 'incident.manage', 'report.read', 'ai.ops.read', 'iam.jit.request']),
            'support_l1' => self::role('SupportL1', 'Read basics + safe actions', 'global', true, ['support.ticket.manage', 'staff.customer.read', 'provisioning.operation.read', 'support.chat.use']),
            'support_l2' => self::role('SupportL2', 'Deeper diagnostics + service actions', 'global', true, ['support.ticket.manage', 'support.ticket.assign', 'staff.customer.read', 'staff.service.manage', 'staff.order.manage', 'provisioning.operation.read', 'provisioning.operation.retry', 'staff.console', 'support.chat.use', 'iam.jit.request']),
            'support_l3' => self::role('SupportL3', 'Engineering escalation', 'global', true, ['support.ticket.manage', 'support.ticket.assign', 'staff.customer.read', 'staff.service.manage', 'staff.order.manage', 'provisioning.operation.read', 'provisioning.operation.retry', 'provisioning.drift.resolve', 'staff.console', 'provider.instance.read', 'incident.manage', 'iam.jit.request']),
            'sales' => self::role('Sales', 'Quotes/CRM without infra admin', 'global', true, ['staff.customer.read', 'staff.order.manage', 'report.read']),
            'marketing_content' => self::role('MarketingContent', 'Public content only', 'global', true, ['content.manage']),
            'auditor_read_only' => self::role('AuditorReadOnly', 'Immutable audit/evidence read', 'global', true, ['audit.read.global', 'security.event.read', 'report.read', 'staff.customer.read', 'provisioning.operation.read', 'ai.ops.read']),
            'partner' => self::role('Partner / Reseller', 'Sub-customers, commissions, white-label', 'organization', false, array_merge($customerRead, ['catalog.order.create', 'support.ticket.write', 'support.chat.use'])),
        ];
    }

    /** @param list<string> $permissions */
    private static function role(string $name, string $description, string $scope, bool $staff, array $permissions): array
    {
        return ['name' => $name, 'description' => $description, 'scope' => $scope, 'staff' => $staff, 'permissions' => array_values(array_unique($permissions))];
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    /** @return list<string> */
    public static function customerRoleKeys(): array
    {
        return array_keys(array_filter(self::all(), fn ($r) => ! $r['staff']));
    }
}
