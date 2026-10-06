<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Penpot;

use Carbon\Carbon;
use Onhost\Domain\Catalog\CatalogPreflight;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Scheduling\NodeScheduler;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Errors\DomainError;

/**
 * Doctor rows and the service health finding of Penpot (TASK-0123). The rows never call a node: they read the catalogue, the
 * registered Penpot nodes and what the last sweep (`onhost:penpot:sweep`) recorded in `tags.penpot.health`.
 */
final class PenpotHealth
{
    /** A probe older than this is not a reading any more (the sweep runs every 10 minutes). */
    public const STALE_MINUTES = 60;

    /** @return list<array{area:string, check:string, ok:bool, detail:string, blocking:bool, remedy:string}> */
    public function checks(): array
    {
        $product = Product::query()->where('key', PenpotInstances::PRODUCT)->first();
        $onSale = $product !== null && $product->state === 'active';
        $nodes = $this->usableNodes();
        $rows = [[
            'area' => 'penpot', 'check' => 'Penpot is sold only with a Penpot node to run it', 'ok' => ! $onSale || $nodes > 0, 'blocking' => $onSale,
            'detail' => match (true) {
                $product === null => 'not in the catalogue: the proposal 2026-10-penpot waits for the owner (php artisan onhost:catalog:revise 2026-10-penpot)',
                ! $onSale => "not on sale (draft); usable Penpot nodes: {$nodes}",
                default => "on sale; usable Penpot nodes: {$nodes}",
            },
            'remedy' => $onSale && $nodes === 0 ? 'take Penpot off sale, or register a Penpot node (docs/runbooks/penpot.md: server prerequisites, provider instance `penpot`, node role `penpot`)' : '',
        ]];
        $priced = true;
        $priceDetail = $product === null ? 'no product yet' : 'every price of the plan is set';
        if ($product !== null) {
            try {
                CatalogPreflight::assertPriced($product);
            } catch (DomainError $e) {
                $priced = false;
                $priceDetail = 'zero price on plan(s): '.implode(', ', (array) ($e->extra['plans'] ?? [])).($onSale ? ' — SOLD FOR NOTHING' : ' (fine while it is a draft)');
            }
        }
        $rows[] = ['area' => 'penpot', 'check' => 'Penpot has a price before it is on sale', 'ok' => $priced || ! $onSale, 'blocking' => $onSale, 'detail' => $priceDetail,
            'remedy' => $priced ? '' : 'set the prices in the plan editor (/sprava/nastaveni/tarify), then put the product on sale'];

        $running = Service::query()->where('family', PenpotInstances::FAMILY)->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])->get(['id', 'hostname', 'tags']);
        $down = $running->filter(fn (Service $s) => data_get($s->tags, 'penpot.health.status') === 'down')->map(fn (Service $s) => (string) $s->hostname)->values()->all();
        $unprobed = $running->filter(fn (Service $s) => ! self::fresh($s))->count();
        $rows[] = ['area' => 'penpot', 'check' => 'every Penpot instance answers', 'ok' => $down === [] && $unprobed === 0, 'blocking' => false,
            'detail' => $running->isEmpty() ? 'no Penpot instance runs' : count($running).' running, '.count($down).' not answering'.($down === [] ? '' : ' ('.implode(', ', array_slice($down, 0, 5)).')').", {$unprobed} without a recent probe",
            'remedy' => $down === [] && $unprobed === 0 ? '' : 'php artisan onhost:penpot:sweep; on the node: docker compose -p <stack> ps / logs (docs/runbooks/penpot.md)'];

        return $rows;
    }

    /** @return array{key:string, level:'ok'|'warn'|'bad', cs:string, en:string} */
    public static function finding(Service $service): array
    {
        $health = (array) data_get($service->tags, 'penpot.health', []);
        $url = (string) data_get($service->tags, 'penpot.url', '');
        if (! self::fresh($service)) {
            return ['key' => 'penpot', 'level' => 'warn', 'cs' => 'Dostupnost Penpotu zatím nebyla ověřena.', 'en' => 'Penpot availability has not been checked yet.'];
        }
        if (($health['status'] ?? '') === 'down') {
            return ['key' => 'penpot', 'level' => 'bad', 'cs' => "Penpot {$url} neodpovídá (kontejnery ".($health['containers'] ?? '?').', HTTP '.($health['http'] ?? 0).').', 'en' => "Penpot {$url} is not responding (containers ".($health['containers'] ?? '?').', HTTP '.($health['http'] ?? 0).').'];
        }

        return ['key' => 'penpot', 'level' => 'ok', 'cs' => "Penpot {$url} odpovídá.", 'en' => "Penpot {$url} responds."];
    }

    public static function fresh(Service $service): bool
    {
        $at = data_get($service->tags, 'penpot.health.checked_at');

        return is_string($at) && $at !== '' && Carbon::parse($at)->greaterThan(now()->subMinutes(self::STALE_MINUTES));
    }

    private function usableNodes(): int
    {
        $instances = ProviderInstance::query()->platform()->where('provider', 'penpot')->get()->filter(fn (ProviderInstance $i) => $i->isUsable())->pluck('id')->all();

        return $instances === [] ? 0 : Node::query()->whereIn('provider_instance_id', $instances)->where('state', 'active')->get()->filter(fn (Node $n) => NodeScheduler::serves($n, 'penpot'))->count();
    }
}
