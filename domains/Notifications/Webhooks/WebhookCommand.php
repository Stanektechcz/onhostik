<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications\Webhooks;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\OrganizationCommand;

/**
 * Customer webhook endpoints, dispatched by `op` (D4):
 *  create{url, events[]} · disable{endpoint_id} · enable{endpoint_id} · rotate_secret{endpoint_id}
 *  redeliver{endpoint_id, delivery_id} · ping{endpoint_id}
 *
 * `create` and `rotate_secret` are HIGH (fresh step-up): one adds a standing destination that receives the organization's
 * events, the other hands out a new signing secret — the same weight as an API token. Turning an endpoint off or on,
 * sending a test event and sending a delivery again stay NORMAL.
 */
final class WebhookCommand extends OrganizationCommand implements RiskAwareCommand
{
    public const OPS = ['create', 'disable', 'enable', 'rotate_secret', 'redeliver', 'ping'];

    private const HIGH = ['create', 'rotate_secret'];

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): string
    {
        return 'organization.manage';
    }

    public function name(): string
    {
        return 'webhook.'.$this->op();
    }

    public function riskLevel(): string
    {
        return in_array($this->op(), self::HIGH, true) ? PermissionCatalog::HIGH : PermissionCatalog::NORMAL;
    }

    public function requiresStepUp(): bool
    {
        return in_array($this->op(), self::HIGH, true);
    }

    public function requiresApproval(): bool
    {
        return false;
    }

    /** A chat tool's incoming-webhook URL carries its credential in the path (Slack, Discord, Teams): the audit keeps the host. */
    public function toAudit(): array
    {
        $audit = parent::toAudit();
        if (isset($audit['payload']['url'])) {
            $audit['payload']['url'] = (string) parse_url((string) $audit['payload']['url'], PHP_URL_HOST);
        }

        return $audit;
    }
}
