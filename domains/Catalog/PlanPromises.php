<?php

declare(strict_types=1);

namespace Onhost\Domain\Catalog;

use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Product;

/**
 * What a plan may promise (audit §5ad). A plan version carries two machine-read bags — `entitlements` and `limits` —
 * and the platform is supposed to enforce, apply or measure every number in them. It did not: five numbers on plans
 * that were on sale were read by no code at all. `cpu_seconds_per_day`, `db_connections`, `outbound_mail_per_hour`
 * and `pps_limit` were not even shown to the customer; they only looked like limits in the plan editor. `inodes` was
 * sold, both panels count the files of a site, and nothing ever compared the two.
 *
 * The rule: a number in `entitlements` or `limits` is read by code outside the catalogue, or it is a **fair-use**
 * promise declared here and worded as one on the price list. A guard test scans the source in both directions
 * (`tests/Feature/Catalog/PlanPromisesTest.php`) and `onhost:doctor` reports plans on sale that break it, because a
 * version published in the administration can put a number back long after this file was written.
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
    ];

    /** Where a key has to be read for the promise to be kept. */
    public const ROOTS = ['domains', 'providers', 'platform', 'app', 'apps/surfaces/api'];

    /**
     * Keys of this plan version that are neither read by code nor declared fair use.
     *
     * @param  list<string>  $read  keys the caller found in the source (the guard test scans it; the doctor passes the known list)
     * @return list<string>
     */
    public static function unapplied(PlanVersion $version, array $read): array
    {
        $keys = array_keys(array_merge((array) $version->entitlements, (array) ($version->limits ?? [])));

        return array_values(array_filter($keys, fn (string $key) => ! in_array($key, $read, true) && ! array_key_exists($key, self::FAIR_USE)));
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
                $haystack .= "\n".(string) file_get_contents($path);
            }
        }

        return array_values(array_filter(array_keys($keys), fn (string $key) => str_contains($haystack, "'{$key}'") || str_contains($haystack, "\"{$key}\"")));
    }
}
