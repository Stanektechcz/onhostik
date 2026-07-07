<?php

declare(strict_types=1);

use App\Domains\Billing\Models\Invoice;

it('admin can view proforma batch index', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.proforma-batch.index'))
         ->assertOk()
         ->assertViewIs('admin.proforma-batch');
});

it('admin can convert proforma to invoice', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    $proforma = Invoice::factory()->create([
        'customer_id' => $customer->customer->id,
        'type'        => 'proforma',
        'status'      => 'sent',
    ]);

    $this->actingAs($admin)
         ->patch(route('admin.proforma-batch.convert', $proforma))
         ->assertRedirect();

    expect($proforma->fresh()->type->value)->toBe('invoice');
});

it('converting non-proforma invoice returns 422', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    $invoice = Invoice::factory()->create([
        'customer_id' => $customer->customer->id,
        'type'        => 'invoice',
        'status'      => 'sent',
    ]);

    $this->actingAs($admin)
         ->patch(route('admin.proforma-batch.convert', $invoice))
         ->assertStatus(422);
});

it('proforma batch index shows only proformas', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    Invoice::factory()->create([
        'customer_id' => $customer->customer->id,
        'type'        => 'proforma',
        'status'      => 'sent',
    ]);

    $response = $this->actingAs($admin)
         ->get(route('admin.proforma-batch.index'))
         ->assertOk();

    $proformas = $response->viewData('proformas');
    expect($proformas)->each(fn ($item) => $item->type->value->toBe('proforma'));
});

it('customer cannot access proforma batch', function (): void {
    $customer = customerUser();

    $this->actingAs($customer)
         ->get(route('admin.proforma-batch.index'))
         ->assertForbidden();
});
