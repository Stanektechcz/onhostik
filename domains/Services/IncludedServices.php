<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Illuminate\Support\Collection;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;

/**
 * The services the platform put under another one and bills with it: the test copy of a web site today, and the
 * further sites a web hosting plan sells. They are ordinary services — with their own resource at the panel, their
 * own files, databases and backups — but no subscription and no invoice of their own, because the customer already
 * pays for them in the plan of the service they belong to (`tags.billing = included`, `tags.parent_service_id`).
 *
 * Until this class nothing ever ended one together with the service it belongs to: a cancelled web hosting left its
 * test copy serving on the node for ever, with the customer's files and databases on it and no archive of them —
 * while the cancellation dialog promised the customer the opposite (`DestructivePreview`).
 *
 * The organisation is part of every query on purpose. `tags` are data on a row, and a row that merely names another
 * service as its parent must never make the platform touch a resource of somebody else.
 */
final class IncludedServices
{
    /** States in which a service still has something to end. */
    public const LIVE = [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED, ServiceStateMachine::SUSPENDED, ServiceStateMachine::PAID, ServiceStateMachine::PROVISIONING, ServiceStateMachine::FAILED];

    /** @return Collection<int, Service> */
    public static function of(Service $service): Collection
    {
        if ($service->id === '' || ! in_array($service->family, ['web', 'managed'], true)) {
            return new Collection;
        }

        return Service::query()
            ->where('organization_id', $service->organization_id)
            ->where('tags->parent_service_id', $service->id)
            ->where('tags->billing', 'included')
            ->whereKeyNot($service->id)
            ->whereIn('state', self::LIVE)
            ->orderBy('created_at')
            ->get();
    }

    /** What the customer is told goes with the service — its domains, in the order they were added. @return list<string> */
    public static function domains(Service $service): array
    {
        return self::of($service)->map(fn (Service $child) => (string) ($child->hostname ?: $child->spec('domain', $child->name)))->values()->all();
    }

    /** Whether this service is one the customer pays for inside another service's plan. */
    public static function isIncluded(Service $service): bool
    {
        return (string) data_get($service->tags, 'billing') === 'included' && (string) data_get($service->tags, 'parent_service_id', '') !== '';
    }
}
