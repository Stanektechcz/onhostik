<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Onhost\Domain\Incidents\Models\Maintenance;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;

/**
 * W2-6: who a planned maintenance concerns. A window names service ids, nodes and/or service families; the services it touches
 * are those that run on a named node, belong to a named family or are named — and nobody else. Add-ons (they copy their parent's
 * node but run nothing there) and the throw-away copies of a verified restore are not services of the customer's, so they are
 * not counted; a service that is gone or suspended is not told either. The organizations that own those services are the audience.
 */
final class MaintenanceAudience
{
    /** @var list<string> */
    private const LIVE = [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED];

    /**
     * @param  ?string  $organizationId  only the services of this organization (what a customer may see of a window)
     * @return Collection<int, Service>
     */
    public static function services(Maintenance $maintenance, ?string $organizationId = null): Collection
    {
        return $maintenance->isTargeted() ? self::touched($maintenance)->when($organizationId !== null, fn ($q) => $q->where('organization_id', $organizationId))->orderBy('created_at')->get() : new Collection;
    }

    /** @return list<string> */
    public static function organizationIds(Maintenance $maintenance): array
    {
        return self::services($maintenance)->pluck('organization_id')->unique()->values()->all();
    }

    /** Whether this service is one the window touches. */
    public static function affects(Maintenance $maintenance, Service $service): bool
    {
        return $maintenance->isTargeted() && self::touched($maintenance)->whereKey($service->id)->exists();
    }

    /** @return list<Maintenance> approved or running windows whose time is now and that touch the service (the health check says so) */
    public static function activeFor(Service $service): array
    {
        return Maintenance::query()->whereIn('state', ['approved', 'in_progress'])->where('starts_at', '<=', now())->where('ends_at', '>=', now())->orderBy('starts_at')->get()
            ->filter(fn (Maintenance $m) => self::affects($m, $service))->values()->all();
    }

    /** @return Builder<Service> the live services of the customers that the window names, by id, node or family */
    private static function touched(Maintenance $maintenance)
    {
        $ids = array_values(array_filter(array_map('strval', (array) $maintenance->affected_services)));
        $nodes = array_values(array_filter(array_map('strval', (array) $maintenance->affected_nodes)));
        $families = array_values(array_filter(array_map('strval', (array) $maintenance->affected_families)));

        return Service::query()->where('family', '!=', 'addon')->whereIn('state', self::LIVE)->whereNull('tags->restore_target_of')
            ->where(function ($q) use ($ids, $nodes, $families): void {
                $q->whereIn('id', $ids)->orWhereIn('node_id', $nodes)->orWhereIn('family', $families);
            });
    }
}
