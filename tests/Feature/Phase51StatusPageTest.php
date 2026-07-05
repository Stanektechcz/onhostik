<?php

declare(strict_types=1);

use App\Domains\Monitoring\Models\Monitor;
use App\Domains\Monitoring\Models\StatusPageComponent;
use App\Domains\Monitoring\Models\StatusPageMaintenance;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── Model helpers ─────────────────────────────────────────────────────────────

it('StatusPageMaintenance statusLabel returns correct strings', function (): void {
    $maint = new StatusPageMaintenance(['status' => 'scheduled']);
    expect($maint->statusLabel())->toBe('Naplánováno');

    $maint->status = 'in_progress';
    expect($maint->statusLabel())->toBe('Probíhá');

    $maint->status = 'completed';
    expect($maint->statusLabel())->toBe('Dokončeno');
});

it('StatusPageMaintenance isUpcoming returns true for future scheduled', function (): void {
    $maint = new StatusPageMaintenance([
        'status'              => 'scheduled',
        'scheduled_start_at'  => now()->addDay(),
        'scheduled_end_at'    => now()->addDays(2),
    ]);
    expect($maint->isUpcoming())->toBeTrue();
});

it('StatusPageComponent currentStatus returns unknown when no monitor', function (): void {
    $comp = new StatusPageComponent(['name' => 'Test']);
    expect($comp->currentStatus())->toBe('unknown');
});

// ── Public status page ────────────────────────────────────────────────────────

it('public status page is accessible without auth', function (): void {
    $this->get(route('front.status'))
        ->assertOk()
        ->assertViewIs('front.status');
});

it('status page passes components and maintenances to view', function (): void {
    StatusPageComponent::create([
        'name'       => 'API Server',
        'sort_order' => 1,
        'is_visible' => true,
    ]);

    StatusPageMaintenance::create([
        'title'              => 'Pravidelná údržba',
        'status'             => 'scheduled',
        'scheduled_start_at' => now()->addDay(),
        'scheduled_end_at'   => now()->addDays(2),
    ]);

    $this->get(route('front.status'))
        ->assertOk()
        ->assertViewHas('components')
        ->assertViewHas('maintenances');
});

it('hidden components do not appear on status page', function (): void {
    StatusPageComponent::create([
        'name'       => 'Hidden Service',
        'sort_order' => 1,
        'is_visible' => false,
    ]);

    $response = $this->get(route('front.status'));
    $response->assertOk();

    $components = $response->viewData('components');
    expect($components->where('name', 'Hidden Service')->count())->toBe(0);
});

// ── Admin status page management ──────────────────────────────────────────────

it('admin can view status page management', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.status-page.index'))
        ->assertOk()
        ->assertViewIs('admin.status-page.index');
});

it('admin can create a status page component', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.status-page.components.store'), [
            'name'       => 'Web Server',
            'sort_order' => 0,
            'is_visible' => 1,
        ])
        ->assertRedirect();

    expect(StatusPageComponent::where('name', 'Web Server')->exists())->toBeTrue();
});

it('admin can link a component to a monitor', function (): void {
    $admin   = adminUser();
    $monitor = Monitor::factory()->create(['name' => 'main-site', 'is_active' => true]);

    $this->actingAs($admin)
        ->post(route('admin.status-page.components.store'), [
            'name'       => 'Website',
            'monitor_id' => $monitor->id,
            'sort_order' => 0,
            'is_visible' => 1,
        ])
        ->assertRedirect();

    $comp = StatusPageComponent::where('name', 'Website')->first();
    expect($comp)->not->toBeNull();
    expect($comp->monitor_id)->toBe($monitor->id);
});

it('admin can delete a status page component', function (): void {
    $admin = adminUser();
    $comp  = StatusPageComponent::create(['name' => 'To delete', 'sort_order' => 0, 'is_visible' => true]);

    $this->actingAs($admin)
        ->delete(route('admin.status-page.components.destroy', $comp))
        ->assertRedirect();

    expect(StatusPageComponent::find($comp->id))->toBeNull();
});

it('admin can create a maintenance window', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.status-page.maintenances.store'), [
            'title'              => 'DB upgrade',
            'status'             => 'scheduled',
            'scheduled_start_at' => now()->addHour()->format('Y-m-d\TH:i'),
            'scheduled_end_at'   => now()->addHours(3)->format('Y-m-d\TH:i'),
        ])
        ->assertRedirect();

    expect(StatusPageMaintenance::where('title', 'DB upgrade')->exists())->toBeTrue();
});

it('maintenance end must be after start', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.status-page.maintenances.store'), [
            'title'              => 'Bad window',
            'status'             => 'scheduled',
            'scheduled_start_at' => now()->addHours(3)->format('Y-m-d\TH:i'),
            'scheduled_end_at'   => now()->addHour()->format('Y-m-d\TH:i'),
        ])
        ->assertSessionHasErrors('scheduled_end_at');
});

it('admin can delete a maintenance window', function (): void {
    $admin = adminUser();
    $maint = StatusPageMaintenance::create([
        'title'              => 'To remove',
        'status'             => 'scheduled',
        'scheduled_start_at' => now()->addDay(),
        'scheduled_end_at'   => now()->addDays(2),
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.status-page.maintenances.destroy', $maint))
        ->assertRedirect();

    expect(StatusPageMaintenance::find($maint->id))->toBeNull();
});

it('admin can update maintenance status to in_progress', function (): void {
    $admin = adminUser();
    $maint = StatusPageMaintenance::create([
        'title'              => 'Ongoing work',
        'status'             => 'scheduled',
        'scheduled_start_at' => now()->subHour(),
        'scheduled_end_at'   => now()->addHour(),
    ]);

    $this->actingAs($admin)
        ->put(route('admin.status-page.maintenances.update', $maint), [
            'title'              => 'Ongoing work',
            'status'             => 'in_progress',
            'scheduled_start_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'scheduled_end_at'   => now()->addHour()->format('Y-m-d\TH:i'),
        ])
        ->assertRedirect();

    expect($maint->fresh()->status)->toBe('in_progress');
});
