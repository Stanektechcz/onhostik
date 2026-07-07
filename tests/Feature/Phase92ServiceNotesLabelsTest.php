<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Models\ServiceLabel;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── ServiceLabel model ─────────────────────────────────────────────────────────

it('ServiceLabel COLORS contains expected values', function (): void {
    expect(ServiceLabel::COLORS)
        ->toContain('primary')
        ->toContain('danger')
        ->toContain('success')
        ->toContain('warning');
});

it('can create a service label', function (): void {
    $label = ServiceLabel::create(['name' => 'VIP zákazník', 'color' => 'primary']);

    expect($label->id)->toBeGreaterThan(0)
        ->and($label->name)->toBe('VIP zákazník')
        ->and($label->color)->toBe('primary');
});

it('Service has admin_note fillable column', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => ServiceStatus::Active,
        'admin_note'  => 'Interní poznámka.',
    ]);

    expect($service->admin_note)->toBe('Interní poznámka.');
});

it('Service has labels BelongsToMany relationship', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);
    $label   = ServiceLabel::create(['name' => 'Testovací', 'color' => 'info']);

    $service->labels()->attach($label->id);

    expect($service->labels()->count())->toBe(1)
        ->and($service->labels->first()->name)->toBe('Testovací');
});

// ── Admin service notes edit page ──────────────────────────────────────────────

it('admin can view service notes edit page', function (): void {
    $admin   = adminUser();
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    $this->actingAs($admin)
        ->get(route('admin.service-notes.edit', $service))
        ->assertOk()
        ->assertSee('Poznámky a štítky');
});

it('customer cannot access service notes admin page', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    $this->actingAs($user)
        ->get(route('admin.service-notes.edit', $service))
        ->assertStatus(403);
});

it('admin can update admin_note on service', function (): void {
    $admin   = adminUser();
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    $this->actingAs($admin)
        ->put(route('admin.service-notes.update', $service), [
            'admin_note' => 'Zkontrolovat billing v pondělí.',
        ])
        ->assertRedirect(route('admin.services.show', $service));

    expect($service->fresh()->admin_note)->toBe('Zkontrolovat billing v pondělí.');
});

it('admin can attach labels to service', function (): void {
    $admin   = adminUser();
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);
    $label   = ServiceLabel::create(['name' => 'Prioritní', 'color' => 'danger']);

    $this->actingAs($admin)
        ->put(route('admin.service-notes.update', $service), [
            'labels' => [$label->id],
        ])
        ->assertRedirect();

    expect($service->labels()->count())->toBe(1)
        ->and($service->labels->first()->id)->toBe($label->id);
});

it('admin can remove all labels by submitting empty labels', function (): void {
    $admin   = adminUser();
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);
    $label   = ServiceLabel::create(['name' => 'Dočasný', 'color' => 'warning']);
    $service->labels()->attach($label->id);

    $this->actingAs($admin)
        ->put(route('admin.service-notes.update', $service), [])
        ->assertRedirect();

    expect($service->labels()->count())->toBe(0);
});

// ── Label CRUD ─────────────────────────────────────────────────────────────────

it('admin can view labels index page', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.service-labels.index'))
        ->assertOk()
        ->assertSee('Štítky');
});

it('admin can create a new label', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.service-labels.store'), [
            'name'  => 'Nový štítek',
            'color' => 'success',
        ])
        ->assertRedirect(route('admin.service-labels.index'));

    expect(ServiceLabel::where('name', 'Nový štítek')->exists())->toBeTrue();
});

it('store validates required name and color', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.service-labels.store'), [])
        ->assertSessionHasErrors(['name', 'color']);
});

it('store rejects invalid color value', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.service-labels.store'), [
            'name'  => 'Test',
            'color' => 'invalid_color',
        ])
        ->assertSessionHasErrors('color');
});

it('admin can delete a label', function (): void {
    $admin = adminUser();
    $label = ServiceLabel::create(['name' => 'Smazat', 'color' => 'secondary']);

    $this->actingAs($admin)
        ->delete(route('admin.service-labels.destroy', $label))
        ->assertRedirect(route('admin.service-labels.index'));

    expect(ServiceLabel::find($label->id))->toBeNull();
});
