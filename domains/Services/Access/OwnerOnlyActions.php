<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Access;

use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/**
 * Service actions only the organization owner may run (owner decision 15), checked where the action is requested and
 * not only by the bus: `ServiceService::requestAction` can be reached without the bus, and a later role edit (or the
 * break-glass account) could widen the permission. The permission says "owner role"; this says "THE owner, in person" —
 * the user in `organizations.owner_user_id`, acting for themselves. Staff, system runs, AI and impersonation are refused:
 * no internal flow sets a panel account password.
 */
final class OwnerOnlyActions
{
    /** The game panel account opens every server of that account, not only this service. */
    public const ACTIONS = ['panel.password'];

    public static function assert(Service $service, string $action, CommandContext $context): void
    {
        if (! in_array($action, self::ACTIONS, true)) {
            return;
        }
        $owner = Organization::query()->whereKey($service->organization_id)->value('owner_user_id');
        $inPerson = $context->actorType === 'user' && $context->onBehalfOfUserId === null && $context->actorId !== null;
        if ($inPerson && $owner !== null && (string) $context->actorId === (string) $owner) {
            return;
        }

        throw new DomainError('owner_only_action', 'Heslo do herního panelu může nastavit jen vlastník organizace. Požádejte ho o to.', 403, ['action' => $action]);
    }
}
