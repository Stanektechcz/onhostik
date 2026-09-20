<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Listeners;

use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Access\ServiceAccessService;
use Onhost\Platform\Outbox\OutboxMessage;

/**
 * `onhost.organization.member.removed`: leaving the organization deletes every binding the person had in it, the ones on
 * single shared services included — the records of those shares are closed here so the owner's list tells the truth.
 */
final class CloseServiceAccessGrants
{
    public function __construct(private readonly ServiceAccessService $access) {}

    public function handle(OutboxMessage $message): void
    {
        $organization = Organization::query()->find($message->aggregate_id);
        $userId = (string) data_get($message->payload, 'user_id', '');
        if ($organization === null || $userId === '') {
            return;
        }
        $this->access->closeForMember($organization, $userId, (string) data_get($message->payload, 'email', ''));
    }
}
