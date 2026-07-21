<?php

declare(strict_types=1);

use Database\Seeders\IntegrationSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Admin queue health page (audit E71).
 *
 * Provisioning rides the queue, so a stalled worker silently strands paid
 * orders. Operators had no way to see that without shell access.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, IntegrationSeeder::class]);
});

function seedFailedJob(string $uuid = null): string
{
    $uuid ??= (string) Str::uuid();

    DB::table('failed_jobs')->insert([
        'uuid'       => $uuid,
        'connection' => 'database',
        'queue'      => 'provisioning',
        'payload'    => json_encode(['displayName' => 'App\\Domains\\Provisioning\\Jobs\\ProvisionHostingServiceJob']),
        'exception'  => 'RuntimeException: aaPanel unreachable',
        'failed_at'  => now(),
    ]);

    return $uuid;
}

it('renders the queue overview for an admin', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.queue.index'))
        ->assertOk()
        ->assertSee('Čeká ve frontě')
        ->assertSee('Neúspěšné');
});

it('forbids a customer from the queue overview', function (): void {
    $this->actingAs(customerUser())
        ->get(route('admin.queue.index'))
        ->assertForbidden();
});

it('lists a failed job with its error', function (): void {
    seedFailedJob();

    $this->actingAs(adminUser())
        ->get(route('admin.queue.index'))
        ->assertOk()
        ->assertSee('ProvisionHostingServiceJob')
        ->assertSee('aaPanel unreachable');
});

it('counts pending jobs and flags long-reserved ones as stuck', function (): void {
    DB::table('jobs')->insert([
        'queue'        => 'provisioning',
        'payload'      => '{}',
        'attempts'     => 1,
        // Reserved 30 minutes ago → the worker that took it is gone.
        'reserved_at'  => now()->subMinutes(30)->timestamp,
        'available_at' => now()->timestamp,
        'created_at'   => now()->subMinutes(31)->timestamp,
    ]);

    $this->actingAs(adminUser())
        ->get(route('admin.queue.index'))
        ->assertOk()
        ->assertSee('Zaseknuté')
        ->assertSee('pravděpodobně spadl worker');
});

it('lets an admin delete a single failed job', function (): void {
    $uuid = seedFailedJob();

    $this->actingAs(adminUser())
        ->post(route('admin.queue.forget'), ['uuid' => $uuid])
        ->assertRedirect();

    expect(DB::table('failed_jobs')->where('uuid', $uuid)->exists())->toBeFalse();
});

it('lets an admin flush every failed job', function (): void {
    seedFailedJob();
    seedFailedJob();

    $this->actingAs(adminUser())
        ->post(route('admin.queue.forget'))
        ->assertRedirect();

    expect(DB::table('failed_jobs')->count())->toBe(0);
});

it('forbids a customer from retrying or deleting jobs', function (): void {
    $uuid = seedFailedJob();

    $this->actingAs(customerUser())->post(route('admin.queue.retry'), ['uuid' => $uuid])->assertForbidden();
    $this->actingAs(customerUser())->post(route('admin.queue.forget'), ['uuid' => $uuid])->assertForbidden();

    expect(DB::table('failed_jobs')->count())->toBe(1);
});

it('records queue maintenance in the audit log', function (): void {
    $uuid = seedFailedJob();

    $this->actingAs(adminUser())->post(route('admin.queue.forget'), ['uuid' => $uuid]);

    expect(\Spatie\Activitylog\Models\Activity::where('description', 'queue.job_forgotten')->exists())->toBeTrue();
});
