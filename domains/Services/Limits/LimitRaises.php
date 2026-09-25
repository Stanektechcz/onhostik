<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Limits;

use Illuminate\Support\Collection;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Billing\SubscriptionService;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\PlanFit;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * A limit raise after it was paid for (owner decision 8, TASK-0022 limit-raise).
 *
 * A raise is an add-on (`Addons`) whose whole job is `+delta` on one number of its parent. On top of what every add-on does:
 *  • it is billed every period, like the service (its own subscription; a raise given at no charge ends with its first period),
 *  • the panel hears the new number at once, through the parent's ordinary `resize` with ALL its numbers (a resize with one
 *    number would reset the others); when the panel cannot be asked, the raise says so (`tags.addon.panel`) and the operator
 *    pushes it again (`onhost:limit-raise:push`),
 *  • a plan change keeps the raises on top of the new plan, and ending one gives back exactly its own delta,
 *  • a customer cannot end a raise the service already uses (the system can: an unpaid raise ends).
 */
final class LimitRaises
{
    public const PRODUCT = 'limit-raise';

    public function __construct(private readonly OutboxPublisher $outbox) {}

    public static function isRaise(Service $service): bool
    {
        return $service->family === 'addon' && $service->product_key === self::PRODUCT;
    }

    /** Called by `ServiceService::attachAddon` once the delta is on the parent. */
    public function afterAttach(Service $parent, Service $addon, OrderItem $item, CommandContext $context): void
    {
        $subscription = app(SubscriptionService::class)->ensureForService($addon, $item, $context);
        $waived = data_get($item->config, 'limit_raise.waived');
        if (is_array($waived)) { // given at no charge: one period, never renewed (and never billed: its price is 0)
            $subscription->forceFill(['cancel_at_period_end' => true, 'auto_renew' => false, 'next_renewal_at' => $subscription->current_period_end])->save();
        }
        $operation = $this->pushPanel($parent, $addon, "limit-raise:{$item->id}");
        [$metric, $delta] = self::deltaOf($addon);
        $this->outbox->publish(GenericEvent::of('service.limit_raised', 'service', $parent->id, [
            'addon_service_id' => $addon->id, 'metric' => $metric, 'metric_label' => self::labelOf($addon, $metric), 'delta' => $delta, 'new_value' => (int) (((array) $parent->fresh()?->entitlements)[$metric] ?? 0),
            'label' => (string) ($parent->label ?: ($parent->hostname ?: $parent->name)), 'waived' => is_array($waived), 'approval_ids' => is_array($waived) ? (array) ($waived['approval_ids'] ?? []) : [],
            'subscription_id' => $subscription->id, 'period' => $subscription->period, 'operation_id' => $operation?->id, 'panel' => (string) data_get($addon->fresh()?->tags, 'addon.panel.state', ''),
        ], $parent->organization_id));
    }

    /** Called when the add-on was taken off its parent (`ServiceActionWorkflow::detachAddonStep`). */
    public function afterRevoke(Service $parent, Service $addon, string $reason): void
    {
        $operation = $this->pushPanel($parent, $addon, "limit-raise-end:{$addon->id}");
        [$metric, $delta] = self::deltaOf($addon);
        $this->outbox->publish(GenericEvent::of('service.limit_raise_ended', 'service', $parent->id, [
            'addon_service_id' => $addon->id, 'metric' => $metric, 'metric_label' => self::labelOf($addon, $metric), 'delta' => $delta, 'new_value' => (int) (((array) $parent->fresh()?->entitlements)[$metric] ?? 0),
            'label' => (string) ($parent->label ?: ($parent->hostname ?: $parent->name)), 'reason' => mb_substr($reason, 0, 120), 'operation_id' => $operation?->id,
        ], $parent->organization_id));
    }

    /**
     * Asks the panel for the parent's numbers as they are now. The platform does this as itself: the customer paid for it (or
     * ended it), and a resize after payment is not the customer's own action. A refusal is recorded on the raise, not thrown:
     * the money and the entitlement are right, only the panel waits for `onhost:limit-raise:push`.
     */
    public function pushPanel(Service $parent, Service $addon, string $idempotencyKey): ?Operation
    {
        $parent = $parent->fresh() ?? $parent;
        if (! in_array($parent->state, [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED], true)) {
            $this->markPanel($addon, 'pending', "the service is {$parent->state}; the panel gets the number with the next push");

            return null;
        }
        $version = PlanVersion::query()->find($parent->plan_version_id);
        $limits = $version === null ? [] : (array) ($version->limits ?? []);
        try {
            $operation = app(ServiceService::class)->requestAction($parent, 'resize', CommandContext::system('limit raise')->withScope($parent->organization_id), $idempotencyKey,
                ['entitlements' => (array) $parent->entitlements, 'limits' => $limits, 'reason' => 'limit raise'], chained: true);
        } catch (DomainError $e) {
            $this->markPanel($addon, 'pending', $e->error.': '.mb_substr($e->getMessage(), 0, 200));

            return null;
        }
        $this->markPanel($addon, 'requested', null, $operation->id);

        return $operation;
    }

    /**
     * What the active raises of a service add, per number.
     *
     * @return array<string,int>
     */
    public static function activeDeltas(Service $parent): array
    {
        $out = [];
        foreach (self::activeOf($parent) as $addon) {
            foreach ((array) data_get($addon->tags, 'addon.delta', []) as $key => $delta) {
                $out[(string) $key] = ($out[(string) $key] ?? 0) + (int) $delta;
            }
        }

        return $out;
    }

    /**
     * A new plan's numbers with the raises the service keeps on top (a plan change used to overwrite them).
     *
     * @param  array<string,mixed>  $entitlements
     * @return array<string,mixed>
     */
    public static function withActiveDeltas(Service $parent, array $entitlements): array
    {
        foreach (self::activeDeltas($parent) as $key => $delta) {
            $entitlements[$key] = (int) ($entitlements[$key] ?? 0) + $delta;
        }

        return $entitlements;
    }

    /** A customer cannot end a raise the service already uses: without it the service would not fit its own numbers. */
    public static function assertCanEnd(Service $addon): void
    {
        $parent = Service::query()->find((string) data_get($addon->tags, 'parent_service_id', ''));
        if ($parent === null || data_get($addon->tags, 'addon.revoked_at') !== null) {
            return;
        }
        $lowered = (array) $parent->entitlements;
        foreach ((array) data_get($addon->tags, 'addon.delta', []) as $key => $delta) {
            if (is_numeric($lowered[$key] ?? null)) {
                $lowered[$key] = max(0, (int) $lowered[$key] - (int) $delta);
            }
        }
        app(PlanFit::class)->assertFits($parent, $lowered);
    }

    /**
     * Raises that are neither billed nor approved, or whose number never reached the panel (the doctor's row).
     *
     * @return list<string>
     */
    public static function problems(): array
    {
        $out = [];
        $raises = Service::query()->where('family', 'addon')->where('product_key', self::PRODUCT)->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])->get();
        foreach ($raises as $addon) {
            if (data_get($addon->tags, 'addon.revoked_at') !== null) {
                continue;
            }
            $item = $addon->order_item_id !== null ? OrderItem::query()->find($addon->order_item_id) : null;
            $approved = (array) data_get($item?->config, 'limit_raise.waived.approval_ids', []) !== [];
            $billed = Subscription::query()->where('service_id', $addon->id)->whereIn('state', [Subscription::ACTIVE, Subscription::PAST_DUE])->exists();
            if (! $billed && ! $approved) {
                $out[] = "{$addon->id}: no subscription and no approval";
            } elseif (data_get($addon->tags, 'addon.panel.state') === 'pending') {
                $out[] = "{$addon->id}: not on the panel yet (".(string) data_get($addon->tags, 'addon.panel.error', '').') — onhost:limit-raise:push';
            }
        }

        return $out;
    }

    /** @return Collection<int, Service> */
    public static function activeOf(Service $parent): Collection
    {
        return Service::query()->where('family', 'addon')->where('product_key', self::PRODUCT)->where('tags->parent_service_id', $parent->id)
            ->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])->get()
            ->filter(fn (Service $addon) => data_get($addon->tags, 'addon.revoked_at') === null)->values();
    }

    /** What the customer bought, in the words of the price list (the option label the raise was priced by). */
    private static function labelOf(Service $addon, string $metric): string
    {
        $item = $addon->order_item_id !== null ? OrderItem::query()->find($addon->order_item_id) : null;

        return (string) (data_get($item?->config, 'limit_raise.label') ?: $metric);
    }

    /** @return array{0: string, 1: int} */
    private static function deltaOf(Service $addon): array
    {
        $delta = (array) data_get($addon->tags, 'addon.delta', []);
        $metric = (string) (array_key_first($delta) ?? '');

        return [$metric, (int) ($delta[$metric] ?? 0)];
    }

    private function markPanel(Service $addon, string $state, ?string $error, ?string $operationId = null): void
    {
        $addon = $addon->fresh() ?? $addon;
        $tags = (array) $addon->tags;
        $tags['addon'] = array_replace((array) ($tags['addon'] ?? []), ['panel' => array_filter(['state' => $state, 'error' => $error, 'operation_id' => $operationId, 'at' => now()->toIso8601String()], fn ($v) => $v !== null)]);
        $addon->forceFill(['tags' => $tags])->save();
    }
}
