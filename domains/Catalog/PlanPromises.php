<?php

declare(strict_types=1);

namespace Onhost\Domain\Catalog;

use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Services\Metering\MetricRegistry;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;

/**
 * What a plan may promise (audit §5ad, brain card H278). A plan version carries two machine-read bags —
 * `entitlements` and `limits` — and the platform is supposed to enforce, apply or measure every number in them. It
 * did not: five numbers on plans that were on sale were read by no code at all. `cpu_seconds_per_day`,
 * `db_connections`, `outbound_mail_per_hour` and `pps_limit` were not even shown to the customer; they only looked
 * like limits in the plan editor. `inodes` was sold, both panels count the files of a site, and nothing ever
 * compared the two.
 *
 * The rule used to be: a number is read by code outside the catalogue, or it is a **fair-use** promise declared here
 * and worded as one on the price list. That "read by code" question was answered by scanning source text for the
 * key's name — and the price list itself is code that names every key it sells (`CatalogPresentation`), so the guard
 * was trivially satisfied by the very file it should have caught. `products`, `connections` and
 * `dedicated_outbound_ip` passed that way: only the price list ever says the word.
 *
 * The rule now: every key not declared **fair use** is checked against `MetricRegistry` — a hand-verified table of
 * what actually measures or enforces each number today — with the price-list/presentation files excluded from the
 * text scan (`readInSource()`) so they can no longer stand in for real code. A numeric key (including a numeric
 * *string* such as `"500"` — `is_numeric()`, not the narrower `is_int()`/`is_float()` this file used to check) must
 * have a registry row whose `status` is `measured` or `enforced_only` **for the plan's own product family**
 * (`MetricRegistry::isKept($key, $family)`): a row verified only for, say, mail must not pass for a web plan that
 * happens to sell the same key name, and a numeric key's registry row is checked the same way whether the plan
 * sells it as an int or a numeric string. A non-numeric capability flag keeps the old text-scan rule. Either way, a
 * key that is neither may still be listed once, honestly, in `KNOWN_GAPS` — a ratchet that may only shrink: fixing a
 * gap without removing it here, or a new gap appearing, both fail the guard test
 * (`tests/Feature/Catalog/PlanPromisesTest.php`), and `onhost:doctor` shows the current list as a standing WARN.
 *
 * `dedicated_outbound_ip` and `dedicated_db` (boolean) were two such gaps, and `pitr_days` and `connections` two numeric ones;
 * the owner decided (2026-09-25, decisions 2/4/6) that they are not provided, so the catalogue revision
 * `2026-09-honest-promises` (`CatalogRevisions`, applied with `onhost:catalog:revise`) publishes versions without them and
 * they left `KNOWN_GAPS`. Until the operator applies it, the doctor names the command instead of reporting a new gap. The
 * versions customers already hold keep the promises they were sold (a version is never edited): `grandfatheredGaps()` lists
 * them so support can answer honestly. `products` (the e-shop product count) became fair use: a recommendation, worded
 * "Doporučeno do N produktů" (decision 5).
 */
final class PlanPromises
{
    /**
     * Numbers the price list states and no panel can apply: they are promises kept by how the platform is operated,
     * not by a setting. Each one is worded as fair use where the customer sees it.
     *
     * @var array<string,string>
     */
    public const FAIR_USE = [
        'relay_per_hour' => 'ISPConfig has no per-hour send limit in its remote API; the rate is held by the mail node itself',
        'capacity_gbps' => 'the scrubbing capacity of the network, not a per-customer setting',
        'pops' => 'how many points of presence the CDN has, not a per-customer setting',
        'io_class' => 'which storage class the node runs on; chosen by placement, not set per service',
        'slots' => 'how many players the game server fits — a consequence of its RAM and the game, not a panel field',
        'console' => 'which console the panel offers for this family',
        'ddos' => 'the baseline mitigation of the network',
        'anycast' => 'the announcement of the CDN network',
        'external_access' => 'how a managed database is reachable; set by the network policy of its node',
        'egress' => 'the outbound policy of the node',
        'warranty' => 'the warranty of the certificate authority',
        'issuance' => 'how fast the certificate authority issues',
        'validation' => 'the validation level of the certificate authority',
        'support' => 'the response time of the support team',
        'tls' => 'which certificate authority the free certificate comes from',
        'products' => 'a recommended catalogue size for the shop plan (owner decision 5, 2026-09-25): nothing reads back or caps a store\'s product count; worded "Doporučeno do N produktů"',
    ];

    /** Where a key has to be read for the promise to be kept. */
    public const ROOTS = ['domains', 'providers', 'platform', 'app', 'apps/surfaces/api'];

    /**
     * Files that name a key without applying it, excluded from `readInSource()`'s text scan for the same reason the
     * catalogue's own directory is: naming a key is not applying it (audit §5ad). Matched by suffix because
     * `readInSource()` walks whole directories and works in forward-slash paths.
     *
     * - the price list (`CatalogPresentation`) and the prototype surfaces renderer (`SurfaceRenderer`) turn a plan's
     *   numbers into words for a person;
     * - `MetricRegistry` itself names every key it documents, including every `KNOWN_GAPS` key — without this line it
     *   would make its own gap list "read", which is exactly the loophole this file closes.
     *
     * @var list<string>
     */
    private const PRESENTATION_ONLY = [
        'app/Http/Support/CatalogPresentation.php',
        'app/Http/Support/SurfaceRenderer.php',
        'domains/Services/Metering/MetricRegistry.php',
    ];

    /**
     * Keys nothing measures or enforces today, kept here on purpose (audit §5ad). This is a ratchet: it may only
     * shrink. `tests/Feature/Catalog/PlanPromisesTest.php` fails if the platform actually starts keeping one of these
     * promises and the line is not removed (a fixed gap must leave the list), and it fails just the same if a plan
     * starts selling a number that keeps none of the three ways out (measured, enforced, fair use) and is not named
     * here — a new gap must be added before it can pass, never absorbed silently.
     *
     * @var array<string,string>
     */
    public const KNOWN_GAPS = [
        'php_workers' => 'sold on wordpress/eshop (aaPanel), where the price list words it as the shared pool ("Sdílené PHP workery", decision 7); AaPanelWebProvider returns applied=false for every PHP-worker change, so the number itself is kept nowhere — see MetricRegistry',
        'aliases' => 'sold on mail plans; ISPConfig has no limit_mailalias and alias.create runs no count check — see MetricRegistry',
        'spam_filter' => 'sold on mail plans; only the price list names the antispam tier, no mail config sets an rspamd policy from it — see MetricRegistry',
        'traffic_tb' => 'sold on VPS/VDS and the CDN add-on; UsageWatch only measures traffic for web/managed families — see MetricRegistry',
        'bot_management' => 'sold on the CDN add-on, whose product has no executor at all — nothing in the platform configures bot management — see MetricRegistry',
        'snapshots' => 'sold on VPS/VDS; the "snapshots" feature limit is display-only, no count check gates snapshot.create — see MetricRegistry',
        'pids' => 'sold in every game plan\'s limits bag; PterodactylGameProvider\'s resource limits (memory/swap/disk/io/cpu) never include a PID cap — see MetricRegistry',
        'backup_days' => 'sold on web/managed, mail and db-s/db-m plans; kept only behind the owner\'s default-off rules — backups.as_sold (web/managed daily backups), mail.backup_retention (mailbox copies, TASK-0024) and backups.compute (managed databases, family `data`) — so with the rules off (the default) no family keeps it as sold — see MetricRegistry kept_under (found by family-scoping the registry check, audit §5ad)',
    ];

    /**
     * `KNOWN_GAPS`, but typed as a plain list rather than the literal constant shape — so a caller checking whether
     * any gap remains gets an ordinary runtime answer instead of a compile-time-constant one.
     *
     * @return list<string>
     */
    public static function knownGapKeys(): array
    {
        return array_keys(self::KNOWN_GAPS);
    }

    /**
     * Keys of this plan version that are neither measured/enforced (numeric), read by code (capability flags), nor
     * declared fair use or a documented `KNOWN_GAPS` ratchet entry.
     *
     * @param  list<string>  $read  keys the caller found in the source (the guard test scans it; the doctor passes the known list)
     * @return list<string>
     */
    public static function unapplied(PlanVersion $version, array $read): array
    {
        return array_values(array_filter(
            self::rawUnkept($version, $read),
            fn (string $key) => ! array_key_exists($key, self::KNOWN_GAPS)
        ));
    }

    /**
     * Same as `unapplied()`, but without subtracting `KNOWN_GAPS` — the raw truth `KNOWN_GAPS` has to equal, key for
     * key, no more and no less. Used by the guard test to keep the ratchet honest; `unapplied()`/`onSaleProblems()`
     * (what the doctor and the customer-facing checks use) subtract `KNOWN_GAPS` because those gaps are already
     * tracked, not hidden.
     *
     * @param  list<string>  $read
     * @return list<string>
     */
    public static function rawUnkept(PlanVersion $version, array $read): array
    {
        $entitlements = array_merge((array) $version->entitlements, (array) ($version->limits ?? []));
        $family = Plan::query()->find($version->plan_id)?->product?->family;
        $problems = [];
        foreach ($entitlements as $key => $value) {
            $key = (string) $key;
            if (array_key_exists($key, self::FAIR_USE)) {
                continue;
            }
            // numeric promises are decided by the hand-verified registry, never by "does the word appear
            // somewhere" — that question is exactly what let a price-list mention count as enforcement. A numeric
            // string ("500") is still a number to the customer and the panel; is_numeric() catches it where
            // is_int()/is_float() alone used to let it skip the registry entirely.
            if (is_numeric($value)) {
                // scoped to the plan's own product family (audit §5ad, MEDIUM finding): a row verified only for
                // e.g. mail must not pass for a web plan that happens to sell the same key name
                if (MetricRegistry::isKept($key, $family)) {
                    continue;
                }
                $problems[] = $key;

                continue;
            }
            // non-numeric capability flags keep the original rule: found by the generous text scan, or not
            if (! in_array($key, $read, true)) {
                $problems[] = $key;
            }
        }

        return $problems;
    }

    /**
     * Every numeric or capability key currently unkept across plans on sale, `KNOWN_GAPS` not subtracted — what the
     * ratchet must equal exactly (`tests/Feature/Catalog/PlanPromisesTest.php`).
     *
     * @param  list<string>  $read
     * @return list<string>
     */
    public static function actualGaps(array $read): array
    {
        $keys = [];
        $products = Product::query()->where('state', 'active')->pluck('id', 'key');
        foreach (Plan::query()->whereIn('product_id', $products->values())->where('state', 'active')->get() as $plan) {
            $version = $plan->currentVersion();
            if ($version === null) {
                continue;
            }
            foreach (self::rawUnkept($version, $read) as $key) {
                $keys[$key] = true;
            }
        }

        return array_keys($keys);
    }

    /**
     * Plans on sale that promise a number nothing applies — for the doctor and the guard test.
     *
     * @param  list<string>  $read
     * @return array<string, list<string>> plan key => the keys nothing applies
     */
    public static function onSaleProblems(array $read): array
    {
        $out = [];
        $products = Product::query()->where('state', 'active')->pluck('id', 'key');
        foreach (Plan::query()->whereIn('product_id', $products->values())->where('state', 'active')->get() as $plan) {
            $version = $plan->currentVersion();
            if ($version === null) {
                continue;
            }
            $problems = self::unapplied($version, $read);
            if ($problems !== []) {
                $out[(string) $products->search($plan->product_id).'/'.$plan->key] = $problems;
            }
        }

        return $out;
    }

    /**
     * The promises a version customers still hold makes and the version on sale no longer does: a revision (or staff) took a
     * number off the plan because the platform does not keep it, and the customers who bought the old version keep it on
     * paper (a version is never edited). Held = a service on it that has not ended, or an active/past-due subscription.
     * Informational (the doctor shows it as a WARN): support knows what those customers were sold.
     *
     * @param  list<string>  $read
     * @return array<string, list<string>> 'product/plan@vN' => keys
     */
    public static function grandfatheredGaps(array $read): array
    {
        $held = Service::query()->whereNotNull('plan_version_id')->whereNotIn('state', [ServiceStateMachine::TERMINATED, ServiceStateMachine::FAILED])->distinct()->pluck('plan_version_id')
            ->merge(Subscription::query()->whereNotNull('plan_version_id')->whereIn('state', ['active', 'past_due'])->distinct()->pluck('plan_version_id'))->unique()->values()->all();
        $out = [];
        foreach (PlanVersion::query()->whereIn('id', $held)->get() as $version) {
            $plan = Plan::query()->with('product')->find($version->plan_id);
            if ($plan === null || (int) $plan->current_version === (int) $version->version) {
                continue;
            }
            $current = $plan->currentVersion();
            $gaps = array_values(array_diff(self::rawUnkept($version, $read), $current === null ? [] : self::rawUnkept($current, $read)));
            if ($gaps !== []) {
                $out[($plan->product->key ?? '?').'/'.$plan->key.'@v'.$version->version] = $gaps;
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * Every plan key any code outside the catalogue mentions. The scan is deliberately generous — a key that appears
     * anywhere under ROOTS counts as read — because the cost of a false "read" is one number nobody notices, while the
     * cost of a false "unapplied" is a guard that cries wolf and gets switched off.
     *
     * @return list<string>
     */
    public static function readInSource(): array
    {
        $keys = [];
        foreach (Plan::query()->with('versions')->get() as $plan) {
            foreach ($plan->versions as $version) {
                foreach (array_merge((array) $version->entitlements, (array) ($version->limits ?? [])) as $key => $unused) {
                    $keys[(string) $key] = true;
                }
            }
        }
        $haystack = '';
        foreach (self::ROOTS as $root) {
            $dir = base_path($root);
            if (! is_dir($dir)) {
                continue;
            }
            /** @var \SplFileInfo $file */
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
                $path = str_replace('\\', '/', $file->getPathname());
                if (! preg_match('/\.(php|js)$/', $path) || str_contains($path, '/Catalog/')) {
                    continue; // the catalogue only carries the keys; somebody else has to read them
                }
                if (self::isPresentationOnly($path)) {
                    continue; // showing a number is not applying it (audit §5ad) — see PRESENTATION_ONLY
                }
                $haystack .= "\n".(string) file_get_contents($path);
            }
        }

        return array_values(array_filter(array_keys($keys), fn (string $key) => str_contains($haystack, "'{$key}'") || str_contains($haystack, "\"{$key}\"")));
    }

    private static function isPresentationOnly(string $path): bool
    {
        foreach (self::PRESENTATION_ONLY as $suffix) {
            if (str_ends_with($path, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
