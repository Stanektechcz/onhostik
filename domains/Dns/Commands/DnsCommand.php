<?php

declare(strict_types=1);

namespace Onhost\Domain\Dns\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\OrganizationCommand;

/**
 * DNS zone edits, dispatched by `op` (blueprint §48, S39):
 *  create_zone{name,template?,vars?} · stage{zone_id,op:add|update|delete,record{},record_id?,confirm_protected?,reason?} ·
 *  discard{zone_id} · commit{zone_id,reason?} · rollback{zone_id,version} · dnssec{zone_id,enabled} · delete_zone{zone_id,reason} ·
 *  republish{zone_id,reason?} — the provider is made to serve what the platform holds (the repair after `onhost:dns:drift`)
 *
 * Deleting a zone and rolling one back are HIGH (TASK-0056): a deleted zone takes every record and every version with it, and a
 * rollback replaces the live set with an older one — both need a fresh step-up, like the DNSSEC switch beside them.
 */
final class DnsCommand extends OrganizationCommand implements RiskAwareCommand
{
    public const OPS = ['create_zone', 'stage', 'discard', 'commit', 'rollback', 'dnssec', 'delete_zone', 'republish'];

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): ?string
    {
        return $this->op() === 'dnssec' ? 'dns.dnssec.manage' : 'dns.zone.write';
    }

    public function name(): string
    {
        return 'dns.'.$this->op();
    }

    public function riskLevel(): string
    {
        return in_array($this->op(), ['delete_zone', 'rollback'], true) || $this->op() === 'dnssec' ? PermissionCatalog::HIGH : PermissionCatalog::NORMAL;
    }

    public function requiresStepUp(): bool
    {
        return in_array($this->op(), ['delete_zone', 'rollback'], true);
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
