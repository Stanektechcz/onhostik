<?php

declare(strict_types=1);

namespace Onhost\Domain\Catalog;

use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\Commands\CatalogCommand;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Price;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\Models\ProductOption;
use Onhost\Domain\Provisioning\Scheduling\PlacementRules;
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
 *  - `drop_undelivered` (optional): keys the new version drops from EVERY plan whose current version sells them where the platform
 *    cannot deliver them (`undeliverable()`: `php_workers_dedicated` on a panel without a PHP pool per site, PlacementRules; a key
 *    of `ExecutorDelivery::WEB_FEATURES` on a web executor that does not declare its feature, R4) — a plan list the code cannot know
 *    in advance (TASK-0027);
 *  - `withdraw_undelivered_options` (optional): entitlement keys whose priced options (`ProductOption.meta.entitlement.key`) are
 *    withdrawn (`CatalogCommand option.delete`) from every product whose executor cannot deliver them (R4). An option is a price
 *    for new orders only: what an order already bought stays in its service's entitlements and its subscription's price;
 *  - `wording` (optional): a regex for features lines that still repeat what this revision withdraws (the preview warns);
 *  - `rewrite`: key => [old value => new value], applied to every plan whose current version still carries the old value;
 *  - `products`: product key => ['match' => regex on the current description, 'description' => {cs, en}] — replaced only while
 *    the current description still says what the revision withdraws, so a text staff wrote since is left alone;
 *  - `create`: keys of `PRODUCTS` a running catalogue must have — created (`CatalogCommand product.create`) while missing, never
 *    changed once they exist. A product defined here carries no prices of its own (a raise is priced by its parent's options);
 *  - `grant` (optional): 'product/plan' => [key => value] the new version SELLS (in `entitlements`, or in `limits` where the current
 *    version keeps the key there) — pending while the current version does not carry exactly that value (TASK-0110);
 *  - `proposal` (optional, true): prepared for the owner's decision and NOT part of "every revision": the doctor does not ask for
 *    it and `onhost:catalog:revise` without an id neither previews nor applies it; it is previewed and applied only by its id.
 */
final class CatalogRevisions
{
    public const REVISIONS = [
        // TASK-0110 (owner decision G-R5): a proposal, prepared and not applied — docs/proposals/custom-iso-plans.md. The owner decides
        // which plans sell a custom ISO and how big one image may be; then `php artisan onhost:catalog:revise 2026-10-custom-iso --apply`
        // (or the plan editor) publishes new versions. Customers on the versions they hold keep them (no custom ISO until a plan change).
        '2026-10-custom-iso' => [
            'proposal' => true,
            'reason' => 'Rozhodnutí vlastníka G-R5 (návrh TASK-0110): vlastní ISO jen tam, kde ho objednaný tarif VPS obsahuje — nové verze tarifů Compute 4/8/16 a VDS s vlastním ISO (jeden obraz do 4 GB); ceny beze změny, stávající smlouvy beze změny.',
            'plans' => [],
            'rewrite' => [],
            'products' => [],
            'grant' => [
                'vps/compute-4' => ['custom_iso' => true, 'custom_iso_max_mb' => 4096],
                'vps/compute-8' => ['custom_iso' => true, 'custom_iso_max_mb' => 4096],
                'vps/compute-16' => ['custom_iso' => true, 'custom_iso_max_mb' => 4096],
                'vds/vds-4' => ['custom_iso' => true, 'custom_iso_max_mb' => 4096],
                'vds/vds-8' => ['custom_iso' => true, 'custom_iso_max_mb' => 4096],
                'vds/vds-16' => ['custom_iso' => true, 'custom_iso_max_mb' => 4096],
            ],
            'wording' => '/ISO/u',
        ],
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
        // TASK-0027 C4: decision 7 for the plans PlacementRules cannot put on a panel with a PHP pool per site (eshop/shop-peak on aaPanel)
        '2026-09-shared-php-workers' => [
            'reason' => 'Rozhodnutí vlastníka 7 (2026-09-25): na aaPanelu obsluhuje jeden PHP pool celý uzel, vyhrazené PHP workery tam nic nedrží — nové verze tarifů, které je slibují na panelu bez vlastního poolu pro web, je nemají a ceník uvádí „Sdílené PHP workery“; ceny beze změny, stávající smlouvy beze změny.',
            'plans' => [],
            'rewrite' => [],
            'products' => [],
            'drop_undelivered' => ['php_workers_dedicated'],
        ],
        '2026-09-limit-raise' => [
            'reason' => 'Rozhodnutí vlastníka 8 (2026-09-25): placené navýšení jednoho limitu jedné služby za cenu volby jejího produktu, účtované každé období (TASK-0022 limit-raise).',
            'plans' => [],
            'rewrite' => [],
            'products' => [],
            'create' => ['limit-raise'],
        ],
        // owner decision R4 (audit 2026-10, P1-15): the e-shop plans (aaPanel, `mail: false`) and the dedicated IPv4 option of the web products
        '2026-10-deliverable-web-plans' => [
            'reason' => 'Rozhodnutí vlastníka R4 (audit 2026-10): tarif nabízí jen to, co jeho server dodá. Schránky v e-shop tarifech (server bez pošty) a dedikovaná IPv4 u webových produktů (žádný webový server ji webu nepřidělí) se stahují z prodeje — nové verze bez schránek, volba IPv4 končí; ceny beze změny, stávající smlouvy a objednané volby beze změny.',
            'plans' => [],
            'rewrite' => [],
            'products' => [],
            'drop_undelivered' => ['mailboxes'],
            'withdraw_undelivered_options' => ['ipv4'],
            'wording' => '/schrán|mailbox|IPv4/iu',
        ],
    ];

    /**
     * Products the code base defines (created by a revision's `create`, and by `CatalogSeeder` on a fresh install). A product here
     * has no plan and no price: what it costs is decided elsewhere (`LimitRaiseLine`: the parent product's option price).
     */
    public const PRODUCTS = [
        'limit-raise' => [
            'family' => 'addon', 'executor' => null, 'billing_model' => 'subscription', 'sort' => 205, 'state' => 'active',
            'name' => ['cs' => 'Navýšení limitu', 'en' => 'Limit raise'],
            'description' => ['cs' => 'Víc schránek, databází nebo prostoru pro jednu službu za cenu volby jejího tarifu, účtováno s každým obdobím.', 'en' => 'More mailboxes, databases or space for one service at the price of its plan\'s option, billed every period.'],
            // never on the price list and never a cart upsell: it is ordered for one running service (LimitRaiseLine)
            'meta' => ['listed' => false, 'limit_raise' => true],
        ],
    ];

    /** Features lines staff may have written that repeat a withdrawn promise: the preview warns, nothing rewrites them. */
    private const WITHDRAWN_WORDING = '/PITR|spojení|connection|dedikovan|dedicated/iu';

    /** The revisions "every revision" means: all but the proposals, which wait for the owner and are named by their id (TASK-0110). @return list<string> */
    public static function ids(): array
    {
        return array_values(array_filter(array_keys(self::REVISIONS), fn (string $id) => empty(self::definition($id)['proposal'])));
    }

    /** Revisions prepared for the owner's decision and not applied by "every revision" (TASK-0110). @return list<string> */
    public static function proposals(): array
    {
        return array_values(array_diff(array_keys(self::REVISIONS), self::ids()));
    }

    /**
     * What is still to do, per revision (only revisions with something pending).
     *
     * @return array<string, array{plans: array<string, array{version: int, drop: array<string,string>, set: array<string, array{bag: string, from: mixed, to: mixed}>}>, products: array<string, array{cs: string, en: string}>, create?: list<string>, options?: array<string, string>}>
     */
    public function pending(?string $id = null): array
    {
        $out = [];
        foreach ($id === null ? self::ids() : [$this->known($id)] as $revision) {
            $plans = $this->pendingPlans($revision);
            $products = $this->pendingProducts($revision);
            $create = $this->pendingCreates($revision);
            $options = $this->pendingOptions($revision);
            if ($plans !== [] || $products !== [] || $create !== [] || $options !== []) {
                $out[$revision] = ['plans' => $plans, 'products' => $products] + ($create === [] ? [] : ['create' => $create]) + ($options === [] ? [] : ['options' => $options]);
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
        foreach ($pending['create'] ?? [] as $key) {
            $rows[] = ['kind' => 'create', 'target' => $key, 'definition' => self::PRODUCTS[$key]];
        }
        foreach ($pending['plans'] as $target => $change) {
            $version = $this->currentVersion($target);
            $rows[] = [
                'kind' => 'plan', 'target' => $target, 'from' => $change['version'], 'to' => $this->nextNumber($version),
                'drop' => $change['drop'], 'set' => $change['set'],
                'services' => Service::query()->where('plan_version_id', $version->id)->whereNotIn('state', [ServiceStateMachine::TERMINATED, ServiceStateMachine::FAILED])->count(),
                'subscriptions' => Subscription::query()->where('plan_version_id', $version->id)->whereIn('state', ['active', 'past_due'])->count(),
                // promo prices of the current version: a revision carries them into the new one (`keep_promos`), the preview says so
                'promos' => Price::query()->where('plan_version_id', $version->id)->where('state', 'active')->whereNotNull('promo_amount_minor')->get()
                    ->map(fn (Price $p) => $p->currency.'/'.$p->period)->sort()->values()->all(),
                'features' => self::withdrawnWording($version, (string) (self::definition($id)['wording'] ?? self::WITHDRAWN_WORDING)),
            ];
        }
        foreach ($pending['options'] ?? [] as $target => $entitlement) {
            [$product, $option] = explode('#', $target, 2);
            $rows[] = [
                'kind' => 'option', 'target' => $target, 'entitlement' => $entitlement,
                // who ordered it keeps it: the choice is in the service's entitlements, its price in the subscription
                'services' => Service::query()->where('product_key', $product)->whereNotIn('state', [ServiceStateMachine::TERMINATED, ServiceStateMachine::FAILED])
                    ->get(['id', 'entitlements'])->filter(fn (Service $service) => ! empty(data_get($service->entitlements, 'options.'.$option)))->count(),
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
        // what a revision still has to do is read just before it runs: two revisions may change the same plan, and the second
        // publishes on top of the version the first one just made (a list read up front was bound to the version before it)
        foreach ($id === null ? self::ids() : [$this->known($id)] as $revision) {
            $pending = $this->pending($revision)[$revision] ?? null;
            if ($pending === null) {
                continue;
            }
            $reason = (string) self::REVISIONS[$revision]['reason'];
            foreach ($pending['create'] ?? [] as $key) { // a product the code defines, as the four-eyes catalogue operation (system actor on the CLI)
                try {
                    $bus->dispatch(new CatalogCommand('catalog.revise:'.$revision.':create:'.$key, ['op' => 'product.create', 'product_key' => $key, 'reason' => $reason]), $context);
                    $done[] = ['kind' => 'create', 'target' => $key];
                } catch (DomainError $e) {
                    $done[] = ['kind' => 'create', 'target' => $key, 'error' => $e->error.': '.$e->getMessage()];
                }
            }
            foreach ($pending['plans'] as $target => $change) {
                [$product, $plan] = explode('/', $target, 2);
                // keep_promos: a revision changes no price, so an introductory price on sale stays on sale (review round 1)
                $payload = ['op' => 'plan.publish', 'product_key' => $product, 'plan_key' => $plan, 'base_version' => $change['version'], 'reason' => $reason, 'keep_promos' => true];
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
            foreach ($pending['options'] ?? [] as $target => $entitlement) { // a price for new orders only, through the bus (four eyes in the console, the system actor here)
                [$product, $option] = explode('#', $target, 2);
                $optionId = (string) ProductOption::query()->where('product_id', Product::query()->where('key', $product)->value('id'))->where('key', $option)->value('id');
                try {
                    // the option's id in the key: an option staff put back later is a new row, and a new run withdraws it again
                    $bus->dispatch(new CatalogCommand(mb_substr('catalog.revise:'.$revision.':option:'.$target.':'.$optionId, 0, 190), ['op' => 'option.delete', 'product_key' => $product, 'key' => $option, 'reason' => $reason]), $context);
                    $done[] = ['kind' => 'option', 'target' => $target];
                } catch (DomainError $e) {
                    $done[] = ['kind' => 'option', 'target' => $target, 'error' => $e->error.': '.$e->getMessage()];
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
     * @param  array<string, array{plans: array<string, array{drop: array<string,string>, set: array<string,mixed>}>, products: array<string,mixed>, create?: list<string>, options?: array<string,string>}>  $pending
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
            foreach ($p['create'] ?? [] as $product) {
                $items[] = 'new product '.$product;
            }
            foreach (array_keys($p['options'] ?? []) as $option) {
                $items[] = 'option '.$option.' withdrawn';
            }
            $parts[] = $revision.': '.implode(', ', $items);
        }

        return implode(' · ', $parts);
    }

    private function known(string $id): string
    {
        if (! array_key_exists($id, self::REVISIONS)) {
            throw new DomainError('catalog_revision_unknown', "Unknown catalogue revision {$id}; known: ".implode(', ', array_keys(self::REVISIONS)).'.', 422, ['field' => 'revision']);
        }

        return $id;
    }

    /**
     * A revision as the code reads it (the constant's literal types are narrower than what a revision may hold).
     *
     * @return array{reason: string, plans: array<string, list<string>>, rewrite: array<string, array<string, mixed>>, products: array<string, array{match: string, description: array{cs: string, en: string}}>, create?: list<string>, drop_undelivered?: list<string>, withdraw_undelivered_options?: list<string>, wording?: string, grant?: array<string, array<string, mixed>>, proposal?: bool}
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
        $grant = (array) ($definition['grant'] ?? []);
        foreach (array_keys($grant) as $target) {
            $targets[(string) $target] = true;
        }
        $undelivered = array_values(array_map('strval', (array) ($definition['drop_undelivered'] ?? [])));
        if ($definition['rewrite'] !== [] || $undelivered !== []) { // a rewrite (or an undeliverable key) looks at every plan: the old value is wrong wherever it is
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
            $product = Product::query()->where('key', explode('/', $target, 2)[0])->first(['executor', 'family']);
            $executor = (string) $product?->executor;
            $family = (string) $product?->family;
            $keys = array_merge($definition['plans'][$target] ?? [], array_values(array_filter($undelivered, fn (string $key) => self::undeliverable($key, $executor, $bags['entitlements'], $family, $bags['limits']))));
            foreach (array_unique($keys) as $key) {
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
            foreach ((array) ($grant[$target] ?? []) as $key => $value) { // what the new version sells (TASK-0110)
                $bag = array_key_exists($key, $bags['limits']) && ! array_key_exists($key, $bags['entitlements']) ? 'limits' : 'entitlements';
                if (($bags[$bag][$key] ?? null) !== $value) {
                    $set[(string) $key] = ['bag' => $bag, 'from' => $bags[$bag][$key] ?? null, 'to' => $value];
                }
            }
            if ($drop !== [] || $set !== []) {
                $out[$target] = ['version' => (int) $version->version, 'drop' => $drop, 'set' => $set];
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * Whether a plan of a product on this executor sells the key where the platform cannot deliver it. Only keys whose delivery
     * depends on where the plan runs are known here; any other key is never dropped this way.
     *
     * @param  array<string,mixed>  $entitlements
     */
    private static function undeliverable(string $key, string $executor, array $entitlements, string $family = '', array $limits = []): bool
    {
        return match ($key) {
            'php_workers_dedicated' => PlacementRules::undelivered($executor, $entitlements), // decision 7: no PHP pool per site on this panel
            default => in_array($key, ExecutorDelivery::undelivered($family, $executor, array_merge($limits, $entitlements)), true), // R4: a feature the site's server does not have
        };
    }

    /**
     * Options a revision withdraws: every priced option, of any product, whose entitlement key the revision names and whose
     * product's executor cannot deliver it (R4). Stateless like the rest: an option staff already removed is not pending.
     *
     * @return array<string, string> 'product#option' => the entitlement key it grants
     */
    private function pendingOptions(string $revision): array
    {
        $keys = array_values(array_map('strval', (array) (self::definition($revision)['withdraw_undelivered_options'] ?? [])));
        if ($keys === []) {
            return [];
        }
        $out = [];
        foreach (Product::query()->get() as $product) {
            foreach (ProductOption::query()->where('product_id', $product->id)->orderBy('sort')->get() as $option) {
                foreach (array_intersect(PlanPromises::optionUndelivered((array) $option->meta, (string) $product->family, (string) $product->executor), $keys) as $key) {
                    $out[$product->key.'#'.$option->key] = $key;
                }
            }
        }
        ksort($out);

        return $out;
    }

    /** @return list<string> products of `PRODUCTS` this revision creates that the catalogue does not have yet */
    private function pendingCreates(string $revision): array
    {
        $keys = (array) (self::definition($revision)['create'] ?? []);

        return array_values(array_filter(array_map('strval', $keys), fn (string $key) => ! Product::query()->where('key', $key)->exists()));
    }

    /**
     * The product row a definition of `PRODUCTS` becomes (`CatalogCommand product.create`, `CatalogSeeder`), or the refusal.
     *
     * @return array<string,mixed>
     */
    public static function productAttributes(string $key): array
    {
        $definition = self::PRODUCTS[$key] ?? throw new DomainError('product_undefined', "Product {$key} is not defined in code (CatalogRevisions::PRODUCTS); a new product comes with the code that delivers it.", 422, ['field' => 'product_key']);

        return ['key' => $key] + $definition;
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
    private static function withdrawnWording(PlanVersion $version, string $wording = self::WITHDRAWN_WORDING): array
    {
        $lines = [];
        foreach ((array) ($version->features ?? []) as $bag) {
            foreach ((array) $bag as $line) {
                if (preg_match($wording, (string) $line) === 1) {
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
