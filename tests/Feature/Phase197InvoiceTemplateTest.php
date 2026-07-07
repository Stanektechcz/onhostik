<?php

use App\Domains\Customer\Models\Customer;
use App\Models\InvoiceTemplate;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view invoice templates list', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.invoice-templates.index'))
        ->assertOk()
        ->assertViewHas('templates');
});

it('admin can create an invoice template', function (): void {
    $customer = Customer::factory()->create();

    $this->actingAs(adminUser())
        ->post(route('admin.invoice-templates.store'), [
            'customer_id' => $customer->id,
            'name'        => 'Měsíční hosting',
            'currency'    => 'CZK',
            'notes'       => 'Faktura za hosting',
            'line_items'  => [
                ['description' => 'Webhosting Basic', 'quantity' => 1, 'unit_price' => 29900],
            ],
        ])
        ->assertRedirect();

    expect(InvoiceTemplate::where('name', 'Měsíční hosting')->exists())->toBeTrue();
});

it('template requires at least one line item', function (): void {
    $customer = Customer::factory()->create();

    $this->actingAs(adminUser())
        ->post(route('admin.invoice-templates.store'), [
            'customer_id' => $customer->id,
            'name'        => 'Prázdná šablona',
            'currency'    => 'CZK',
            'line_items'  => [],
        ])
        ->assertSessionHasErrors('line_items');
});

it('admin can delete an invoice template', function (): void {
    $customer = Customer::factory()->create();
    $template = InvoiceTemplate::create([
        'customer_id' => $customer->id,
        'name'        => 'Ke smazání',
        'currency'    => 'CZK',
        'line_items'  => [['description' => 'Test', 'quantity' => 1, 'unit_price' => 100]],
    ]);

    $this->actingAs(adminUser())
        ->delete(route('admin.invoice-templates.destroy', $template))
        ->assertRedirect();

    expect(InvoiceTemplate::find($template->id))->toBeNull();
});

it('template currency must be 3 characters', function (): void {
    $customer = Customer::factory()->create();

    $this->actingAs(adminUser())
        ->post(route('admin.invoice-templates.store'), [
            'customer_id' => $customer->id,
            'name'        => 'Špatná měna',
            'currency'    => 'CZ',
            'line_items'  => [['description' => 'x', 'quantity' => 1, 'unit_price' => 100]],
        ])
        ->assertSessionHasErrors('currency');
});
