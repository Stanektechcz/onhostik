<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Penpot;

use Carbon\Carbon;
use Onhost\Domain\Catalog\CatalogPreflight;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\PenpotOffer;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Scheduling\NodeScheduler;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Errors\DomainError;
use Onhost\Providers\Penpot\PenpotDockerProvider;

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
        // TASK-0150: a node of a sandbox instance (`options.sandbox`) takes only sandbox tenants (NodeScheduler); it is no node for
        // the customers Penpot is on sale to, and counting it said "on sale; usable Penpot nodes: 1" while every order was refused
        $nodes = $this->usableNodes(false);
        $sandboxNodes = $this->usableNodes(true);
        $sandboxNote = $sandboxNodes > 0 ? "; sandbox-only Penpot nodes (sandbox tenants only): {$sandboxNodes}" : '';
        // H-R7 (TASK-0128): Penpot is on sale before its node exists; the cart and the delivery refuse a Penpot no node can run
        // (penpot_unavailable, nothing charged / the line refunded), so a missing node is a finding to act on, not a deploy blocker
        $rows = [[
            'area' => 'penpot', 'check' => 'Penpot is sold only with a Penpot node to run it', 'ok' => ! $onSale || $nodes > 0, 'blocking' => false,
            'detail' => match (true) {
                $product === null => 'not in the catalogue: php artisan onhost:catalog:revise 2026-10-penpot-on-sale (dry run), then --apply',
                ! $onSale => "not on sale (draft); usable Penpot nodes: {$nodes}{$sandboxNote}",
                $nodes === 0 && $sandboxNodes > 0 => "on sale, but the only Penpot node(s) ({$sandboxNodes}) belong to a sandbox instance: they serve sandbox tenants only, the cart refuses every customer's Penpot order (penpot_unavailable) and nothing is charged",
                $nodes === 0 => 'on sale, but no qualified Penpot node: the cart refuses every Penpot order (penpot_unavailable) and nothing is charged',
                default => "on sale; usable Penpot nodes: {$nodes}{$sandboxNote}",
            },
            'remedy' => $onSale && $nodes === 0 ? 'register and qualify a Penpot node on a production instance (docs/runbooks/penpot.md: server prerequisites, provider instance `penpot` without options.sandbox, node role `penpot`), or take Penpot off sale' : '',
        ]];
        $offer = app(PenpotOffer::class);
        $tariffs = $offer->overview()['tariffs'];
        $included = count(array_filter($tariffs, fn (array $t) => $t['included']));
        $rows[] = ['area' => 'penpot', 'check' => 'Penpot has a rule for every web hosting tariff', 'ok' => $offer->configured() || $product === null, 'blocking' => false,
            'detail' => ($offer->configured() ? '' : 'not written yet, the owner\'s defaults apply; ').count($tariffs).' web hosting tariff(s): '.$included.' include Penpot, '.(count($tariffs) - $included).' price it; any other service: the catalogue price of penpot/penpot-team',
            'remedy' => $offer->configured() || $product === null ? '' : 'php artisan onhost:catalog:revise 2026-10-penpot-on-sale --apply (or save the Penpot editor in /sprava/nastaveni/integrace)'];
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

        // H-R7: a Penpot belongs to a service; one whose service failed or ended (and was not ended with it) is served for nothing
        $orphans = Service::query()->where('family', PenpotInstances::FAMILY)->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED, ServiceStateMachine::SUSPENDED])->whereNull('terminate_at')->get(['id', 'organization_id', 'hostname', 'tags'])
            ->filter(function (Service $penpot): bool {
                $parentId = (string) data_get($penpot->tags, 'parent_service_id', '');
                $parent = $parentId === '' ? null : Service::query()->where('organization_id', $penpot->organization_id)->find($parentId, ['id', 'state', 'terminate_at']);

                return $parentId !== '' && ($parent === null || $parent->terminate_at !== null || in_array($parent->state, [ServiceStateMachine::FAILED, ServiceStateMachine::TERMINATING, ServiceStateMachine::TERMINATED], true));
            })->map(fn (Service $s) => (string) ($s->hostname ?: $s->id))->values()->all();
        $rows[] = ['area' => 'penpot', 'check' => 'no Penpot outlives the service it was ordered for', 'ok' => $orphans === [], 'blocking' => false,
            'detail' => $orphans === [] ? 'every Penpot belongs to a running service' : 'its service failed or ended: '.implode(', ', array_slice($orphans, 0, 10)),
            'remedy' => $orphans === [] ? '' : 'cancel the Penpot (staff console, terminate) or move it to another service of the customer; a failed parent order line was refunded'];

        // TASK-0123 security review of PR #119: the nodes themselves (M4, M2) and the images (L)
        $nodes = ProviderInstance::query()->platform()->where('provider', 'penpot')->where('state', '!=', 'disabled')->orderBy('key')->get();
        $unpinned = $nodes->filter(fn (ProviderInstance $i) => preg_match(PenpotDockerProvider::FINGERPRINT_PATTERN, (string) $i->option('ssh_fingerprint', '')) !== 1)->pluck('key')->all();
        $rows[] = ['area' => 'penpot', 'check' => 'every Penpot node pins its SSH host key', 'ok' => $unpinned === [], 'blocking' => false,
            'detail' => $nodes->isEmpty() ? 'no Penpot node registered' : ($unpinned === [] ? $nodes->count().' node(s), every one with options.ssh_fingerprint' : 'without options.ssh_fingerprint (the adapter sends them nothing): '.implode(', ', $unpinned)),
            'remedy' => $unpinned === [] ? '' : 'ssh-keyscan <host> | ssh-keygen -lf - on a trusted machine, then set options.ssh_fingerprint of the instance (SHA256:…)'];
        $noQuota = $nodes->filter(fn (ProviderInstance $i) => trim((string) $i->option('quota_command', config('penpot.quota_command'))) === '')->pluck('key')->all();
        $rows[] = ['area' => 'penpot', 'check' => 'every Penpot node limits the storage of a stack', 'ok' => $noQuota === [], 'blocking' => false,
            'detail' => $nodes->isEmpty() ? 'no Penpot node registered' : ($noQuota === [] ? 'every node runs its quota helper (XFS project quota) for each stack' : 'no storage quota (the plan\'s storage is only measured): '.implode(', ', $noQuota)),
            'remedy' => $noQuota === [] ? '' : 'docs/runbooks/penpot.md "Storage quota": XFS with prjquota under /var/lib/docker, the helper script, then options.quota_command'];
        $loose = array_keys(array_filter((array) config('penpot.images', []), fn ($image) => preg_match('/@sha256:[a-f0-9]{64}$/', (string) $image) !== 1));
        $rows[] = ['area' => 'penpot', 'check' => 'Penpot images are pinned by digest', 'ok' => $loose === [], 'blocking' => false,
            'detail' => $loose === [] ? implode(', ', array_map(fn ($image) => (string) strtok((string) $image, '@'), (array) config('penpot.images', []))) : 'by tag only: '.implode(', ', $loose),
            'remedy' => $loose === [] ? '' : 'pin every image of config/penpot.php `images` as <repo>:<tag>@sha256:<digest>'];

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

    /** Active, qualified Penpot nodes of usable instances — of sandbox instances only, or of the others (NodeScheduler::pick). */
    private function usableNodes(bool $sandbox): int
    {
        $instances = ProviderInstance::query()->platform()->where('provider', 'penpot')->get()
            ->filter(fn (ProviderInstance $i) => $i->isUsable() && (bool) data_get($i->options, 'sandbox', false) === $sandbox)->pluck('id')->all();

        return $instances === [] ? 0 : Node::query()->whereIn('provider_instance_id', $instances)->where('state', 'active')->get()->filter(fn (Node $n) => NodeScheduler::serves($n, 'penpot'))->count();
    }
}
