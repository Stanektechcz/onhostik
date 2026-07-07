<?php

use App\Models\InvoiceReminderRule;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view invoice reminder rules page', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.invoice-reminder-rules.index'))
        ->assertOk()
        ->assertViewHas('rules');
});

it('admin can create a new reminder rule', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.invoice-reminder-rules.store'), [
            'days_after_due' => 7,
            'channel'        => 'mail',
            'template_key'   => 'invoice.overdue',
        ])
        ->assertRedirect();

    expect(InvoiceReminderRule::where('days_after_due', 7)->where('channel', 'mail')->exists())->toBeTrue();
});

it('store is idempotent — updates existing rule for same days+channel', function (): void {
    InvoiceReminderRule::create([
        'days_after_due' => 14,
        'channel'        => 'mail',
        'template_key'   => 'old.template',
        'is_active'      => true,
    ]);

    $this->actingAs(adminUser())
        ->post(route('admin.invoice-reminder-rules.store'), [
            'days_after_due' => 14,
            'channel'        => 'mail',
            'template_key'   => 'invoice.overdue_v2',
        ])
        ->assertRedirect();

    expect(InvoiceReminderRule::where('days_after_due', 14)->where('channel', 'mail')->count())->toBe(1);
    expect(InvoiceReminderRule::where('days_after_due', 14)->value('template_key'))->toBe('invoice.overdue_v2');
});

it('admin can deactivate a reminder rule', function (): void {
    $rule = InvoiceReminderRule::create([
        'days_after_due' => 30,
        'channel'        => 'mail',
        'template_key'   => 'invoice.final',
        'is_active'      => true,
    ]);

    $this->actingAs(adminUser())
        ->patch(route('admin.invoice-reminder-rules.update', $rule), [
            'is_active'    => false,
            'template_key' => 'invoice.final',
        ])
        ->assertRedirect();

    expect($rule->fresh()->is_active)->toBeFalse();
});

it('days_after_due must not exceed 90', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.invoice-reminder-rules.store'), [
            'days_after_due' => 91,
            'channel'        => 'mail',
            'template_key'   => 'invoice.overdue',
        ])
        ->assertSessionHasErrors('days_after_due');
});
