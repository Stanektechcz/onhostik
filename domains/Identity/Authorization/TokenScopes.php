<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Authorization;

use Onhost\Domain\Identity\Models\PersonalAccessToken;

/**
 * What an API token may do: ONE explicit map from a catalogue permission to the token scope that carries it (C13-H2c).
 *
 * The decision was a chain of prefixes in ApiContext, and everything under `service.` that was not managing or deleting
 * fell to `services:read`: a read-only token opened a noVNC console, and the portal's "operate services" preset (read +
 * power) ran commands and put SSH keys on a server. Deny by default now: a permission missing here — or decided `null` —
 * is not available to API tokens at all. ApiTokenScopeMapTest fails when the catalogue gains a key without a decision here.
 */
final class TokenScopes
{
    public const SERVICES_READ = 'services:read';

    public const SERVICES_POWER = 'services:power';

    public const SERVICES_CONSOLE = 'services:console';

    public const INVOICES_READ = 'invoices:read';

    public const TICKETS_WRITE = 'tickets:write';

    public const DNS_WRITE = 'dns:write';

    public const DOMAINS_READ = 'domains:read';

    public const WALLET_READ = 'wallet:read';

    /** Every scope a token can be issued with — the seven documented ones in their old order, then the console. */
    public const ALL = [self::SERVICES_READ, self::SERVICES_POWER, self::INVOICES_READ, self::TICKETS_WRITE, self::DNS_WRITE, self::DOMAINS_READ, self::WALLET_READ, self::SERVICES_CONSOLE];

    /** Scopes no preset of the token form carries: a customer ticks them on purpose, with the warning next to them. */
    public const EXPLICIT_ONLY = [self::SERVICES_CONSOLE];

    /** permission → scope; null = not available to API tokens. One row per PermissionCatalog key. @var array<string, ?string> */
    private const DECISIONS = [
        // ── customer: organization & identity — the account itself stays with the portal (a token cannot manage tokens, members or MFA)
        'organization.read' => null,
        'organization.manage' => null,
        'organization.members.manage' => null,
        'organization.close' => null,
        'project.manage' => null,
        'api_token.manage' => null,
        'security.settings.manage' => null,
        'audit.read' => null,
        'data_export.request' => null,

        // ── customer: billing — reading invoices and the balance; nothing that moves or spends money
        'billing.wallet.read' => self::WALLET_READ,
        'billing.wallet.topup' => null,
        'billing.invoice.read' => self::INVOICES_READ,
        'billing.payment_method.manage' => null,
        'billing.budget.manage' => null,
        'catalog.order.create' => null,
        'billing.wallet.spend' => null,

        // ── customer: services — reading, operating (power), and the console as a scope of its own (C13-H2c): a console,
        //    a terminal, a command or an SSH key is neither a read nor a restart
        'service.read' => self::SERVICES_READ,
        'backup.read' => self::SERVICES_READ,
        'service.manage' => self::SERVICES_POWER,
        'service.delete' => self::SERVICES_POWER, // HIGH: still refused through a token by the step-up rule (a token session never holds one)
        'backup.restore' => self::SERVICES_POWER, // HIGH: as above
        // CRITICAL in the catalogue, but the bus forces CRITICAL only for staff permissions: the command's own risk decides. The
        // `backup.delete` service action asks for this permission with a step-up only with TASK-0029's action map (before it:
        // service.manage, NORMAL — ApiTokenScopeMapTest "refuses deleting a backup through a token", skipped until then)
        'backup.delete' => self::SERVICES_POWER,
        'compute.vm.manage' => self::SERVICES_POWER,
        'compute.vm.delete' => self::SERVICES_POWER,
        'game.manage' => self::SERVICES_POWER,
        'apps.deploy' => self::SERVICES_POWER,
        'mail.manage' => self::SERVICES_POWER,
        'database.manage' => self::SERVICES_POWER,
        'service.console' => self::SERVICES_CONSOLE,
        'service.credentials.rotate' => null,
        'service.panel_account.manage' => null, // the panel account opens every server of the account: owner only, portal only
        'backup.download' => null, // an archive holds wp-config.php and .env: taking the data away was never open to tokens and stays closed

        // ── customer: domains & DNS — domains are read, zones are written; registrant, transfers and registration stay in the portal
        'domain.read' => self::DOMAINS_READ,
        'domain.manage' => null,
        'domain.transfer_out.execute' => null,
        'domain.registrant.change' => null,
        'dns.zone.read' => self::DNS_WRITE,
        'dns.zone.write' => self::DNS_WRITE,
        'dns.dnssec.manage' => self::DNS_WRITE, // HIGH: refused through a token by the step-up rule

        // ── customer: support — tickets are one scope for reading and writing, as they always were; the assistant is the portal's
        'support.ticket.read' => self::TICKETS_WRITE,
        'support.ticket.write' => self::TICKETS_WRITE,
        'support.chat.use' => null,

        // ── staff: every staff permission — a token is a customer's tool; staff routes are closed to tokens (TokenRouteScope)
        'staff.customer.read' => null,
        'staff.customer.manage' => null,
        'staff.order.manage' => null,
        'staff.service.manage' => null,
        'staff.service.delete' => null,
        'staff.console' => null,
        'support.customer_impersonate' => null,
        'provisioning.operation.read' => null,
        'provisioning.operation.retry' => null,
        'provisioning.operation.cancel' => null,
        'provisioning.drift.resolve' => null,
        'provisioning.freeze' => null,
        'provider.instance.read' => null,
        'provider.instance.manage' => null,
        'provider.secret.view' => null,
        'capacity.read' => null,
        'capacity.manage' => null,
        'node.manage' => null,
        'ipam.manage' => null,
        'backup.policy.manage' => null,
        'domain.registrar.manage' => null,
        'dns.global.write' => null,
        'domain.critical.manage' => null,
        'billing.invoice.manage' => null,
        'billing.refund.execute' => null,
        'billing.refund.execute_large' => null,
        'billing.credit.adjust' => null,
        'billing.credit.adjust_mass' => null,
        'billing.tax_rule.manage' => null,
        'billing.reconcile' => null,
        'billing.dunning.manage' => null,
        'billing.credit_line.manage' => null,
        'report.read' => null,
        'support.ticket.assign' => null,
        'support.ticket.manage' => null,
        'support.queue.manage' => null,
        'support.kb.manage' => null,
        'incident.manage' => null,
        'incident.publish' => null,
        'maintenance.manage' => null,
        'sla.credit.manage' => null,
        'notification.template.manage' => null,
        'notification.mass.send' => null,
        'security.incident.manage' => null,
        'security.event.read' => null,
        'abuse.case.manage' => null,
        'compliance.case.manage' => null,
        'compliance.legal_hold.manage' => null,
        'iam.user.manage' => null,
        'iam.role.manage' => null,
        'iam.mfa.reset' => null,
        'iam.jit.request' => null,
        'iam.jit.approve' => null,
        'iam.approval.decide' => null,
        'iam.access_review.manage' => null,
        'iam.break_glass' => null,
        'secret.rotate' => null,
        'audit.read.global' => null,
        'ai.policy.manage' => null,
        'ai.ops.read' => null,
        'content.manage' => null,
        'catalog.manage' => null,
        'partner.manage' => null,
        'feature_flag.manage' => null,
        'billing.limit_raise.waive' => null,

        // ── TASK-0031 ──
        // ── end TASK-0031 ──
    ];

    /** The scope a token needs for `$permission`; null = not available to API tokens (unknown permissions included). */
    public static function for(?string $permission): ?string
    {
        return $permission === null ? null : (self::DECISIONS[$permission] ?? null);
    }

    /** @return array<string, ?string> */
    public static function decisions(): array
    {
        return self::DECISIONS;
    }

    /** The personal access token a request is made with, or null for the portal's own session (and Sanctum's transient token). */
    public static function tokenOf(mixed $user): ?PersonalAccessToken
    {
        $token = is_object($user) && method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;

        return $token instanceof PersonalAccessToken ? $token : null;
    }
}
