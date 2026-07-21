<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Customer\Models\Customer;
use App\Domains\Reseller\Exceptions\ResellerCustomerLimitReached;
use App\Domains\Reseller\Models\ResellerProfile;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * K109 reseller sub-customer cap + D41 corporate payment terms.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── K109: sub-customer cap ────────────────────────────────────────────────────

it('treats a reseller with no cap as unlimited', function (): void {
    // Every reseller created before the column existed has NULL — adding the
    // limit must never retroactively lock them out.
    $reseller = ResellerProfile::factory()->create(['max_customers' => null]);

    expect($reseller->hasCustomerLimit())->toBeFalse()
        ->and($reseller->customerSlotsRemaining())->toBeNull()
        ->and($reseller->canAddCustomer())->toBeTrue();
});

it('reports remaining slots against the cap', function (): void {
    $reseller = ResellerProfile::factory()->create(['max_customers' => 3]);

    Customer::factory()->create(['reseller_id' => $reseller->id]);

    expect($reseller->fresh()->customerSlotsRemaining())->toBe(2);
});

it('accepts a customer while slots remain', function (): void {
    $reseller = ResellerProfile::factory()->create(['max_customers' => 2]);

    Customer::factory()->create(['reseller_id' => $reseller->id]);
    Customer::factory()->create(['reseller_id' => $reseller->id]);

    expect($reseller->fresh()->customerSlotsRemaining())->toBe(0)
        ->and($reseller->fresh()->canAddCustomer())->toBeFalse();
});

it('refuses to attach a customer beyond the cap', function (): void {
    $reseller = ResellerProfile::factory()->create(['max_customers' => 1]);
    Customer::factory()->create(['reseller_id' => $reseller->id]);

    expect(fn () => Customer::factory()->create(['reseller_id' => $reseller->id]))
        ->toThrow(ResellerCustomerLimitReached::class);

    // The over-limit customer must not have landed.
    expect(Customer::where('reseller_id', $reseller->id)->count())->toBe(1);
});

it('still allows editing a customer already attached to a full reseller', function (): void {
    /*
     | If the cap is lowered after the fact, existing customers are over it.
     | Blocking edits then would stop an admin fixing their own data — the cap
     | governs ATTACHING, not touching.
     */
    $reseller = ResellerProfile::factory()->create(['max_customers' => 5]);
    $customer = Customer::factory()->create(['reseller_id' => $reseller->id]);

    $reseller->update(['max_customers' => 1]);

    $customer->update(['company_name' => 'Přejmenováno']);

    expect($customer->fresh()->company_name)->toBe('Přejmenováno');
});

it('does not block a customer with no reseller at all', function (): void {
    $customer = Customer::factory()->create(['reseller_id' => null]);

    expect($customer->exists)->toBeTrue();
});

// ── D41: corporate payment terms ──────────────────────────────────────────────

it('uses the default proforma window when no terms are agreed', function (): void {
    config(['billing.proforma_validity_days' => 10]);

    $user = customerUser();
    ['invoice' => $invoice] = placeOrder($user);

    expect($invoice->due_date->toDateString())
        ->toBe(now()->addDays(10)->toDateString());
});

it('honours an agreed net-30 payment term', function (): void {
    config(['billing.proforma_validity_days' => 10]);

    $user = customerUser();
    $user->customer->update(['payment_terms_days' => 30]);

    ['invoice' => $invoice] = placeOrder($user->fresh());

    /*
     | Without this the customer was "overdue" from day 11 — dunning chased
     | them and late fees applied for an invoice that was contractually fine.
     */
    expect($invoice->due_date->toDateString())
        ->toBe(now()->addDays(30)->toDateString());
});

it('keeps a net-30 invoice out of the overdue set during its term', function (): void {
    $user = customerUser();
    $user->customer->update(['payment_terms_days' => 30]);

    ['invoice' => $invoice] = placeOrder($user->fresh());

    // Day 15: past the default window, well inside the agreed one.
    $this->travelTo(now()->addDays(15));

    expect($invoice->fresh()->due_date->isFuture())->toBeTrue()
        ->and($invoice->fresh()->status)->not->toBe(InvoiceStatus::Overdue);
});
