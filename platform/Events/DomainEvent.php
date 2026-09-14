<?php

declare(strict_types=1);

namespace Onhost\Platform\Events;

use Onhost\Platform\Commands\CommandContext;

/**
 * Base class for events written to the outbox. Names are dotted and stable
 * (`order.committed`, `service.activated`, `payment.succeeded`, `domain.registered`)
 * and listed in contracts/events/catalog.md.
 */
abstract class DomainEvent
{
    /** @param array<string,mixed> $payload */
    public function __construct(
        protected readonly string $eventName,
        protected readonly string $aggregate,
        protected readonly string $aggregateKey,
        protected readonly array $payload = [],
        protected readonly ?string $organization = null,
        protected readonly ?string $correlation = null,
    ) {}

    public function name(): string
    {
        return $this->eventName;
    }

    public function aggregateType(): string
    {
        return $this->aggregate;
    }

    public function aggregateId(): string
    {
        return $this->aggregateKey;
    }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return $this->payload;
    }

    public function organizationId(): ?string
    {
        return $this->organization;
    }

    public function correlationId(): string
    {
        return $this->correlation ?? CommandContext::currentCorrelationId();
    }
}
