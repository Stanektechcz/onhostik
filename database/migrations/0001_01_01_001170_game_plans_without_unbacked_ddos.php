<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Services\Models\Service;

/**
 * Phase P, owner decision P-R69 (DD-1, 2026-10-13): the DDoS protection of the game servers is not documented anywhere (no upstream
 * contract, capacity, layer or detection time), so the catalogue's `ddos` bullet of the game plans ("Anti-DDoS upstream L3/L4", and
 * "… + game profiles" on Game 32 GB) is withdrawn. A deploy runs migrations, not seeders: the bullet the seeder wrote is removed here —
 * only from a plan version nobody holds (a held version is a contract and changes only through a catalogue revision, four eyes) and
 * only while it still carries the seeded words (words staff wrote are theirs). Prices, names and every other entitlement stay.
 */
return new class extends Migration
{
    /** plan key => the words the seeder wrote */
    private const SEEDED = [
        'game-custom' => 'upstream L3/L4',
        'game-8' => 'upstream L3/L4',
        'game-16' => 'upstream L3/L4',
        'game-32' => 'upstream L3/L4 + game profiles',
    ];

    public function up(): void
    {
        $this->each(function (PlanVersion $version, string $words): void {
            $entitlements = (array) $version->entitlements;
            if (($entitlements['ddos'] ?? null) !== $words) {
                return;
            }
            unset($entitlements['ddos']);
            $version->forceFill(['entitlements' => $entitlements])->save();
        });
    }

    public function down(): void
    {
        $this->each(function (PlanVersion $version, string $words): void {
            $entitlements = (array) $version->entitlements;
            if (array_key_exists('ddos', $entitlements)) {
                return;
            }
            $version->forceFill(['entitlements' => $entitlements + ['ddos' => $words]])->save();
        });
    }

    /** @param  callable(PlanVersion, string): void  $change */
    private function each(callable $change): void
    {
        $product = Product::query()->where('key', 'game')->first();
        if ($product === null) {
            return; // a fresh database: the seeder writes the plans without the bullet
        }
        foreach (self::SEEDED as $planKey => $words) {
            $plan = Plan::query()->where('product_id', $product->id)->where('key', $planKey)->first();
            if ($plan === null) {
                continue;
            }
            foreach (PlanVersion::query()->where('plan_id', $plan->id)->orderBy('version')->get() as $version) {
                $held = Service::query()->where('plan_version_id', $version->id)->exists()
                    || Subscription::query()->where('plan_version_id', $version->id)->exists();
                if (! $held) {
                    $change($version, $words);
                }
            }
        }
    }
};
