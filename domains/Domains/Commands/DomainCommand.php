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
 *  publish_ds{fqdn} · contact{…} · holder{fqdn,holder{email,phone,street,city,postal_code,country}} · register{fqdn,period,registrant…,consent} (staff/manual; customers order through checkout)
 */
final class DomainCommand extends OrganizationCommand implements RiskAwareCommand
{
    public const OPS = ['renew', 'nameservers', 'use_onhost_dns', 'auto_renew', 'transfer_lock', 'auth_info', 'transfer_in', 'publish_ds', 'contact', 'holder', 'register'];

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): ?string
    {
        return match ($this->op()) {
            'auth_info' => 'domain.transfer_out.execute',
            'publish_ds' => 'dns.dnssec.manage',
            'contact', 'holder' => 'domain.registrant.change',
            default => 'domain.manage',
        };
    }

    public function name(): string
    {
        return 'domain.'.$this->op();
    }

    /**
     * The key the BUS keeps its answer under: the caller's key plus a keyed fingerprint of what was asked (TASK-0041,
     * permission program IF-12 / P0-10 follow-up; the same rule as ServiceActionCommand::idempotencyKey). The bus answers a
     * known key before the handler runs, so the same key with another body was answered with the first run whenever the
     * HTTP layer had kept nothing (a crash after the commit, a 5xx) — auto-renew switched off stayed off while the answer
     * said so, and DomainService's own 409 for a changed request never got the chance. The handler still gets the caller's
     * key alone (`$this->idempotencyKey`), so an operation is found by it and a changed request is refused there; the
     * controller refuses it for the instant ops (DomainController::assertKeyUnused). Keyed with app.key: a transfer-in
     * carries its transfer code, and this key is stored in clear for a day. The same request still replays as before.
     */
    public function idempotencyKey(): string
    {
        return $this->idempotencyKey.'#'.substr(hash_hmac('sha256', (string) json_encode($this->payload), (string) config('app.key')), 0, 16);
    }

    public function riskLevel(): string
    {
        return match ($this->op()) {
            'auth_info', 'transfer_in', 'nameservers', 'transfer_lock', 'holder' => PermissionCatalog::HIGH,
            default => PermissionCatalog::NORMAL,
        };
    }

    public function requiresStepUp(): bool
    {
        return in_array($this->op(), ['auth_info', 'transfer_in', 'holder'], true) || ($this->op() === 'transfer_lock' && $this->get('locked') === false);
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
