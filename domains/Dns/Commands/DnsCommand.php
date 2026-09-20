<?php

declare(strict_types=1);

namespace Onhost\Domain\Dns\Commands;

use Onhost\Platform\Commands\OrganizationCommand;

/**
 * DNS zone edits, dispatched by `op` (blueprint §48, S39):
 *  create_zone{name,template?,vars?} · stage{zone_id,op:add|update|delete,record{},record_id?,confirm_protected?,reason?} ·
 *  discard{zone_id} · commit{zone_id,reason?} · rollback{zone_id,version} · dnssec{zone_id,enabled} · delete_zone{zone_id,reason} ·
 *  republish{zone_id,reason?} — the provider is made to serve what the platform holds (the repair after `onhost:dns:drift`)
 */
final class DnsCommand extends OrganizationCommand
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
}
