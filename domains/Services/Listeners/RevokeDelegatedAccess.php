<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Listeners;

use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\DelegatedAccessReview;
use Onhost\Domain\Services\SshKeyLedger;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxEventDispatched;

/** A person removed from an organization loses the panel accounts (H333) and the SSH keys (H185) that were theirs too. */
final class RevokeDelegatedAccess
{
    public function __construct(private readonly DelegatedAccessReview $review, private readonly SshKeyLedger $keys) {}

    public function __invoke(OutboxEventDispatched $event): void
    {
        $m = $event->message;
        if ($m->name === 'operation.succeeded' && data_get($m->payload, 'kind') === 'service.action') {
            $this->keys->confirmed((string) $m->aggregate_id); // a key removal the panel applied from its queue is closed now, not at the next scheduled pass

            return;
        }
        if ($m->name !== 'organization.member.removed') {
            return;
        }
        $organization = Organization::query()->find((string) $m->aggregate_id);
        if ($organization === null) {
            return;
        }
        $context = CommandContext::system('member removed');
        $userId = (string) data_get($m->payload, 'user_id', '');
        if ($userId !== '') {
            $this->keys->revokeForUser($organization, $userId, $context); // their SSH keys on the organization's sites (H185)
        }
        $email = (string) data_get($m->payload, 'email', '');
        if ($email !== '') {
            $this->review->revokeForMember($organization, $email, $context);
        }
    }
}
