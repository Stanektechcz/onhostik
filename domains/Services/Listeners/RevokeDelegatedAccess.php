<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Listeners;

use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\DelegatedAccessReview;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxEventDispatched;

/** A person removed from an organization loses the panel accounts that were theirs too (Brain card H333). */
final class RevokeDelegatedAccess
{
    public function __construct(private readonly DelegatedAccessReview $review) {}

    public function __invoke(OutboxEventDispatched $event): void
    {
        $m = $event->message;
        if ($m->name !== 'organization.member.removed') {
            return;
        }
        $organization = Organization::query()->find((string) $m->aggregate_id);
        $email = (string) data_get($m->payload, 'email', '');
        if ($organization === null || $email === '') {
            return;
        }
        $this->review->revokeForMember($organization, $email, CommandContext::system('member removed'));
    }
}
