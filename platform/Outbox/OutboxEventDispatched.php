<?php

declare(strict_types=1);

namespace Onhost\Platform\Outbox;

/** Generic Laravel event wrapping any relayed outbox message (webhooks, notifications, projections). */
final class OutboxEventDispatched
{
    public function __construct(public readonly OutboxMessage $message) {}
}
