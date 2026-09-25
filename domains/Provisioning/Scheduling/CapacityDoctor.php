<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Scheduling;

use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Provisioning\Models\ProviderInstance;

/**
 * The doctor's rows for decisions 7 and 19 (TASK-0023), kept out of `Doctor` so that file only gains one loop. Every
 * row is a standing WARN, never a FAIL: none of them is fixed by a deploy, each needs an operator's or the owner's
 * decision (flip the disk basis after the report, remove a placement, decide what to do with a service already
 * running on a node-wide pool — the platform never moves it).
 */
final class CapacityDoctor
{
    public function __construct(private readonly CapacityReport $report) {}

    /** @return list<array{check:string, ok:bool, detail:string}> */
    public function rows(): array
    {
        $running = $this->report->dedicatedOnNodeWidePool();
        $undelivered = $this->report->undeliveredPlans();
        $ignored = $this->report->ignoredPlacements();
        $basis = CapacityBasis::defaults();
        $overrides = ProviderInstance::query()->platform()->get()->filter(fn (ProviderInstance $i) => $i->option('capacity_basis') !== null)
            ->map(fn (ProviderInstance $i) => $i->key.(CapacityBasis::isLegacy($i) ? ' (legacy '.(string) $i->option('capacity_basis').')' : ' (per dimension)'))->values()->all();
        $asDecided = $basis['disk'] === CapacityBasis::SOLD && $basis['ram'] === CapacityBasis::MEASURED && $overrides === [];
        $homeless = $this->dedicatedWithoutPanel();

        return [
            ['check' => 'dedicated PHP workers are sold only where a site has its own pool', 'ok' => $undelivered === [],
                'detail' => $undelivered === [] ? 'every plan that sells them runs on a panel with a pool per site' : implode(', ', $undelivered).' sell(s) them on a panel with one pool per node — owner decision pending (TASK-0023)'],
            ['check' => 'no service with dedicated PHP workers runs on a node-wide pool', 'ok' => $running === [],
                'detail' => $running === [] ? 'none' : count($running).' service(s), not moved — php artisan onhost:capacity:basis lists them'],
            ['check' => 'no placement sends a dedicated-PHP plan to a node-wide pool', 'ok' => $ignored === [],
                'detail' => $ignored === [] ? 'none' : 'ignored for these plans: '.implode(', ', array_slice($ignored, 0, 5)).' — remove or narrow the placement (Nastavení → Umístění tarifů)'],
            ['check' => 'plans with dedicated PHP workers have a panel to run on', 'ok' => $homeless === [],
                'detail' => $homeless === [] ? 'a usable panel with a pool per site exists' : 'no usable panel with a pool per site for: '.implode(', ', $homeless)],
            ['check' => 'capacity basis as decided (disk sold, RAM and CPU measured)', 'ok' => $asDecided,
                'detail' => $asDecided ? 'as decided' : 'disk '.$basis['disk'].', RAM '.$basis['ram'].($overrides === [] ? '' : '; panel options: '.implode(', ', $overrides)).' — read php artisan onhost:capacity:basis, then ONHOST_CAPACITY_DISK_BASIS=sold'],
        ];
    }

    /** @return list<string> product/plan on sale with dedicated PHP workers bound to panels none of which is usable */
    private function dedicatedWithoutPanel(): array
    {
        $usable = ProviderInstance::query()->platform()->whereIn('provider', PlacementRules::DEDICATED_PHP_PROVIDERS)->get()->contains(fn (ProviderInstance $i) => $i->isUsable());
        if ($usable) {
            return [];
        }
        $out = [];
        $products = Product::query()->where('state', 'active')->whereNotNull('executor')->get(['id', 'key', 'executor'])->keyBy('id');
        foreach (Plan::query()->whereIn('product_id', $products->keys())->where('state', 'active')->get() as $plan) {
            $product = $products->get($plan->product_id);
            if ($product !== null && PlacementRules::requires((string) $product->executor, (array) $plan->currentVersion()?->entitlements) !== []) {
                $out[] = $product->key.'/'.$plan->key;
            }
        }

        return $out;
    }
}
