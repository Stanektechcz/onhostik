<?php

declare(strict_types=1);

use App\Domains\Backups\Models\BackupPolicy;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Model / migration ─────────────────────────────────────────────────────────

it('BackupPolicy stores scheduled_hour, scheduled_weekday and notify_on_failure', function (): void {
    $user   = customerUser();
    $result = placeOrder($user);
    $order  = $result['order'];

    $service = Service::factory()->create([
        'customer_id'   => $user->customer->id,
        'order_item_id' => $order->items->first()->id,
    ]);

    $policy = BackupPolicy::create([
        'service_id'        => $service->id,
        'frequency'         => 'weekly',
        'scheduled_hour'    => 5,
        'scheduled_weekday' => 2, // Wednesday
        'retention_days'    => 30,
        'notify_on_failure' => true,
        'is_active'         => true,
    ]);

    $policy->refresh();

    expect($policy->scheduled_hour)->toBe(5)
        ->and($policy->scheduled_weekday)->toBe(2)
        ->and($policy->notify_on_failure)->toBeTrue()
        ->and($policy->retention_days)->toBe(30);
});

it('BackupPolicy frequencyLabel returns Czech labels', function (): void {
    $user   = customerUser();
    $result = placeOrder($user);
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    $makePolicy = fn (string $freq) => new BackupPolicy(['service_id' => $service->id, 'frequency' => $freq]);

    expect($makePolicy('daily')->frequencyLabel())->toBe('Denně')
        ->and($makePolicy('weekly')->frequencyLabel())->toBe('Týdně')
        ->and($makePolicy('monthly')->frequencyLabel())->toBe('Měsíčně');
});

it('BackupPolicy weekdayLabel returns Czech day names', function (): void {
    $policy = new BackupPolicy();
    $days   = [0 => 'Pondělí', 1 => 'Úterý', 2 => 'Středa', 3 => 'Čtvrtek', 4 => 'Pátek', 5 => 'Sobota', 6 => 'Neděle'];

    foreach ($days as $num => $label) {
        $policy->scheduled_weekday = $num;
        expect($policy->weekdayLabel())->toBe($label);
    }
});

// ── Panel: updateBackupSchedule ───────────────────────────────────────────────

it('customer can update backup schedule via panel', function (): void {
    $user   = customerUser();
    $result = placeOrder($user);
    $order  = $result['order'];

    $service = Service::factory()->create([
        'customer_id'   => $user->customer->id,
        'order_item_id' => $order->items->first()->id,
    ]);

    $this->actingAs($user)
        ->put(route('panel.services.backup-schedule', $service), [
            'frequency'         => 'weekly',
            'scheduled_hour'    => 4,
            'scheduled_weekday' => 3,
            'retention_days'    => 21,
            'is_active'         => '1',
            'notify_on_failure' => '1',
        ])
        ->assertRedirect();

    $policy = BackupPolicy::where('service_id', $service->id)->first();

    expect($policy)->not->toBeNull()
        ->and($policy->frequency)->toBe('weekly')
        ->and($policy->scheduled_hour)->toBe(4)
        ->and($policy->scheduled_weekday)->toBe(3)
        ->and($policy->retention_days)->toBe(21)
        ->and($policy->notify_on_failure)->toBeTrue();
});

it('updateBackupSchedule validates inputs', function (): void {
    $user   = customerUser();
    $result = placeOrder($user);
    $service = Service::factory()->create([
        'customer_id'   => $user->customer->id,
        'order_item_id' => $result['order']->items->first()->id,
    ]);

    $this->actingAs($user)
        ->put(route('panel.services.backup-schedule', $service), [
            'frequency'      => 'hourly', // invalid
            'scheduled_hour' => 99,       // invalid
            'retention_days' => 0,        // invalid
        ])
        ->assertSessionHasErrors(['frequency', 'scheduled_hour', 'retention_days']);
});

it('updateBackupSchedule updates existing policy without creating duplicate', function (): void {
    $user   = customerUser();
    $result = placeOrder($user);
    $service = Service::factory()->create([
        'customer_id'   => $user->customer->id,
        'order_item_id' => $result['order']->items->first()->id,
    ]);

    // Create initial policy
    BackupPolicy::create([
        'service_id'     => $service->id,
        'frequency'      => 'daily',
        'scheduled_hour' => 3,
        'retention_days' => 14,
    ]);

    $this->actingAs($user)
        ->put(route('panel.services.backup-schedule', $service), [
            'frequency'      => 'monthly',
            'scheduled_hour' => 2,
            'retention_days' => 90,
        ])
        ->assertRedirect();

    expect(BackupPolicy::where('service_id', $service->id)->count())->toBe(1)
        ->and(BackupPolicy::where('service_id', $service->id)->value('frequency'))->toBe('monthly');
});

// ── Admin service show ────────────────────────────────────────────────────────

it('admin service show displays backup schedule', function (): void {
    $admin  = adminUser();
    $user   = customerUser();
    $result = placeOrder($user);
    $service = Service::factory()->create([
        'customer_id'   => $user->customer->id,
        'order_item_id' => $result['order']->items->first()->id,
    ]);

    BackupPolicy::create([
        'service_id'        => $service->id,
        'frequency'         => 'daily',
        'scheduled_hour'    => 3,
        'retention_days'    => 14,
        'notify_on_failure' => true,
        'is_active'         => true,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.services.show', $service))
        ->assertOk()
        ->assertSee('Plán zálohování')
        ->assertSee('Denně')
        ->assertSee('03:00 UTC');
});

it('admin service show shows nothing when no backup policy exists', function (): void {
    $admin  = adminUser();
    $user   = customerUser();
    $result = placeOrder($user);
    $service = Service::factory()->create([
        'customer_id'   => $user->customer->id,
        'order_item_id' => $result['order']->items->first()->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.services.show', $service))
        ->assertOk()
        ->assertDontSee('Plán zálohování');
});
