<?php

declare(strict_types=1);

use App\Domains\Billing\Models\Invoice;
use App\Domains\Customer\Models\Customer;

it('admin can view customer merge page', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    $this->actingAs($admin)
         ->get(route('admin.customers.merge.show', $customer->customer))
         ->assertOk()
         ->assertViewIs('admin.customer-merge');
});

it('admin can merge two customers', function (): void {
    $admin    = adminUser();
    $target   = customerUser();
    $source   = customerUser();

    $invoice = Invoice::factory()->create(['customer_id' => $source->customer->id]);

    $this->actingAs($admin)
         ->post(route('admin.customers.merge', $target->customer), [
             'source_customer_id' => $source->customer->id,
         ])
         ->assertRedirect();

    expect(Invoice::find($invoice->id)->customer_id)->toBe($target->customer->id);
});

it('source customer is soft-deleted after merge', function (): void {
    $admin  = adminUser();
    $target = customerUser();
    $source = customerUser();

    $sourceId = $source->customer->id;

    $this->actingAs($admin)
         ->post(route('admin.customers.merge', $target->customer), [
             'source_customer_id' => $sourceId,
         ]);

    expect(Customer::withTrashed()->find($sourceId)->deleted_at)->not->toBeNull();
});

it('merging customer with itself returns 422', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    $this->actingAs($admin)
         ->post(route('admin.customers.merge', $customer->customer), [
             'source_customer_id' => $customer->customer->id,
         ])
         ->assertStatus(422);
});

it('customer cannot access merge functionality', function (): void {
    $customer = customerUser();

    $this->actingAs($customer)
         ->get(route('admin.customers.merge.show', $customer->customer))
         ->assertForbidden();
});
