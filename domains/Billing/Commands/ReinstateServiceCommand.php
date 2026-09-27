<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Domain\Identity\Authorization\StaffActor;
use Onhost\Domain\Identity\Authorization\StaffModeCommand;
use Onhost\Platform\Commands\OrganizationCommand;

/**
 * Pay and restore (TASK-0025): the customer brings a cancelled service back inside its restore window and pays what it
 * owes from the organization's credit. It is a payment from the credit like an invoice paid from it
 * (`invoice.pay_from_wallet`): the same bus permission, and inside it the one credit gate `Orders\CreditOrderPolicy`
 * (TASK-0027) — while `onhost.orders.credit_approval.enabled` is on only the owner and the billing admin
 * (`billing.wallet.spend`) spend the credit. The amount is the stated quote for the customer's own service, the same risk
 * as paying an invoice from the credit: NORMAL, no step-up. Not available to API tokens (no token scope maps to it).
 */
final class ReinstateServiceCommand extends OrganizationCommand implements RiskAwareCommand, StaffModeCommand
{
    /** Whoever may pay the organization's invoices from its credit may ask; CreditOrderPolicy decides whether they may spend it. */
    public const PERMISSION = 'billing.wallet.topup';

    public function permission(): string
    {
        return self::PERMISSION;
    }

    // ── TASK-0039 P0-16 re-check (staff mode asks staff keys) ──
    /**
     * On /v1/staff/services/{id}/reinstate staff restore on the platform's authority (ServiceReinstatement skips the credit gate for
     * them): the staff billing key, not the customer's `billing.wallet.topup`, which the staff person's own membership satisfied.
     */
    public function staffPermission(): string
    {
        return StaffActor::BILLING_KEY;
    }
    // ── end TASK-0039 P0-16 re-check ──

    public function name(): string
    {
        return 'service.reinstate';
    }

    public function riskLevel(): string
    {
        return PermissionCatalog::NORMAL;
    }

    public function requiresStepUp(): bool
    {
        return false;
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
