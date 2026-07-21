<?php

declare(strict_types=1);

use App\Domains\Billing\Models\Invoice;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Phase L (L153): N+1 guards on the hot list views.
 *
 * These lists already eager-load their relations. The point of these tests is
 * to keep it that way: the query count must not grow with the number of rows.
 * If someone drops a `with(...)` the count jumps from constant to linear and
 * the test fails — which is the whole reason the guard exists, since an N+1 is
 * invisible until the table is big enough to hurt in production.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

/** Count the queries a request makes. */
function countQueriesFor(callable $request): int
{
    $count = 0;
    DB::listen(function () use (&$count): void {
        $count++;
    });

    $request();

    return $count;
}

it('renders the customer service list with a query count independent of row count', function (): void {
    $user = customerUser();

    $server = \App\Domains\Provisioning\Models\Server::query()->firstOrFail();
    $product = \App\Domains\Products\Models\Product::query()->firstOrFail();

    $make = fn () => Service::factory()->create([
        'customer_id' => $user->customer->id,
        'server_id'   => $server->id,
        'product_id'  => $product->id,
    ]);

    // Baseline with a handful of rows.
    $make();
    $make();
    $baseline = countQueriesFor(function () use ($user): void {
        $this->actingAs($user)->get(route('panel.services.index'))->assertOk();
    });

    // Ten times the rows.
    for ($i = 0; $i < 18; $i++) {
        $make();
    }
    $scaled = countQueriesFor(function () use ($user): void {
        $this->actingAs($user)->get(route('panel.services.index'))->assertOk();
    });

    // A couple of queries of slack for pagination counts; anything close to
    // +18 means a relation is being lazy-loaded per row.
    expect($scaled)->toBeLessThanOrEqual($baseline + 3);
});

it('renders the admin service list without a per-row query', function (): void {
    $admin = adminUser();

    $server  = \App\Domains\Provisioning\Models\Server::query()->firstOrFail();
    $product = \App\Domains\Products\Models\Product::query()->firstOrFail();

    $make = function () use ($server, $product): void {
        $customer = \App\Domains\Customer\Models\Customer::factory()->create();
        Service::factory()->create([
            'customer_id' => $customer->id,
            'server_id'   => $server->id,
            'product_id'  => $product->id,
        ]);
    };

    $make();
    $make();
    $baseline = countQueriesFor(function () use ($admin): void {
        $this->actingAs($admin)->get(route('admin.services.index'))->assertOk();
    });

    for ($i = 0; $i < 18; $i++) {
        $make();
    }
    $scaled = countQueriesFor(function () use ($admin): void {
        $this->actingAs($admin)->get(route('admin.services.index'))->assertOk();
    });

    // The list eager-loads customer, product, server and domainRegistration.
    expect($scaled)->toBeLessThanOrEqual($baseline + 3);
});

it('renders the invoice list without a per-row query', function (): void {
    $user = customerUser();

    $make = fn () => Invoice::factory()->create(['customer_id' => $user->customer->id]);

    $make();
    $make();
    $baseline = countQueriesFor(function () use ($user): void {
        $this->actingAs($user)->get(route('panel.billing.invoices'))->assertOk();
    });

    for ($i = 0; $i < 18; $i++) {
        $make();
    }
    $scaled = countQueriesFor(function () use ($user): void {
        $this->actingAs($user)->get(route('panel.billing.invoices'))->assertOk();
    });

    expect($scaled)->toBeLessThanOrEqual($baseline + 3);
});
