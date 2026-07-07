<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view tax rate applications log', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.tax-rate-applications.index'))
        ->assertOk()
        ->assertViewHas('applications');
});

it('tax rate applications page shows all records', function (): void {
    $taxRate = \App\Models\TaxRate::create([
        'country_code'   => 'CZ',
        'name'           => 'VAT 21%',
        'rate_percent'   => 21.00,
        'type'           => 'standard',
        'is_active'      => true,
        'effective_from' => now()->toDateString(),
    ]);
    $invoice = \App\Domains\Billing\Models\Invoice::factory()->create();

    \App\Models\TaxRateApplication::create([
        'tax_rate_id'  => $taxRate->id,
        'invoice_id'   => $invoice->id,
        'customer_id'  => $invoice->customer_id,
        'rate_applied' => 21.00,
        'tax_amount'   => 2100,
        'currency'     => 'CZK',
    ]);

    $response = $this->actingAs(adminUser())
        ->get(route('admin.tax-rate-applications.index'))
        ->assertOk()
        ->assertViewHas('applications');

    expect($response->viewData('applications')->total())->toBeGreaterThanOrEqual(1);
});

it('admin can filter applications by invoice id', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.tax-rate-applications.index').'?invoice_id=1')
        ->assertOk();
});

it('guest cannot access tax rate applications', function (): void {
    $this->get(route('admin.tax-rate-applications.index'))
        ->assertRedirect();
});

it('tax rate applications view contains rate_applied column', function (): void {
    $taxRate = \App\Models\TaxRate::create([
        'country_code'   => 'CZ',
        'name'           => 'VAT 21%',
        'rate_percent'   => 21.00,
        'type'           => 'standard',
        'is_active'      => true,
        'effective_from' => now()->toDateString(),
    ]);
    $invoice = \App\Domains\Billing\Models\Invoice::factory()->create();

    \App\Models\TaxRateApplication::create([
        'tax_rate_id'  => $taxRate->id,
        'invoice_id'   => $invoice->id,
        'customer_id'  => $invoice->customer_id,
        'rate_applied' => 21.00,
        'tax_amount'   => 2100,
        'currency'     => 'CZK',
    ]);

    $this->actingAs(adminUser())
        ->get(route('admin.tax-rate-applications.index'))
        ->assertOk()
        ->assertSee('21');
});
