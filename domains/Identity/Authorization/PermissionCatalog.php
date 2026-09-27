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

    // ── TASK-0021 ──
    /**
     * Customer permissions that belong to the organization owner alone (owner decisions 14/15): no admin, operator, shared
     * (svc_*) or staff role holds them. RoleCatalog withholds them from org_admin; PermissionMatrixTest keeps it that way.
     */
    public const OWNER_ONLY = ['organization.close', 'service.panel_account.manage', ...self::PARTNER_OWNER_ONLY];
    // ── end TASK-0021 ──

    // ── TASK-0040 (permission program IF-14, audit P2) ──
    /** Where the partner's commission is paid is the owner's decision alone: no admin, billing role or staff sets it. */
    public const PARTNER_OWNER_ONLY = ['partner.payout_account.manage'];
    // ── end TASK-0040 ──

    // ── TASK-0037 (permission program IF-13, principle 6 "risk only goes up") ──
    /**
     * Operations allowed to run BELOW the risk of their permission: `command name => [permission => why]`. Empty, and pinned
     * empty by RiskFloorTest — an entry is a reviewed decision, never a side effect. IdentityCommandAuthorizer used to trust a
     * command's own riskLevel(), so `publish_ds` (a DS record at the registry under the HIGH `dns.dnssec.manage`), a registrant
     * contact change and a dozen staff operations under HIGH permissions ran without a step-up (audit G4).
     *
     * @var array<string, array<string, string>>
     */
    public const LOWERED_RISK = [];
    // ── end TASK-0037 ──

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
            // TASK-0021 (owner decision 20): paying from the organization's credit — the owner and the billing admin; anybody else's credit order waits for them
            'billing.wallet.spend' => $c('Pay from account credit (orders, invoices, renewals) and approve credit-paid orders of other members'),

            // ── customer: services ───────────────────────────────────────────
            'service.read' => $c('View services, metrics, logs and activity'),
            'service.manage' => $c('Start/stop/restart, resize, configure services'),
            'service.delete' => $c('Terminate services (with retention grace)', self::HIGH),
            'service.console' => $c('Open consoles and shells, set root access and SSH keys, rescue mode, game sub-users and console schedules'),
            'service.credentials.rotate' => $c('Rotate service credentials', self::HIGH),
            // TASK-0021 (owner decision 15): the panel account opens every server of the account — the organization owner alone
            'service.panel_account.manage' => $c('Set the password of the service panel account (game panel); organization owner only', self::HIGH),
            'compute.vm.manage' => $c('Manage VPS/VDS: power, resize, snapshots, firewall'),
            'compute.vm.delete' => $c('Delete VMs and snapshots', self::HIGH),
            'apps.deploy' => $c('Deploy, roll back and configure applications'),
            'game.manage' => $c('Manage game servers, mods, schedules, sub-users'),
            'mail.manage' => $c('Manage mail domains, mailboxes, aliases, relay'),
            'database.manage' => $c('Manage managed databases and users'),
            'backup.read' => $c('View backups and restore points'),
            'backup.download' => $c('Download backup archives and data exports'), // seeing that a backup exists is not taking the data away (H344)
            'backup.restore' => $c('Restore from backups', self::HIGH),
            // CRITICAL stays as documentation of what deletion is; the customer action (ServiceActionCommand) is HIGH with a fresh
            // step-up, because IdentityCommandAuthorizer forces CRITICAL for staff-audience permissions only and customers have no
            // four-eyes (TASK-0029, D29.2)
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
            // ── TASK-0022 limit-raise: a raise of a limit at no charge is money given away — a second person (docs/runbooks/approvals.md) ──
            'billing.limit_raise.waive' => $s('Grant a limit raise at no charge for one period (four eyes)', self::CRITICAL),
            // ── TASK-0037 (program IF-18, critic D18): staff read with staff keys. The staff queue and the finance lists asked for
            // the CUSTOMER keys at global scope; these land first so P0-15 can take the customer keys away from staff roles
            // without 403-ing the support desk ──
            'staff.support.ticket.read' => $s('Read the support ticket queue and tickets of every customer'),
            'staff.backup.read' => $s('View backups and restore points of customer services (operations)'),
            'staff.billing.read' => $s('View invoices, withdrawals and billing records of every customer (finance)'),
            // ── end TASK-0037 ──
            // ── TASK-0040 (permission program IF-14, audit P2/P5): the partner portal is read by the roles that run the partnership,
            // not by every member (client names, commissions and payouts); the payout account is the owner's, with a step-up ──
            'partner.portal.read' => $c('View the partner portal: clients, commissions, payouts and the payout account'),
            'partner.payout_account.manage' => $c('Set the bank account partner commissions are paid to (organization owner only; usable after a cooling-off)', self::HIGH),
            // ── end TASK-0040 ──
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

    // ── TASK-0037 ──
    /**
     * The lowest risk an action under `$permission` may run at. The catalogue's risk — except that a CUSTOMER permission rated
     * CRITICAL floors at HIGH: a customer has no second person to ask (approvals are staff-only until the customer decider rule,
     * program D8/S4-03), and routing a customer's own action through the staff approval queue was rejected (D8). The GDPR
     * erasure (`organization.close`) and deleting a backup generation (`backup.delete`) therefore stay what their commands
     * always made them: a fresh step-up.
     */
    public static function floor(string $permission): string
    {
        $risk = self::risk($permission);

        return $risk === self::CRITICAL && (self::all()[$permission]['audience'] ?? null) === 'customer' ? self::HIGH : $risk;
    }

    /**
     * What the bus enforces: the higher of what the command declares and the floor of its permission (max(declared, catalogue),
     * program §3 "Verification"). A command that declares nothing (not RiskAwareCommand) runs at the catalogue's risk, as before.
     */
    public static function effectiveRisk(string $permission, ?string $declared, ?string $commandName = null): string
    {
        if ($declared === null) {
            return self::risk($permission);
        }
        if ($commandName !== null && isset(self::loweredRisk()[$commandName][$permission])) {
            return $declared;
        }
        $floor = self::floor($permission);

        return self::rank($declared) >= self::rank($floor) ? $declared : $floor;
    }

    /**
     * LOWERED_RISK read as what it may hold, not as the empty list it is today (a reviewed entry must work the day it is added).
     *
     * @return array<string, array<string, string>>
     */
    public static function loweredRisk(): array
    {
        return self::LOWERED_RISK;
    }

    private static function rank(string $risk): int
    {
        return match ($risk) {
            self::CRITICAL => 2,
            self::HIGH => 1,
            default => 0,
        };
    }
    // ── end TASK-0037 ──

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
