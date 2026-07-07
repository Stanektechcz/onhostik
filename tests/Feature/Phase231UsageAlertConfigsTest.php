<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('panel user can view usage alert configurations', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.usage-alert-configs.index'))
        ->assertOk()
        ->assertViewHas('alerts');
});

it('panel user can create a usage alert', function (): void {
    $user    = customerUser();
    $service = \App\Domains\Provisioning\Models\Service::factory()->create(['customer_id' => $user->customer->id]);

    $this->actingAs($user)
        ->post(route('panel.usage-alert-configs.store'), [
            'service_id'        => $service->id,
            'metric'            => 'disk',
            'threshold_percent' => 80,
        ])
        ->assertRedirect();

    expect(\App\Models\UsageAlertConfig::count())->toBe(1);
});

it('panel user can delete their usage alert', function (): void {
    $user    = customerUser();
    $service = \App\Domains\Provisioning\Models\Service::factory()->create(['customer_id' => $user->customer->id]);
    $alert   = \App\Models\UsageAlertConfig::create([
        'service_id'        => $service->id,
        'user_id'           => $user->id,
        'metric'            => 'disk',
        'threshold_percent' => 80,
        'is_active'         => true,
    ]);

    $this->actingAs($user)
        ->delete(route('panel.usage-alert-configs.destroy', $alert))
        ->assertRedirect();

    $this->assertDatabaseMissing('usage_alert_configs', ['id' => $alert->id]);
});

it('panel user cannot delete another user\'s alert', function (): void {
    $user    = customerUser();
    $other   = \App\Models\User::factory()->create();
    $service = \App\Domains\Provisioning\Models\Service::factory()->create();
    $alert   = \App\Models\UsageAlertConfig::create([
        'service_id'        => $service->id,
        'user_id'           => $other->id,
        'metric'            => 'disk',
        'threshold_percent' => 80,
        'is_active'         => true,
    ]);

    $this->actingAs($user)
        ->delete(route('panel.usage-alert-configs.destroy', $alert))
        ->assertForbidden();
});

it('guest cannot access usage alerts', function (): void {
    $this->get(route('panel.usage-alert-configs.index'))
        ->assertRedirect();
});
