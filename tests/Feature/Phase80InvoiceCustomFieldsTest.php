<?php

declare(strict_types=1);

use App\Domains\Billing\Models\InvoiceCustomFieldValue;
use App\Domains\Billing\Models\InvoiceFieldDefinition;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Model tests ───────────────────────────────────────────────────────────────

it('TYPES constant has all supported types', function (): void {
    expect(InvoiceFieldDefinition::TYPES)->toHaveKey('text')
        ->and(InvoiceFieldDefinition::TYPES)->toHaveKey('number')
        ->and(InvoiceFieldDefinition::TYPES)->toHaveKey('date');
});

it('InvoiceFieldDefinition has values relation', function (): void {
    $def = InvoiceFieldDefinition::create([
        'key'   => 'po_number',
        'label' => 'PO číslo',
        'type'  => 'text',
    ]);

    expect($def->values())->not->toBeNull();
});

// ── Admin routes ──────────────────────────────────────────────────────────────

it('admin can view invoice fields page', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.invoice-fields.index'))
        ->assertOk()
        ->assertSee('Vlastní pole faktur');
});

it('admin can create a field definition', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.invoice-fields.store'), [
            'label'           => 'PO číslo',
            'type'            => 'text',
            'is_required'     => 0,
            'show_on_invoice' => 1,
            'sort_order'      => 1,
        ])
        ->assertRedirect(route('admin.invoice-fields.index'));

    expect(InvoiceFieldDefinition::where('label', 'PO číslo')->exists())->toBeTrue();
});

it('slug is auto-generated from label', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.invoice-fields.store'), [
            'label' => 'Projekt kód',
            'type'  => 'text',
        ])
        ->assertRedirect();

    $def = InvoiceFieldDefinition::where('label', 'Projekt kód')->first();
    expect($def)->not->toBeNull()
        ->and($def->key)->not->toBeEmpty();
});

it('label is required for field creation', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.invoice-fields.store'), ['type' => 'text'])
        ->assertSessionHasErrors('label');
});

it('admin can update a field definition', function (): void {
    $admin = adminUser();
    $def   = InvoiceFieldDefinition::create([
        'key'   => 'old_key',
        'label' => 'Old Label',
        'type'  => 'text',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.invoice-fields.update', $def), [
            'label'           => 'New Label',
            'type'            => 'number',
            'is_required'     => 1,
            'show_on_invoice' => 1,
            'is_active'       => 1,
            'sort_order'      => 5,
        ])
        ->assertRedirect(route('admin.invoice-fields.index'));

    expect($def->fresh()->label)->toBe('New Label')
        ->and($def->fresh()->type)->toBe('number')
        ->and($def->fresh()->is_required)->toBeTrue()
        ->and($def->fresh()->sort_order)->toBe(5);
});

it('admin can delete a field definition', function (): void {
    $admin = adminUser();
    $def   = InvoiceFieldDefinition::create([
        'key'   => 'del_key',
        'label' => 'Delete Me',
        'type'  => 'text',
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.invoice-fields.destroy', $def))
        ->assertRedirect(route('admin.invoice-fields.index'));

    expect(InvoiceFieldDefinition::find($def->id))->toBeNull();
});

it('admin can save custom field values on an invoice', function (): void {
    $admin = adminUser();
    $user  = customerUser();
    $def   = InvoiceFieldDefinition::create([
        'key'       => 'po_number',
        'label'     => 'PO číslo',
        'type'      => 'text',
        'is_active' => true,
    ]);

    [$order, $invoice] = placeOrderAndInvoice($user);

    $this->actingAs($admin)
        ->post(route('admin.invoice-fields.save-values', $invoice), [
            'fields' => [$def->id => 'PO-2026-001'],
        ])
        ->assertRedirect();

    expect(InvoiceCustomFieldValue::where('invoice_id', $invoice->id)->where('invoice_field_definition_id', $def->id)->value('value'))
        ->toBe('PO-2026-001');
});

it('saving values twice uses updateOrCreate', function (): void {
    $admin = adminUser();
    $user  = customerUser();
    $def   = InvoiceFieldDefinition::create([
        'key'       => 'proj_code',
        'label'     => 'Projekt kód',
        'type'      => 'text',
        'is_active' => true,
    ]);

    [$order, $invoice] = placeOrderAndInvoice($user);

    $this->actingAs($admin)
        ->post(route('admin.invoice-fields.save-values', $invoice), ['fields' => [$def->id => 'PROJ-A']]);

    $this->actingAs($admin)
        ->post(route('admin.invoice-fields.save-values', $invoice), ['fields' => [$def->id => 'PROJ-B']]);

    expect(InvoiceCustomFieldValue::where('invoice_id', $invoice->id)->count())->toBe(1);
    expect(InvoiceCustomFieldValue::where('invoice_id', $invoice->id)->value('value'))->toBe('PROJ-B');
});

it('invoice has customFieldValues relation', function (): void {
    $user = customerUser();
    [$order, $invoice] = placeOrderAndInvoice($user);

    expect($invoice->customFieldValues())->not->toBeNull();
});

it('non-admin cannot access invoice fields', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.invoice-fields.index'))
        ->assertStatus(403);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function placeOrderAndInvoice($user): array
{
    $result  = placeOrder($user);
    $order   = $result['order'];
    $invoice = $result['invoice'];
    return [$order, $invoice];
}
