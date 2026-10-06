<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Penpot;

use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Outbox\OutboxEventDispatched;

/**
 * A purged Penpot leaves no key behind (TASK-0123): once the service is TERMINATED (the purge removed the stack and its
 * volumes, the final archive holds the data), its vault entry `db://penpot/<service id>` goes too.
 */
final class ForgetPenpotSecrets
{
    public function __construct(private readonly PenpotSecrets $secrets) {}

    public function __invoke(OutboxEventDispatched $event): void
    {
        $m = $event->message;
        if ($m->name !== 'service.terminated' || $m->aggregate_type !== 'service') {
            return;
        }
        $service = Service::withTrashed()->find((string) $m->aggregate_id); // a purged service may already be soft-deleted
        if ($service !== null && PenpotInstances::isPenpot($service)) {
            $this->secrets->forget($service);
        }
    }
}
