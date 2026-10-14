<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Services\Models\Service;

/**
 * Phase T, metering M3 (docs/proposals/metering-after-launch.md §2): every game plan sold a PID cap in its `limits` bag that nothing
 * kept — Pterodactyl has no per-server PID field and PterodactylGameProvider never sent one (`PlanPromises::KNOWN_GAPS` 'pids'). A
 * deploy runs migrations, not seeders: the cap the seeder wrote is removed here — only from a plan version nobody holds (a held
 * version is a contract and changes only through the catalogue revision `2026-10-game-plans-without-pids`, which publishes a new
 * version) and only while it still carries the seeded number (a number staff wrote is theirs). Prices, names, the CPU limit and
 * every entitlement stay.
 */
return new class extends Migration
{
    /** plan key => the PID cap the seeder wrote */
    private const SEEDED = [
        'game-custom' => 400,
        'game-8' => 500,
        'game-16' => 800,
        'game-32' => 1200,
    ];

    public function up(): void
    {
        $this->each(function (PlanVersion $version, int $pids): void {
            $limits = (array) ($version->limits ?? []);
            if (($limits['pids'] ?? null) !== $pids) {
                return;
            }
            unset($limits['pids']);
            $version->forceFill(['limits' => $limits])->save();
        });
    }

    public function down(): void
    {
        $this->each(function (PlanVersion $version, int $pids): void {
            $limits = (array) ($version->limits ?? []);
            if (array_key_exists('pids', $limits)) {
                return;
            }
            $version->forceFill(['limits' => ['pids' => $pids] + $limits])->save();
        });
    }

    /** @param  callable(PlanVersion, int): void  $change */
    private function each(callable $change): void
    {
        $product = Product::query()->where('key', 'game')->first();
        if ($product === null) {
            return; // a fresh database: the seeder writes the plans without the cap
        }
        foreach (self::SEEDED as $planKey => $pids) {
            $plan = Plan::query()->where('product_id', $product->id)->where('key', $planKey)->first();
            if ($plan === null) {
                continue;
            }
            foreach (PlanVersion::query()->where('plan_id', $plan->id)->orderBy('version')->get() as $version) {
                $held = Service::query()->where('plan_version_id', $version->id)->exists()
                    || Subscription::query()->where('plan_version_id', $version->id)->exists();
                if (! $held) {
                    $change($version, $pids);
                }
            }
        }
    }
};
