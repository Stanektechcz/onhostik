<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Penpot;

use Illuminate\Support\Collection;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\PenpotOffer;
use Onhost\Domain\Services\Models\Service;

/**
 * What the panel's service list says about Penpot (TASK-0131, I1): a web hosting row whose tariff includes Penpot reads
 * "Penpot v ceně" (only while Penpot is on sale — a label must not promise what cannot be ordered), and a Penpot row names the
 * service it belongs to. Computed for the whole list at once from the rows the member already sees: one query for the tariffs,
 * none per row, and no service of another organization is ever read.
 */
final class PenpotBadges
{
    /**
     * @param  Collection<int, Service>  $services  the organization's services the panel lists
     * @return array<string, array{note: string, penpot: array<string, mixed>}> service id → text appended to the row's meta and the structured field
     */
    public static function for(Collection $services, bool $cs): array
    {
        $web = $services->filter(fn (Service $s) => in_array((string) $s->family, PenpotOffer::WEB_FAMILIES, true));
        $instances = $services->filter(fn (Service $s) => $s->family === PenpotInstances::FAMILY && in_array($s->state, PenpotParents::HELD, true));
        $onSale = $web->isNotEmpty() && (Product::query()->where('key', PenpotOffer::PRODUCT)->first()?->isSellable() ?? false);
        if ($instances->isEmpty() && ! $onSale) {
            return [];
        }
        $included = self::included($web);
        $byParent = $instances->keyBy(fn (Service $s) => (string) data_get($s->tags, 'parent_service_id', ''));
        $out = [];
        foreach ($web as $s) {
            $instance = $byParent->get($s->id);
            $free = $included[$s->id] ?? false;
            if ($instance === null && ! ($onSale && $free)) {
                continue; // nothing to say: no Penpot, and none included that could be ordered now
            }
            $label = $free ? ($cs ? 'Penpot v ceně' : 'Penpot included') : 'Penpot';
            $out[$s->id] = ['note' => ' · '.$label.($instance !== null ? ($cs ? ' (aktivní)' : ' (active)') : ''),
                'penpot' => ['included' => $free, 'instance_id' => $instance?->id]];
        }
        foreach ($instances as $p) {
            $parent = $services->firstWhere('id', (string) data_get($p->tags, 'parent_service_id', ''));
            if ($parent === null) {
                continue; // the parent is not on this member's list (a project role): say nothing rather than guess
            }
            $name = (string) ($parent->label ?: ($parent->hostname ?: $parent->name));
            $free = $included[$parent->id] ?? false;
            $out[$p->id] = ['note' => ' · '.($cs ? 'ke službě ' : 'for ').$name.($free ? ($cs ? ' · v ceně tarifu' : ' · included in the plan') : ''),
                'penpot' => ['included' => $free, 'parent_service_id' => $parent->id]];
        }

        return $out;
    }

    /**
     * Whether each web hosting's tariff includes Penpot under the rules staff set (PenpotOffer: the tariff's own rule, else
     * `web_default`).
     *
     * @param  Collection<int, Service>  $web
     * @return array<string, bool>
     */
    private static function included(Collection $web): array
    {
        if ($web->isEmpty()) {
            return [];
        }
        $rules = app(PenpotOffer::class)->rules();
        $planOf = PlanVersion::query()->whereIn('id', $web->pluck('plan_version_id')->filter()->unique()->values()->all())->pluck('plan_id', 'id')->all();
        $planKeys = Plan::query()->whereIn('id', array_values(array_unique($planOf)))->pluck('key', 'id')->all();
        $out = [];
        foreach ($web as $s) {
            $target = $s->product_key.'/'.($planKeys[$planOf[$s->plan_version_id] ?? ''] ?? '');
            $out[$s->id] = (bool) (($rules['plans'][$target] ?? $rules['web_default'])['included'] ?? false);
        }

        return $out;
    }
}
