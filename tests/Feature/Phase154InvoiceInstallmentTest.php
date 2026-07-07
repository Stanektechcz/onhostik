<?php

declare(strict_types=1);

use App\Domains\Billing\Models\Invoice;
use App\Models\InvoiceInstallment;

it('customer can create installment plan for sent invoice', function (): void {
    $customer = customerUser();

    $invoice = Invoice::factory()->create([
        'customer_id' => $customer->customer->id,
        'status'      => 'sent',
    ]);

    $this->actingAs($customer)
         ->post(route('panel.billing.invoices.installment', $invoice), ['installment_count' => 3])
         ->assertRedirect();

    expect(InvoiceInstallment::where('invoice_id', $invoice->id)->count())->toBe(3);
});

it('installment amounts sum to invoice total', function (): void {
    $customer = customerUser();

    $invoice = Invoice::factory()->create([
        'customer_id' => $customer->customer->id,
        'status'      => 'sent',
    ]);

    $this->actingAs($customer)
         ->post(route('panel.billing.invoices.installment', $invoice), ['installment_count' => 4]);

    $sum = InvoiceInstallment::where('invoice_id', $invoice->id)->sum('amount_minor');
    expect($sum)->toBe($invoice->total->getMinorAmount()->toInt());
});

it('customer cannot create duplicate installment plan', function (): void {
    $customer = customerUser();

    $invoice = Invoice::factory()->create([
        'customer_id' => $customer->customer->id,
        'status'      => 'sent',
    ]);

    $this->actingAs($customer)
         ->post(route('panel.billing.invoices.installment', $invoice), ['installment_count' => 2]);

    $this->actingAs($customer)
         ->post(route('panel.billing.invoices.installment', $invoice), ['installment_count' => 2])
         ->assertStatus(422);
});

it('admin can view installment plans index', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.invoice-installments.index'))
         ->assertOk()
         ->assertViewIs('admin.invoice-installments.index');
});

it('customer cannot access other customer invoice installment', function (): void {
    $customer1 = customerUser();
    $customer2 = customerUser();

    $invoice = Invoice::factory()->create([
        'customer_id' => $customer1->customer->id,
        'status'      => 'sent',
    ]);

    $this->actingAs($customer2)
         ->post(route('panel.billing.invoices.installment', $invoice), ['installment_count' => 2])
         ->assertForbidden();
});
