<?php

declare(strict_types=1);

namespace Onhost\Domain\Content\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * E2: the public waitlist, dispatched by `op`: signup{email, product, consent_version, locale, ip} · confirm{token} ·
 * withdraw{token}. Anybody may ask (no permission, like accepting an invitation): the token is the proof for confirm and
 * withdraw. Routine risk — no money, one e-mail address with a narrow purpose. The audit keeps the operation and the product,
 * never the address, the token or the IP.
 */
final class WaitlistCommand extends GlobalCommand implements RiskAwareCommand
{
    public const OPS = ['signup', 'confirm', 'withdraw'];

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): ?string
    {
        return null;
    }

    public function name(): string
    {
        return 'waitlist.'.$this->op();
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

    public function toAudit(): array
    {
        return ['payload' => array_filter(['op' => $this->op(), 'product' => $this->get('product'), 'consent_version' => $this->get('consent_version')], fn ($v) => $v !== null)];
    }
}
