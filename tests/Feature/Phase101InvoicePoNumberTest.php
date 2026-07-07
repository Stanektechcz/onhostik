<?php

declare(strict_types=1);

use App\Domains\Billing\Models\Invoice;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Model / migration ─────────────────────────────────────────────────────────

it('Invoice accepts purchase_order_number and custom_reference', function (): void {
    $user   = customerUser();
    $result = placeOrder($user);
    /** @var Invoice $invoice */
    $invoice = $result['invoice'];

    $invoice->update([
        'purchase_order_number' => 'PO-2024-9999',
        'custom_reference'      => 'Projekt Alfa / středisko 42',
    ]);

    $invoice->refresh();

    expect($invoice->purchase_order_number)->toBe('PO-2024-9999')
        ->and($invoice->custom_reference)->toBe('Projekt Alfa / středisko 42');
});

// ── Panel: customer can set reference ─────────────────────────────────────────

it('customer can set purchase_order_number via panel', function (): void {
    $user   = customerUser();
    $result = placeOrder($user);
    /** @var Invoice $invoice */
    $invoice = $result['invoice'];

    $this->actingAs($user)
        ->put(route('panel.billing.invoices.update-reference', $invoice), [
            'purchase_order_number' => 'PO-123',
            'custom_reference'      => 'My reference',
        ])
        ->assertRedirect();

    $invoice->refresh();
    expect($invoice->purchase_order_number)->toBe('PO-123')
        ->and($invoice->custom_reference)->toBe('My reference');
});

it('customer can clear reference fields by sending empty strings', function (): void {
    $user   = customerUser();
    $result = placeOrder($user);
    /** @var Invoice $invoice */
    $invoice = $result['invoice'];
    $invoice->update(['purchase_order_number' => 'PO-OLD', 'custom_reference' => 'old']);

    $this->actingAs($user)
        ->put(route('panel.billing.invoices.update-reference', $invoice), [
            'purchase_order_number' => '',
            'custom_reference'      => '',
        ])
        ->assertRedirect();

    $invoice->refresh();
    expect($invoice->purchase_order_number)->toBeNull()
        ->and($invoice->custom_reference)->toBeNull();
});

it('another customer cannot update reference of someone elses invoice', function (): void {
    $user1  = customerUser();
    $user2  = customerUser();
    $result = placeOrder($user1);
    /** @var Invoice $invoice */
    $invoice = $result['invoice'];

    $this->actingAs($user2)
        ->put(route('panel.billing.invoices.update-reference', $invoice), [
            'purchase_order_number' => 'HACKED',
        ])
        ->assertForbidden();
});

it('updateReference validates max length on purchase_order_number', function (): void {
    $user   = customerUser();
    $result = placeOrder($user);
    $invoice = $result['invoice'];

    $this->actingAs($user)
        ->put(route('panel.billing.invoices.update-reference', $invoice), [
            'purchase_order_number' => str_repeat('x', 101), // > 100 chars
        ])
        ->assertSessionHasErrors(['purchase_order_number']);
});

// ── Panel invoice show displays reference ─────────────────────────────────────

it('panel invoice show displays purchase_order_number when set', function (): void {
    $user   = customerUser();
    $result = placeOrder($user);
    /** @var Invoice $invoice */
    $invoice = $result['invoice'];
    $invoice->update(['purchase_order_number' => 'PO-SHOW-TEST']);

    $this->actingAs($user)
        ->get(route('panel.billing.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('PO-SHOW-TEST');
});

// ── Admin invoice show displays reference ─────────────────────────────────────

it('admin invoice show displays purchase_order_number badge', function (): void {
    $admin  = adminUser();
    $user   = customerUser();
    $result = placeOrder($user);
    /** @var Invoice $invoice */
    $invoice = $result['invoice'];
    $invoice->update(['purchase_order_number' => 'PO-ADMIN-BADGE']);

    $this->actingAs($admin)
        ->get(route('admin.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('PO-ADMIN-BADGE');
});
