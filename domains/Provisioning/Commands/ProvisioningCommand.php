<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * Staff provisioning controls, dispatched by `op`:
 *  retry{operation_id,reason?} · cancel{operation_id,reason} · resolve_drift{drift_id,resolution:approved|ignored|repair,note} ·
 *  freeze{reason} · thaw{} · reconcile{service_id}
 */
final class ProvisioningCommand extends GlobalCommand implements RiskAwareCommand
{
    public const OPS = ['retry', 'cancel', 'resolve_drift', 'freeze', 'thaw', 'load.set', 'reconcile', 'instance.upsert', 'instance.probe', 'instance.state', 'instance.discover', 'node.upsert', 'placement.upsert', 'placement.delete', 'registrar.costs.refresh', 'registrar.costs.scrape', 'registrar.costs.upsert', 'registrar.policy.set', 'service.create'];

    protected const AUDIT_STRIP = ['password', 'secret', 'token', 'credentials'];

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): ?string
    {
        return match ($this->op()) {
            'retry' => 'provisioning.operation.retry',
            'cancel' => 'provisioning.operation.cancel',
            'resolve_drift' => 'provisioning.drift.resolve',
            'freeze', 'thaw', 'load.set', 'automation.toggle', 'automation.risk' => 'provisioning.freeze',
            'reconcile', 'bulk.start', 'service.create' => 'staff.service.manage',
            'instance.upsert', 'instance.state', 'instance.discover', 'node.upsert', 'node.state', 'placement.upsert', 'placement.delete', 'registrar.costs.refresh', 'registrar.costs.scrape', 'registrar.costs.upsert', 'registrar.policy.set', 'game.eggs.map', 'game.eggs.sync', 'game.bootstrap', 'game.allocations.create', 'game.node.update', 'game.operator_variable.set', 'game.migrate', 'game.evacuate', 'service.migrate', 'service.evacuate', 'rebalance.apply' => 'provider.instance.manage',
            'tenant.sandbox' => 'staff.customer.manage',
            'instance.probe', 'instance.prereqs' => 'provider.instance.read',
            default => 'provisioning.operation.read',
        };
    }

    public function name(): string
    {
        return 'provisioning.'.$this->op();
    }

    public function riskLevel(): string
    {
        return in_array($this->op(), ['freeze', 'thaw', 'cancel', 'instance.upsert', 'instance.state', 'game.operator_variable.set'], true) ? PermissionCatalog::HIGH : PermissionCatalog::NORMAL;
    }

    /** Registering credentials / changing base URLs touches production executors: fresh step-up. */
    public function requiresStepUp(): bool
    {
        return in_array($this->op(), ['freeze', 'thaw', 'instance.upsert', 'instance.state', 'game.operator_variable.set'], true); // §5t-1: the Steam account behind a template
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
