<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Penpot;

use Illuminate\Support\Collection;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Provisioning\Scheduling\NodeScheduler;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Errors\DomainError;

/**
 * Penpot is ordered for a service (owner decision H-R7): included in a web hosting tariff, an add-on next to anything else.
 * One Penpot per service — an included one could otherwise be ordered ten times for nothing — and only while a qualified
 * Penpot node can run it: nothing is sold that cannot be delivered (the cart refuses, a paid line fails and is refunded).
 *
 * The Penpot is a service of its own (its stack on the Penpot node) that names its parent in `tags.parent_service_id`; it
 * ends with the parent (ServiceActionWorkflow `endPenpotStep`). The organisation is part of every query: a row that names
 * somebody else's service as its parent never makes the platform act on it.
 */
final class PenpotParents
{
    /** States a service may get a Penpot in. */
    public const PARENT_STATES = [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED];

    /** States a Penpot still counts as the service's Penpot in (anything but gone). */
    public const HELD = [ServiceStateMachine::PAID, ServiceStateMachine::PROVISIONING, ServiceStateMachine::VERIFYING, ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED, ServiceStateMachine::RESIZING,
        ServiceStateMachine::SUSPENDING, ServiceStateMachine::SUSPENDED, ServiceStateMachine::RESUMING];

    /** What the Penpot plan needs of a node (the plan's own entitlements are the source; these are the floors). */
    private const NEEDS = ['ram_mb' => 4096, 'cpus' => 2, 'storage_gb' => 20];

    /** Families that never carry a Penpot: an add-on row and a Penpot itself. */
    private const NO_PARENT = ['addon', PenpotInstances::FAMILY];

    /** The organisation's own service a Penpot is ordered for, or the refusal. */
    public static function parent(string $organizationId, string $serviceId): Service
    {
        $service = Service::query()->where('organization_id', $organizationId)->find($serviceId);
        if ($service === null) {
            throw DomainError::notFound('service');
        }
        self::assertParent($service);

        return $service;
    }

    public static function assertParent(Service $service): void
    {
        if (in_array((string) $service->family, self::NO_PARENT, true)) {
            throw new DomainError('penpot_parent_invalid', 'Penpot se objednává ke službě (webhosting, server …), ne k doplňku ani k jinému Penpotu.', 422, ['field' => 'parent_service_id']);
        }
        if (! in_array($service->state, self::PARENT_STATES, true) || $service->terminate_at !== null) {
            throw new DomainError('penpot_parent_inactive', 'Penpot lze přidat jen ke službě, která běží a není zrušená.', 409, ['field' => 'parent_service_id', 'state' => $service->state]);
        }
    }

    /** The Penpot instances a service carries (anything not gone). @return Collection<int, Service> */
    public static function of(Service $parent): Collection
    {
        return Service::query()
            ->where('organization_id', $parent->organization_id)
            ->where('family', PenpotInstances::FAMILY)
            ->where('tags->parent_service_id', $parent->id)
            ->whereKeyNot($parent->id)
            ->whereIn('state', self::HELD)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Whether the service already has its Penpot: running, or ordered on an order not finished yet (two orders placed one
     * after the other must not give one hosting two included instances).
     */
    public static function taken(Service $parent, ?string $exceptItemId = null): bool
    {
        if (self::of($parent)->isNotEmpty()) {
            return true;
        }

        return OrderItem::query()->where('product_key', PenpotInstances::PRODUCT)->whereIn('state', ['pending', 'provisioning'])->whereNull('service_id')
            ->when($exceptItemId !== null, fn ($q) => $q->whereKeyNot($exceptItemId))
            ->whereHas('order', fn ($q) => $q->where('organization_id', $parent->organization_id)
                ->whereIn('state', [OrderStateMachine::NEW, OrderStateMachine::PENDING_PAYMENT, OrderStateMachine::PAID, OrderStateMachine::PROVISIONING]))
            ->get(['id', 'config'])
            ->contains(fn (OrderItem $item) => (string) data_get($item->config, 'parent_service_id', '') === $parent->id);
    }

    /**
     * Whether a qualified Penpot node can take one more instance now (role `penpot` on a usable `penpot` instance with room
     * for the plan). No node registered is "no", not "unknown": a Penpot cannot be built anywhere else.
     *
     * @param  array<string,mixed>  $entitlements  the Penpot plan's entitlements
     */
    public static function deliverable(?string $organizationId, array $entitlements = []): bool
    {
        $fits = app(NodeScheduler::class)->canHost([
            'role' => 'penpot', 'provider' => 'penpot', 'region' => (string) config('onhost.provisioning.default_region', 'cz1'), 'sandbox' => NodeScheduler::sandboxFor($organizationId),
            'ram_mb' => (int) ($entitlements['ram_mb'] ?? self::NEEDS['ram_mb']), 'cpu_cores' => (int) ceil((float) ($entitlements['cpus'] ?? self::NEEDS['cpus'])),
            'disk_gb' => (int) ($entitlements['storage_gb'] ?? self::NEEDS['storage_gb']),
        ]);

        return $fits === true;
    }

    /** The refusal a customer reads when no node can run Penpot: nothing was ordered or charged. */
    public static function unavailable(): DomainError
    {
        return new DomainError('penpot_unavailable', 'Penpot teď nemůžeme dodat: server pro Penpot není připravený nebo je plný. Nic nebylo objednáno ani účtováno; zkuste to později.', 409, ['field' => 'items', 'product' => PenpotInstances::PRODUCT]);
    }
}
