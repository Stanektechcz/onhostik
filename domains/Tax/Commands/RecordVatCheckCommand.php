<?php

declare(strict_types=1);

namespace Onhost\Domain\Tax\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\OrganizationCommand;

/**
 * Records one VIES verdict about an organization's VAT number (TASK-0031, D31.3). Payload: `number` (the number that was
 * asked about, with its prefix), `status` valid|invalid, `consultation_number`, `name`, `address`, `request_date`,
 * `requester_vat_id`, `trigger` (vat_id_changed | checkout | recheck | operator), `source` (vies | format), `error_code`.
 * Only the platform's own check sends it (VatNumberChecks, system actor); the handler refuses anybody else — a customer
 * cannot declare their own number valid. Idempotency key: `vat-check:{org}:{number}:{ulid}`, one per answer.
 * The trader's name and address stay out of the audit trail; they are evidence in vat_validations only.
 */
final class RecordVatCheckCommand extends OrganizationCommand implements RiskAwareCommand
{
    protected const AUDIT_STRIP = ['password', 'auth_info', 'secret', 'code', 'token', 'totp', 'recovery_code', 'name', 'address'];

    public function permission(): ?string
    {
        return null; // system-internal: no customer or staff permission reaches it (the handler checks the actor)
    }

    public function name(): string
    {
        return 'tax.vat_number.record';
    }

    /** It writes what the register answered; the tax decision that uses it is made elsewhere. */
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
