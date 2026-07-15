<?php

declare(strict_types=1);

use App\Models\CustomerCommunicationLog;
use App\Models\CustomerOnboardingStep;

// ── Customer 360° detail sections ─────────────────────────────────────────────

it('admin customer detail shows unified 360 sections', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;

    $this->actingAs($admin)
        ->get(route('admin.customers.show', $customer))
        ->assertOk()
        ->assertSee('Onboarding')
        ->assertSee('Komunikace se zákazníkem')
        ->assertSee('Zdraví');
});

it('customer detail shows live-computed health score without stored column', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;
    $customer->update(['health_score' => null]);

    $this->actingAs($admin)
        ->get(route('admin.customers.show', $customer))
        ->assertOk()
        ->assertSee('/100');
});

it('customer detail lists communication logs inline', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;

    CustomerCommunicationLog::create([
        'customer_id'   => $customer->id,
        'admin_user_id' => $admin->id,
        'channel'       => 'phone',
        'direction'     => 'outbound',
        'subject'       => 'Upsell hovor o VPS',
        'body'          => 'Zákazník zvažuje upgrade na VPS Pro.',
        'created_by'    => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.customers.show', $customer))
        ->assertOk()
        ->assertSee('Upsell hovor o VPS');
});

it('customer detail shows onboarding progress with steps', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;

    CustomerOnboardingStep::create([
        'customer_id' => $customer->id,
        'step'        => 'Ověření e-mailu 360',
        'is_required' => true,
    ]);
    CustomerOnboardingStep::create([
        'customer_id'  => $customer->id,
        'step'         => 'První objednávka 360',
        'is_required'  => false,
        'completed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.customers.show', $customer))
        ->assertOk()
        ->assertSee('Ověření e-mailu 360')
        ->assertSee('První objednávka 360')
        ->assertSee('1/2');
});

it('communication log can be created from customer detail', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;

    $this->actingAs($admin)
        ->from(route('admin.customers.show', $customer))
        ->post(route('admin.customer-communication-logs.store'), [
            'customer_id' => $customer->id,
            'channel'     => 'email',
            'direction'   => 'outbound',
            'subject'     => 'Vítejte v OnHost',
            'body'        => 'Uvítací e-mail odeslán ručně.',
        ])
        ->assertRedirect(route('admin.customers.show', $customer));

    expect(CustomerCommunicationLog::where('customer_id', $customer->id)->where('subject', 'Vítejte v OnHost')->exists())
        ->toBeTrue();
});

it('onboarding step can be toggled from customer detail', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;

    $step = CustomerOnboardingStep::create([
        'customer_id' => $customer->id,
        'step'        => 'Nastavení DNS',
        'is_required' => true,
    ]);

    $this->actingAs($admin)
        ->from(route('admin.customers.show', $customer))
        ->patch(route('admin.customer-onboarding-steps.update', $step))
        ->assertRedirect(route('admin.customers.show', $customer));

    expect($step->fresh()->completed_at)->not->toBeNull();
});
