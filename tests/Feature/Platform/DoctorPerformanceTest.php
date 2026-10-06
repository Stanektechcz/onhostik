<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\DoctorBenchmarkSeeder;
use Database\Seeders\LegalEntitySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Loyalty\Models\LoyaltyPoint;
use Onhost\Domain\Platform\GoLiveChecks;

/*
 * H5 (performance): the doctor rows must not grow queries with the catalogue or the customer base, and the lookups that group by
 * organization or by credit note have an index of their own.
 */

beforeEach(fn () => Http::preventStrayRequests());

it('asks the catalogue for custom ISO in two queries however many plans there are', function () {
    $this->seed([LegalEntitySeeder::class, CatalogSeeder::class]);
    $method = new ReflectionMethod(GoLiveChecks::class, 'customIsoSold');

    DB::flushQueryLog();
    DB::enableQueryLog();
    $method->invoke(new GoLiveChecks);
    $catalogueQueries = collect(DB::getQueryLog())->filter(fn (array $q) => preg_match('/from "(plans|plan_versions)"/', $q['query']) === 1)->count();
    DB::disableQueryLog();

    expect(Plan::query()->count())->toBeGreaterThan(2)
        ->and($catalogueQueries)->toBeLessThanOrEqual(2);
});

it('has the indexes the loyalty aggregates and the refund credit-note lookup use', function () {
    expect(Schema::hasIndex('loyalty_points', ['organization_id', 'created_at']))->toBeTrue()
        ->and(Schema::hasIndex('payment_refunds', ['credit_note_id']))->toBeTrue();
});

it('runs the whole doctor with a query count that does not grow with the customer base', function () {
    $this->seed([LegalEntitySeeder::class, CatalogSeeder::class]);
    $measure = function (int $organizations): int {
        DB::table('loyalty_points')->where('organization_id', 'like', 'org_bench%')->delete();
        foreach (['organizations' => 'org_bench', 'services' => 'svc_b', 'invoices' => 'inv_b', 'payment_refunds' => 'rf_b'] as $table => $prefix) {
            DB::table($table)->where('id', 'like', $prefix.'%')->delete();
        }
        putenv("ONHOST_BENCH_ORGS={$organizations}");
        $this->seed(DoctorBenchmarkSeeder::class);
        putenv('ONHOST_BENCH_ORGS');
        DB::flushQueryLog();
        DB::enableQueryLog();
        (new GoLiveChecks)->rows();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    $small = $measure(40);
    $large = $measure(400);

    expect(LoyaltyPoint::query()->count())->toBe(1200)->and(abs($large - $small))->toBeLessThanOrEqual(3); // cache warm-up may differ by a query; a per-organization query would add hundreds
});
