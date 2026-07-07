<?php

use App\Models\DunningConfig;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view dunning configs', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.dunning-configs.index'))
        ->assertOk()
        ->assertViewHas('configs');
});

it('admin can create a dunning step', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.dunning-configs.store'), [
            'name'           => 'First reminder',
            'step'           => 1,
            'days_after_due' => 3,
            'action'         => 'email',
            'email_template' => 'invoice.reminder',
            'is_active'      => true,
        ])
        ->assertRedirect();

    expect(DunningConfig::where('step', 1)->where('action', 'email')->exists())->toBeTrue();
});

it('action must be one of allowed values', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.dunning-configs.store'), [
            'name'           => 'Bad',
            'step'           => 1,
            'days_after_due' => 3,
            'action'         => 'delete',
        ])
        ->assertSessionHasErrors('action');
});

it('admin can update a dunning config', function (): void {
    $config = DunningConfig::create([
        'name'           => 'Old name',
        'step'           => 2,
        'days_after_due' => 7,
        'action'         => 'email',
        'is_active'      => true,
    ]);

    $this->actingAs(adminUser())
        ->patch(route('admin.dunning-configs.update', $config), [
            'name'           => 'Updated',
            'days_after_due' => 10,
            'action'         => 'email',
            'is_active'      => true,
        ])
        ->assertRedirect();

    expect($config->fresh()->name)->toBe('Updated');
    expect($config->fresh()->days_after_due)->toBe(10);
});

it('admin can delete a dunning config', function (): void {
    $config = DunningConfig::create([
        'name'           => 'Delete me',
        'step'           => 3,
        'days_after_due' => 14,
        'action'         => 'suspend',
        'is_active'      => true,
    ]);

    $this->actingAs(adminUser())
        ->delete(route('admin.dunning-configs.destroy', $config))
        ->assertRedirect();

    expect(DunningConfig::find($config->id))->toBeNull();
});
