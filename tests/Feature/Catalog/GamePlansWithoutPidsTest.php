<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Catalog\CatalogRevisions;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\PlanPromises;
use Onhost\Domain\Services\Metering\MetricRegistry;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Commands\CommandContext;

/*
 * Phase T, metering M3 (docs/proposals/metering-after-launch.md §2): every game plan sold a PID cap in its `limits` bag
 * (`pids` 400 / 500 / 800 / 1200) that nothing kept. Pterodactyl has no per-server PID field (its server limits are
 * memory/swap/disk/io/cpu/threads/oom_disabled), PterodactylGameProvider never sent it, and nothing on the platform reads it back.
 * The recommended way out is the catalogue, not a panel: the seeder stops writing it, a data migration takes it off the plan
 * versions nobody holds while the seeded number still stands, and the revision `2026-10-game-plans-without-pids` publishes a new
 * version where a held version is the current one. A held version is a contract and keeps what it was sold.
 */

/** @return array<string, PlanVersion> plan key => its first version */
function t2GameVersions(): array
{
    $product = Product::query()->where('key', 'game')->firstOrFail();
    $out = [];
    foreach (Plan::query()->where('product_id', $product->id)->get() as $plan) {
        $out[$plan->key] = PlanVersion::query()->where('plan_id', $plan->id)->orderBy('version')->firstOrFail();
    }

    return $out;
}

function t2WithPids(PlanVersion $version, int $pids): PlanVersion
{
    $version->forceFill(['limits' => (array) ($version->limits ?? []) + ['pids' => $pids]])->save();

    return $version->fresh();
}

/** A customer's game server on the version it was sold with: the version is held from now on. */
function t2Hold(string $organizationId, PlanVersion $version): void
{
    Service::query()->create([
        'organization_id' => $organizationId, 'product_key' => 'game', 'family' => 'game', 'name' => 'Game', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1',
        'plan_version_id' => $version->id, 'entitlements' => (array) $version->entitlements, 'desired_spec' => ['family' => 'game'], 'sla_class' => 'standard', 'tags' => [],
    ]);
}

beforeEach(function () {
    $this->seed([CatalogSeeder::class]);
});

it('seeds no game plan with a PID cap and keeps every other limit', function () {
    foreach (t2GameVersions() as $key => $version) {
        expect(array_key_exists('pids', (array) ($version->limits ?? [])))->toBeFalse("{$key} still sells a PID cap")
            ->and(array_key_exists('cpu_pct', (array) $version->limits))->toBeTrue("{$key} lost its CPU limit");
    }
});

it('drops the seeded PID cap from unheld game plan versions only while the seeded number stands', function () {
    $versions = t2GameVersions();
    $before = DB::table('plan_versions')->count();
    // a database migrated before phase T: the seeded numbers, one number staff changed, one version a customer holds
    $seeded = t2WithPids($versions['game-8'], 500);
    $custom = t2WithPids($versions['game-16'], 640);
    $held = t2WithPids($versions['game-32'], 1200);
    [, $org] = $this->customerWithOrganization();
    t2Hold($org->id, $held);

    $migration = require base_path('database/migrations/0001_01_01_001200_game_plans_without_unkept_pids.php');
    $migration->up();
    expect((array) $seeded->fresh()->limits)->not->toHaveKey('pids')
        ->and($seeded->fresh()->limits['cpu_pct'])->toBe(300) // nothing else in the bag moves
        ->and($custom->fresh()->limits['pids'])->toBe(640) // a number staff wrote is theirs
        ->and($held->fresh()->limits['pids'])->toBe(1200); // a held version is a contract

    $migration->down();
    expect($seeded->fresh()->limits['pids'])->toBe(500)
        ->and($custom->fresh()->limits['pids'])->toBe(640)
        ->and($held->fresh()->limits['pids'])->toBe(1200);
    expect(DB::table('plan_versions')->count())->toBe($before); // nothing created or removed
});

it('publishes a game plan version without the PID cap where a held version still sells it', function () {
    $held = t2WithPids(t2GameVersions()['game-8'], 500);
    [, $org] = $this->customerWithOrganization();
    t2Hold($org->id, $held);
    $revisions = app(CatalogRevisions::class);

    expect($revisions->pendingKeys())->toContain('pids')
        ->and(array_keys($revisions->pending()))->toContain('2026-10-game-plans-without-pids');

    $revisions->apply('2026-10-game-plans-without-pids', CommandContext::system('test:t2-pids'));

    $current = Plan::query()->where('key', 'game-8')->firstOrFail()->currentVersion();
    expect($current?->id)->not->toBe($held->id)
        ->and((array) ($current?->limits ?? []))->not->toHaveKey('pids')
        ->and($current?->limits['cpu_pct'] ?? null)->toBe(300)
        ->and($held->fresh()->limits['pids'])->toBe(500) // the customer keeps what was sold
        ->and($revisions->pendingKeys())->not->toContain('pids');
});

it('takes pids off the metering gap list: no plan on sale sells it any more', function () {
    app(CatalogRevisions::class)->apply(null, CommandContext::system('test:t2-pids'));

    expect(PlanPromises::KNOWN_GAPS)->not->toHaveKey('pids')
        ->and(PlanPromises::actualGaps(PlanPromises::readInSource()))->not->toContain('pids')
        ->and(MetricRegistry::get('pids')['status'] ?? null)->toBe(MetricRegistry::GAP); // the row stays for versions customers hold
});
