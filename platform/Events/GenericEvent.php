<?php

declare(strict_types=1);

namespace Onhost\Platform\Events;

/** Ad-hoc domain event: GenericEvent::of('service.activated', 'service', $id, [...], $orgId). */
final class GenericEvent extends DomainEvent
{
    /** @param array<string,mixed> $payload */
    public static function of(string $name, string $aggregate, string $aggregateId, array $payload = [], ?string $organizationId = null): self
    {
        return new self($name, $aggregate, $aggregateId, $payload, $organizationId);
    }
}
