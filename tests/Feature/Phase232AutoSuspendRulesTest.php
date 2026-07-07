<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view auto suspend rules', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.auto-suspend-rules.index'))
        ->assertOk()
        ->assertViewHas('rules');
});

it('admin can create an auto suspend rule', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.auto-suspend-rules.store'), [
            'name'            => 'Overdue 30 days',
            'trigger'         => 'overdue_days',
            'threshold_value' => 30,
            'is_active'       => 1,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('auto_suspend_rules', ['name' => 'Overdue 30 days']);
});

it('admin can toggle rule active status', function (): void {
    $rule = \App\Models\AutoSuspendRule::create([
        'name'            => 'Test',
        'trigger'         => 'overdue_days',
        'threshold_value' => 30,
        'is_active'       => false,
        'description'     => '',
    ]);

    $this->actingAs(adminUser())
        ->patch(route('admin.auto-suspend-rules.update', $rule), [
            'name'            => 'Test',
            'trigger'         => 'overdue_days',
            'threshold_value' => 30,
            'is_active'       => 1,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('auto_suspend_rules', ['is_active' => true]);
});

it('guest cannot access auto suspend rules', function (): void {
    $this->get(route('admin.auto-suspend-rules.index'))
        ->assertRedirect();
});

it('store validates trigger enum', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.auto-suspend-rules.store'), [
            'name'            => 'Bad Rule',
            'trigger'         => 'invalid_trigger',
            'threshold_value' => 30,
            'is_active'       => 1,
        ])
        ->assertSessionHasErrors('trigger');
});
