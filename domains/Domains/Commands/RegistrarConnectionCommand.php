<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\OrganizationCommand;

/**
 * A customer's connected registrar account, dispatched by `op`:
 *  connect{provider,login,password,label?,customer_number?} · probe{connection_id} · sync{connection_id} · settings{connection_id,…}
 *  · disconnect{connection_id} · pair{domain_id,service_id} · unpair{domain_id}
 *  staff: disable{connection_id,reason?} · enable{connection_id} · staff_sync{connection_id}
 * Storing or dropping credentials is HIGH risk (fresh step-up); the password never reaches the audit trail.
 */
final class RegistrarConnectionCommand extends OrganizationCommand implements RiskAwareCommand
{
    public const OPS = ['connect', 'probe', 'sync', 'settings', 'disconnect', 'pair', 'unpair', 'disable', 'enable', 'staff_sync'];

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): ?string
    {
        return match ($this->op()) {
            'disable', 'enable', 'staff_sync' => 'domain.registrar.manage',
            default => 'domain.manage',
        };
    }

    public function name(): string
    {
        return 'registrar_connection.'.$this->op();
    }

    public function riskLevel(): string
    {
        return in_array($this->op(), ['connect', 'disconnect'], true) ? PermissionCatalog::HIGH : PermissionCatalog::NORMAL;
    }

    public function requiresStepUp(): bool
    {
        return in_array($this->op(), ['connect', 'disconnect'], true);
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
