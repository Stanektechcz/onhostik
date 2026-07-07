<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Models\ServiceResourceUsage;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Access control ─────────────────────────────────────────────────────────────

it('customer can export usage for their own service', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $service = Service::factory()->create([
        'customer_id' => $customer->id,
        'status'      => ServiceStatus::Active,
    ]);

    $this->actingAs($user)
        ->get(route('panel.services.usage-export', $service))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

it('customer cannot export another customers service usage', function (): void {
    $owner  = customerUser();
    $other  = customerUser();

    $service = Service::factory()->create([
        'customer_id' => $owner->customer->id,
        'status'      => ServiceStatus::Active,
    ]);

    $this->actingAs($other)
        ->get(route('panel.services.usage-export', $service))
        ->assertForbidden();
});

it('unauthenticated users cannot access usage export', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    $this->get(route('panel.services.usage-export', $service))
        ->assertRedirect();
});

it('admin can access any service usage export', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    $this->actingAs(adminUser())
        ->get(route('panel.services.usage-export', $service))
        ->assertOk();
});

// ── CSV content ────────────────────────────────────────────────────────────────

it('CSV contains header row', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    $response = $this->actingAs($user)
        ->get(route('panel.services.usage-export', $service));

    $content = $response->streamedContent();
    expect($content)->toContain('Datum a čas')
        ->toContain('CPU')
        ->toContain('RAM')
        ->toContain('Disk');
});

it('CSV contains usage rows', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    DB::table('service_resource_usages')->insert([
        'service_id'   => $service->id,
        'cpu_percent'  => 45,
        'ram_mb'       => 512,
        'disk_gb'      => 10,
        'bandwidth_gb' => 2,
        'recorded_at'  => now()->subHour()->toDateTimeString(),
        'created_at'   => now()->toDateTimeString(),
        'updated_at'   => now()->toDateTimeString(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('panel.services.usage-export', $service));

    $content = $response->streamedContent();
    expect($content)->toContain('45')
        ->toContain('512')
        ->toContain('10');
});

it('CSV is empty body (header only) when no usage records', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    $response = $this->actingAs($user)
        ->get(route('panel.services.usage-export', $service));

    $content = $response->streamedContent();
    $lines   = array_filter(explode("\n", trim($content)));
    expect(count($lines))->toBe(1); // only header
});

// ── Date filtering ─────────────────────────────────────────────────────────────

it('from filter excludes older records', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    DB::table('service_resource_usages')->insert([
        'service_id'  => $service->id,
        'cpu_percent' => 10,
        'recorded_at' => now()->subDays(10)->toDateTimeString(),
        'created_at'  => now()->toDateTimeString(),
        'updated_at'  => now()->toDateTimeString(),
    ]);

    DB::table('service_resource_usages')->insert([
        'service_id'  => $service->id,
        'cpu_percent' => 99,
        'recorded_at' => now()->toDateTimeString(),
        'created_at'  => now()->toDateTimeString(),
        'updated_at'  => now()->toDateTimeString(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('panel.services.usage-export', $service) . '?from=' . now()->subDay()->toDateString());

    $content = $response->streamedContent();
    expect($content)->toContain('99')
        ->not->toContain('"10"');
});

it('to filter excludes newer records', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    DB::table('service_resource_usages')->insert([
        'service_id'  => $service->id,
        'cpu_percent' => 55,
        'recorded_at' => now()->subDays(5)->toDateTimeString(),
        'created_at'  => now()->toDateTimeString(),
        'updated_at'  => now()->toDateTimeString(),
    ]);

    DB::table('service_resource_usages')->insert([
        'service_id'  => $service->id,
        'cpu_percent' => 77,
        'recorded_at' => now()->toDateTimeString(),
        'created_at'  => now()->toDateTimeString(),
        'updated_at'  => now()->toDateTimeString(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('panel.services.usage-export', $service) . '?to=' . now()->subDays(3)->toDateString());

    $content = $response->streamedContent();
    expect($content)->toContain('55')
        ->not->toContain('"77"');
});

it('filename contains service id and today date', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    $response = $this->actingAs($user)
        ->get(route('panel.services.usage-export', $service));

    $disposition = $response->headers->get('Content-Disposition') ?? '';
    expect($disposition)->toContain('pouziti-' . $service->id);
    expect($disposition)->toContain(now()->format('Ymd'));
});
