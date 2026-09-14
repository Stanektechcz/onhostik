<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\OrganizationCommand;

/**
 * One command class for the domain platform, dispatched by `op` (blueprint §46):
 *  renew{fqdn,years} · nameservers{fqdn,nameservers[],dns_provider?} · use_onhost_dns{fqdn,template?,vars?} ·
 *  auto_renew{fqdn,enabled} · transfer_lock{fqdn,locked} · auth_info{fqdn} · transfer_in{fqdn,auth_info,registrant…} ·
 *  publish_ds{fqdn} · contact{…} · register{fqdn,period,registrant…,consent} (staff/manual; customers order through checkout)
 */
final class DomainCommand extends OrganizationCommand implements RiskAwareCommand
{
    public const OPS = ['renew', 'nameservers', 'use_onhost_dns', 'auto_renew', 'transfer_lock', 'auth_info', 'transfer_in', 'publish_ds', 'contact', 'register'];

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): ?string
    {
        return match ($this->op()) {
            'auth_info' => 'domain.transfer_out.execute',
            'publish_ds' => 'dns.dnssec.manage',
            'contact' => 'domain.registrant.change',
            default => 'domain.manage',
        };
    }

    public function name(): string
    {
        return 'domain.'.$this->op();
    }

    public function riskLevel(): string
    {
        return match ($this->op()) {
            'auth_info', 'transfer_in', 'nameservers', 'transfer_lock' => PermissionCatalog::HIGH,
            default => PermissionCatalog::NORMAL,
        };
    }

    public function requiresStepUp(): bool
    {
        return in_array($this->op(), ['auth_info', 'transfer_in'], true) || ($this->op() === 'transfer_lock' && $this->get('locked') === false);
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
