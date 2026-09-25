<?php

declare(strict_types=1);

namespace Onhost\Domain\Catalog;

use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\Commands\CatalogCommand;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Price;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/**
 * Catalogue revisions defined in code (TASK-0022, owner decisions 2/4/5/6/11 and 18, 2026-09-25).
 *
 * The catalogue on a running installation changes only through plan versions: `CatalogSeeder` writes version 1 on a fresh
 * install and is not part of a deployment, and a version customers hold is never edited (brain card H01). A change the code
 * base decides — a promise the platform turned out not to keep — is therefore written down here and published by an operator
 * with `php artisan onhost:catalog:revise` (a dry run by default, `--apply` publishes) through the same `CatalogCommand
 * plan.publish` path the staff console uses: audited, one `catalog.plan.version_published` event per plan for finance, the
 * prices of the current version carried over unchanged, and everybody on an older version keeps it.
 *
 * A revision is stateless: what is pending is read from the current versions (a key still there, a value still the old one), so
 * a change staff already made by hand counts as done, a second run finds nothing, and a rollback to an old version makes the
 * revision pending again. A revision never passes `prices` or `features`: it cannot change what anything costs.
 *
 * Shape of a revision:
 *  - `reason`: kept with every version it publishes (finance and the audit read it);
 *  - `plans`: 'product/plan' => keys the new version drops (from `entitlements` or `limits`, wherever the current version has them);
 *  - `rewrite`: key => [old value => new value], applied to every plan whose current version still carries the old value;
 *  - `products`: product key => ['match' => regex on the current description, 'description' => {cs, en}] — replaced only while
 *    the current description still says what the revision withdraws, so a text staff wrote since is left alone.
 */
final class CatalogRevisions
{
    public const REVISIONS = [
        '2026-09-honest-promises' => [
            'reason' => 'Rozhodnutí vlastníka 2/4/6/18 (2026-09-25): PITR, počet spojení, dedikovaná odchozí IP a dedikovaná databáze se neposkytují a interval záloh „1h“ se opravuje na „hourly“ — nové verze bez nich, ceny beze změny, stávající smlouvy beze změny.',
            'plans' => [
                'database/db-s' => ['pitr_days', 'connections'],
                'database/db-m' => ['pitr_days', 'connections'],
                'mail/mail-enterprise' => ['dedicated_outbound_ip'],
                'wordpress/managed-woo' => ['dedicated_db'],
                'eshop/shop-peak' => ['dedicated_db'],
            ],
            'rewrite' => [
                'backup_frequency' => ['1h' => 'hourly'], // the scheduler knows 15m/hourly/6h/daily/weekly; "1h" fell back to daily
            ],
            'products' => [
                'database' => [
                    'match' => '/\bPITR\b/i',
                    'description' => ['cs' => 'PostgreSQL, MariaDB a Redis jako služba — single-tenant KVM a zálohy.', 'en' => 'PostgreSQL, MariaDB and Redis as a service — single-tenant KVM and backups.'],
                ],
            ],
        ],
    ];

    /** Features lines staff may have written that repeat a withdrawn promise: the preview warns, nothing rewrites them. */
    private const WITHDRAWN_WORDING = '/PITR|spojení|connection|dedikovan|dedicated/iu';

    /** @return list<string> */
    public static function ids(): array
    {
        return array_keys(self::REVISIONS);
    }

    /**
     * What is still to do, per revision (only revisions with something pending).
     *
     * @return array<string, array{plans: array<string, array{version: int, drop: array<string,string>, set: array<string, array{bag: string, from: mixed, to: mixed}>}>, products: array<string, array{cs: string, en: string}>}>
     */
    public function pending(?string $id = null): array
    {
        $out = [];
        foreach ($id === null ? self::ids() : [$this->known($id)] as $revision) {
            $plans = $this->pendingPlans($revision);
            $products = $this->pendingProducts($revision);
            if ($plans !== [] || $products !== []) {
                $out[$revision] = ['plans' => $plans, 'products' => $products];
            }
        }

        return $out;
    }

    /**
     * Every key a pending revision still has to take off a plan on sale: the doctor does not count them as a new gap, it names
     * the command instead (they already left `PlanPromises::KNOWN_GAPS` in the commit that defined the revision).
     *
     * @return list<string>
     */
    public function pendingKeys(): array
    {
        $keys = [];
        foreach ($this->pending() as $revision) {
            foreach ($revision['plans'] as $plan) {
                foreach (array_keys($plan['drop']) as $key) {
                    $keys[$key] = true;
                }
            }
        }

        return array_keys($keys);
    }

    /**
     * The dry run: one row per plan and product the revision would change, with who keeps the current version and what would
     * not carry over.
     *
     * @return list<array<string,mixed>>
     */
    public function preview(string $id): array
    {
        $pending = $this->pending($id)[$id] ?? ['plans' => [], 'products' => []];
        $rows = [];
        foreach ($pending['plans'] as $target => $change) {
            $version = $this->currentVersion($target);
            $rows[] = [
                'kind' => 'plan', 'target' => $target, 'from' => $change['version'], 'to' => $this->nextNumber($version),
                'drop' => $change['drop'], 'set' => $change['set'],
                'services' => Service::query()->where('plan_version_id', $version->id)->whereNotIn('state', [ServiceStateMachine::TERMINATED, ServiceStateMachine::FAILED])->count(),
                'subscriptions' => Subscription::query()->where('plan_version_id', $version->id)->whereIn('state', ['active', 'past_due'])->count(),
                // PlanVersioning does not carry a promo price into a new version: an introductory price ends for new orders
                'promos' => Price::query()->where('plan_version_id', $version->id)->where('state', 'active')->whereNotNull('promo_amount_minor')->get()
                    ->map(fn (Price $p) => $p->currency.'/'.$p->period)->sort()->values()->all(),
                'features' => self::withdrawnWording($version),
            ];
        }
        foreach ($pending['products'] as $key => $description) {
            $rows[] = ['kind' => 'product', 'target' => $key, 'description' => $description];
        }

        return $rows;
    }

    /**
     * Publishes the pending changes of one revision (or of every revision) as new plan versions, through the bus. Each plan is its
     * own command and transaction: one refusal does not undo the others, and a second run picks up what is left.
     *
     * @return list<array{kind: string, target: string, from?: int, to?: int, error?: string}>
     */
    public function apply(?string $id, CommandContext $context): array
    {
        $bus = app(CommandBus::class); // resolved here: the doctor reads what is pending without building the bus
        $done = [];
        foreach ($this->pending($id) as $revision => $pending) {
            $reason = (string) self::REVISIONS[$revision]['reason'];
            foreach ($pending['plans'] as $target => $change) {
                [$product, $plan] = explode('/', $target, 2);
                $payload = ['op' => 'plan.publish', 'product_key' => $product, 'plan_key' => $plan, 'base_version' => $change['version'], 'reason' => $reason];
                foreach ($change['drop'] as $key => $bag) {
                    $payload[$bag][$key] = null; // PlanVersioning: a null takes the key off the new version
                }
                foreach ($change['set'] as $key => $set) {
                    $payload[$set['bag']][$key] = $set['to'];
                }
                // the number of versions never repeats (PlanVersioning never reuses one), so a rollback and a new run is a new command
                $key = 'catalog.revise:'.$revision.':'.$target.':v'.$change['version'].':n'.$this->nextNumber($this->currentVersion($target));
                try {
                    $result = $bus->dispatch(new CatalogCommand(mb_substr($key, 0, 190), $payload), $context);
                    $done[] = ['kind' => 'plan', 'target' => $target, 'from' => $change['version'], 'to' => (int) $result['version']];
                } catch (DomainError $e) {
                    $done[] = ['kind' => 'plan', 'target' => $target, 'from' => $change['version'], 'error' => $e->error.': '.$e->getMessage()];
                }
            }
            foreach ($pending['products'] as $key => $description) {
                $payload = ['op' => 'product.describe', 'product_key' => $key, 'description' => $description];
                try {
                    $bus->dispatch(new CatalogCommand(mb_substr('catalog.revise:'.$revision.':describe:'.$key.':'.substr(hash('sha256', (string) json_encode(Product::query()->where('key', $key)->value('description'))), 0, 16), 0, 190), $payload), $context);
                    $done[] = ['kind' => 'product', 'target' => $key];
                } catch (DomainError $e) {
                    $done[] = ['kind' => 'product', 'target' => $key, 'error' => $e->error.': '.$e->getMessage()];
                }
            }
        }

        return $done;
    }

    /**
     * One line for the doctor: 'revision: product/plan (−key, ~key), product key'.
     *
     * @param  array<string, array{plans: array<string, array{drop: array<string,string>, set: array<string,mixed>}>, products: array<string,mixed>}>  $pending
     */
    public static function summary(array $pending): string
    {
        $parts = [];
        foreach ($pending as $revision => $p) {
            $items = [];
            foreach ($p['plans'] as $target => $change) {
                $keys = array_merge(array_map(fn (string $k) => '−'.$k, array_keys($change['drop'])), array_map(fn (string $k) => '~'.$k, array_keys($change['set'])));
                $items[] = $target.' ('.implode(', ', $keys).')';
            }
            foreach (array_keys($p['products']) as $product) {
                $items[] = 'product '.$product;
            }
            $parts[] = $revision.': '.implode(', ', $items);
        }

        return implode(' · ', $parts);
    }

    private function known(string $id): string
    {
        if (! array_key_exists($id, self::REVISIONS)) {
            throw new DomainError('catalog_revision_unknown', "Unknown catalogue revision {$id}; known: ".implode(', ', self::ids()).'.', 422, ['field' => 'revision']);
        }

        return $id;
    }

    /**
     * A revision as the code reads it (the constant's literal types are narrower than what a revision may hold).
     *
     * @return array{reason: string, plans: array<string, list<string>>, rewrite: array<string, array<string, mixed>>, products: array<string, array{match: string, description: array{cs: string, en: string}}>}
     */
    private static function definition(string $revision): array
    {
        return self::REVISIONS[$revision];
    }

    /** @return array<string, array{version: int, drop: array<string,string>, set: array<string, array{bag: string, from: mixed, to: mixed}>}> */
    private function pendingPlans(string $revision): array
    {
        $definition = self::definition($revision);
        $targets = [];
        foreach (array_keys($definition['plans']) as $target) {
            $targets[$target] = true;
        }
        if ($definition['rewrite'] !== []) { // a rewrite looks at every plan: the old value is wrong wherever it is
            foreach (Plan::query()->with('product')->get() as $plan) {
                if ($plan->product !== null) {
                    $targets[$plan->product->key.'/'.$plan->key] = true;
                }
            }
        }
        $out = [];
        foreach (array_keys($targets) as $target) {
            $version = $this->currentVersion($target, false);
            if ($version === null) {
                continue; // a plan that is gone or has no version on this installation
            }
            $bags = ['entitlements' => (array) $version->entitlements, 'limits' => (array) ($version->limits ?? [])];
            $drop = [];
            foreach ($definition['plans'][$target] ?? [] as $key) {
                foreach ($bags as $bag => $values) {
                    if (array_key_exists($key, $values)) {
                        $drop[$key] = $bag;
                        break;
                    }
                }
            }
            $set = [];
            foreach ($definition['rewrite'] as $key => $map) {
                foreach ($bags as $bag => $values) {
                    if (array_key_exists($key, $values) && ! array_key_exists($key, $drop) && is_scalar($values[$key]) && array_key_exists((string) $values[$key], $map)) {
                        $set[$key] = ['bag' => $bag, 'from' => $values[$key], 'to' => $map[(string) $values[$key]]];
                        break;
                    }
                }
            }
            if ($drop !== [] || $set !== []) {
                $out[$target] = ['version' => (int) $version->version, 'drop' => $drop, 'set' => $set];
            }
        }
        ksort($out);

        return $out;
    }

    /** @return array<string, array{cs: string, en: string}> */
    private function pendingProducts(string $revision): array
    {
        $out = [];
        foreach (self::definition($revision)['products'] as $key => $change) {
            $product = Product::query()->where('key', $key)->first();
            if ($product === null) {
                continue;
            }
            $current = implode("\n", array_map('strval', (array) $product->description));
            if (preg_match($change['match'], $current) === 1 && (array) $product->description !== $change['description']) {
                $out[$key] = $change['description'];
            }
        }

        return $out;
    }

    private function currentVersion(string $target, bool $required = true): ?PlanVersion
    {
        [$productKey, $planKey] = array_pad(explode('/', $target, 2), 2, '');
        $productId = Product::query()->where('key', $productKey)->value('id');
        $plan = $productId === null ? null : Plan::query()->where('product_id', $productId)->where('key', $planKey)->first();
        $version = $plan?->currentVersion();
        if ($version === null && $required) {
            throw DomainError::notFound("Plan {$target}");
        }

        return $version;
    }

    /** @return list<string> the version's own bullet lines (any locale) that still name a withdrawn promise */
    private static function withdrawnWording(PlanVersion $version): array
    {
        $lines = [];
        foreach ((array) ($version->features ?? []) as $bag) {
            foreach ((array) $bag as $line) {
                if (preg_match(self::WITHDRAWN_WORDING, (string) $line) === 1) {
                    $lines[] = (string) $line;
                }
            }
        }

        return $lines;
    }

    private function nextNumber(PlanVersion $version): int
    {
        return (int) PlanVersion::query()->where('plan_id', $version->plan_id)->max('version') + 1;
    }
}
