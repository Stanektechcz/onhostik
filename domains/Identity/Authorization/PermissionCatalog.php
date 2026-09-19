<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Authorization;

/**
 * Canonical capability list (blueprint §61.3). Never `if role === admin` in code —
 * everything is a capability with a risk class. `high` => WebAuthn/TOTP step-up,
 * `critical` => step-up + two-person approval (§61.6).
 */
final class PermissionCatalog
{
    public const NORMAL = 'normal';

    public const HIGH = 'high';

    public const CRITICAL = 'critical';

    /**
     * @return array<string, array{description:string, risk:string, audience:string}>
     */
    public static function all(): array
    {
        $c = fn (string $description, string $risk = self::NORMAL) => ['description' => $description, 'risk' => $risk, 'audience' => 'customer'];
        $s = fn (string $description, string $risk = self::NORMAL) => ['description' => $description, 'risk' => $risk, 'audience' => 'staff'];

        return [
            // ── customer: organization & identity ─────────────────────────────
            'organization.read' => $c('View organization profile, members and projects'),
            'organization.manage' => $c('Edit organization profile and settings'),
            'organization.members.manage' => $c('Invite, remove and change roles of members', self::HIGH),
            'organization.close' => $c('Close the organization and schedule data deletion', self::CRITICAL),
            'project.manage' => $c('Create and edit projects'),
            'api_token.manage' => $c('Create and revoke API tokens and service accounts', self::HIGH),
            'security.settings.manage' => $c('Manage MFA enforcement, IP allow-lists, sessions', self::HIGH),
            'audit.read' => $c('Read the organization audit log'),
            'data_export.request' => $c('Request portable data export (Data Act)'),

            // ── customer: billing ────────────────────────────────────────────
            'billing.wallet.read' => $c('View wallet balance, holds and usage'),
            'billing.wallet.topup' => $c('Top up the wallet and manage auto top-up'),
            'billing.invoice.read' => $c('View and download invoices, credit notes, receipts'),
            'billing.payment_method.manage' => $c('Manage saved payment methods', self::HIGH),
            'billing.budget.manage' => $c('Set budgets, spend limits and alerts'),
            'catalog.order.create' => $c('Place orders and change plans'),

            // ── customer: services ───────────────────────────────────────────
            'service.read' => $c('View services, metrics, logs and activity'),
            'service.manage' => $c('Start/stop/restart, resize, configure services'),
            'service.delete' => $c('Terminate services (with retention grace)', self::HIGH),
            'service.console' => $c('Open consoles and shells for own services'),
            'service.credentials.rotate' => $c('Rotate service credentials', self::HIGH),
            'compute.vm.manage' => $c('Manage VPS/VDS: power, resize, snapshots, firewall'),
            'compute.vm.delete' => $c('Delete VMs and snapshots', self::HIGH),
            'apps.deploy' => $c('Deploy, roll back and configure applications'),
            'game.manage' => $c('Manage game servers, mods, schedules, sub-users'),
            'mail.manage' => $c('Manage mail domains, mailboxes, aliases, relay'),
            'database.manage' => $c('Manage managed databases and users'),
            'backup.read' => $c('View backups and restore points'),
            'backup.download' => $c('Download backup archives and data exports'), // seeing that a backup exists is not taking the data away (H344)
            'backup.restore' => $c('Restore from backups', self::HIGH),
            'backup.delete' => $c('Delete backup generations', self::CRITICAL),

            // ── customer: domains & DNS ──────────────────────────────────────
            'domain.read' => $c('View domains, expiry and registrar status'),
            'domain.manage' => $c('Register, renew, set auto-renew, manage contacts'),
            'domain.transfer_out.execute' => $c('Reveal AUTH-ID / transfer domain away', self::HIGH),
            'domain.registrant.change' => $c('Change registrant of a domain', self::HIGH),
            'dns.zone.read' => $c('View DNS zones and history'),
            'dns.zone.write' => $c('Edit DNS records and publish changes'),
            'dns.dnssec.manage' => $c('Enable/disable DNSSEC, replace keys', self::HIGH),

            // ── customer: support ────────────────────────────────────────────
            'support.ticket.read' => $c('Read tickets of the organization'),
            'support.ticket.write' => $c('Open tickets and reply'),
            'support.chat.use' => $c('Use AI assistant and live chat'),

            // ── staff: customers & operations ────────────────────────────────
            'staff.customer.read' => $s('Customer 360 view (organizations, services, billing, tickets)'),
            'staff.customer.manage' => $s('Edit customer organizations and memberships', self::HIGH),
            'staff.order.manage' => $s('Transition orders and trigger provisioning'),
            'staff.service.manage' => $s('Suspend/resume/resize any service'),
            'staff.service.delete' => $s('Terminate any service', self::HIGH),
            'staff.console' => $s('Open console on customer resources (recorded, ticket-bound)', self::HIGH),
            'support.customer_impersonate' => $s('Impersonate a customer in the portal', self::HIGH),

            // ── staff: provisioning & providers ──────────────────────────────
            'provisioning.operation.read' => $s('View operations, jobs and provider calls'),
            'provisioning.operation.retry' => $s('Retry failed operations'),
            'provisioning.operation.cancel' => $s('Cancel operations', self::HIGH),
            'provisioning.drift.resolve' => $s('Approve or repair configuration drift', self::HIGH),
            'provisioning.freeze' => $s('Freeze all automated provisioning (incident switch)', self::HIGH),
            'provider.instance.read' => $s('View provider instances, capabilities and health'),
            'provider.instance.manage' => $s('Register/edit provider instances', self::HIGH),
            'provider.secret.view' => $s('Reveal or rotate provider credentials', self::CRITICAL),
            'capacity.read' => $s('View capacity, N+1 headroom and inventory'),
            'capacity.manage' => $s('Approve, order and close capacity requests', self::HIGH),
            'node.manage' => $s('Maintenance mode, drain, cordon of nodes', self::HIGH),
            'ipam.manage' => $s('Manage IP pools, allocations and reverse DNS', self::HIGH),
            'backup.policy.manage' => $s('Edit backup policies and retention', self::HIGH),

            // ── staff: domains/DNS ───────────────────────────────────────────
            'domain.registrar.manage' => $s('Operate registrar queue, contacts, NSSET, credit', self::HIGH),
            'dns.global.write' => $s('Change ONhost infrastructure DNS / nameservers', self::CRITICAL),
            'domain.critical.manage' => $s('Change critical ONhost-owned domains', self::CRITICAL),

            // ── staff: finance ───────────────────────────────────────────────
            'billing.invoice.manage' => $s('Issue, correct and resend invoices'),
            'billing.refund.execute' => $s('Execute refunds', self::HIGH),
            'billing.refund.execute_large' => $s('Execute refunds above the approval threshold', self::CRITICAL),
            'billing.credit.adjust' => $s('Manual wallet adjustments', self::HIGH),
            'billing.credit.adjust_mass' => $s('Mass credit adjustments', self::CRITICAL),
            'billing.tax_rule.manage' => $s('Edit tax rules and registrations', self::CRITICAL),
            'billing.reconcile' => $s('Run and resolve reconciliations'),
            'billing.dunning.manage' => $s('Run dunning, suspend and unsuspend for non-payment', self::HIGH),
            'billing.credit_line.manage' => $s('Approve B2B postpaid credit lines', self::HIGH),
            'report.read' => $s('View financial and operational reports'),

            // ── staff: support & incidents ───────────────────────────────────
            'support.ticket.assign' => $s('Assign and route tickets'),
            'support.ticket.manage' => $s('Reply, escalate, resolve tickets'),
            'support.queue.manage' => $s('Manage queues, SLA policies, macros'),
            'support.kb.manage' => $s('Edit knowledge base articles'),
            'incident.manage' => $s('Open, update and resolve incidents'),
            'incident.publish' => $s('Publish incidents to the public status page', self::HIGH),
            'maintenance.manage' => $s('Schedule maintenance windows', self::HIGH),
            'sla.credit.manage' => $s('Approve SLA credits', self::HIGH),
            'notification.template.manage' => $s('Edit notification templates'),
            'notification.mass.send' => $s('Send mass notifications', self::HIGH),

            // ── staff: security, compliance, IAM ─────────────────────────────
            'security.incident.manage' => $s('Manage security incidents, quarantine, forensics', self::HIGH),
            'security.event.read' => $s('Read security events and anomalies'),
            'abuse.case.manage' => $s('Handle DSA/abuse cases', self::HIGH),
            'compliance.case.manage' => $s('Regulatory timers, GDPR/NIS2/Data Act cases', self::HIGH),
            'compliance.legal_hold.manage' => $s('Apply or lift legal holds', self::CRITICAL),
            'iam.user.manage' => $s('Manage staff users', self::HIGH),
            'iam.role.manage' => $s('Create global staff roles / change role permissions', self::CRITICAL),
            'iam.mfa.reset' => $s('Reset MFA of another user', self::HIGH),
            'iam.jit.request' => $s('Request temporary privilege elevation'),
            'iam.jit.approve' => $s('Approve JIT elevations', self::HIGH),
            'iam.approval.decide' => $s('Approve or reject two-person approvals', self::HIGH),
            'iam.access_review.manage' => $s('Run quarterly access reviews'),
            'iam.break_glass' => $s('Break-glass emergency access', self::CRITICAL),
            'secret.rotate' => $s('Rotate high-impact secrets', self::CRITICAL),
            'audit.read.global' => $s('Read the global audit log'),
            'ai.policy.manage' => $s('Manage AI tool policies, prompts and evaluations', self::HIGH),
            'ai.ops.read' => $s('Read AI runs and evaluations'),
            'content.manage' => $s('Edit public content, changelog, docs'),
            'catalog.manage' => $s('Edit products, plans and prices', self::HIGH),
            'partner.manage' => $s('Manage reseller partners, commissions, payouts', self::HIGH),
            'feature_flag.manage' => $s('Toggle feature flags', self::HIGH),
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function risk(string $permission): string
    {
        return self::all()[$permission]['risk'] ?? self::NORMAL;
    }

    public static function requiresStepUp(string $permission): bool
    {
        return in_array(self::risk($permission), [self::HIGH, self::CRITICAL], true);
    }

    public static function requiresFourEyes(string $permission): bool
    {
        return self::risk($permission) === self::CRITICAL;
    }

    public static function exists(string $permission): bool
    {
        return array_key_exists($permission, self::all());
    }
}
