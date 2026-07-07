<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Models\InvoiceDispute;
use Illuminate\Support\Str;

function makeDisputableInvoice(\App\Models\User $user): \App\Domains\Billing\Models\Invoice
{
    $id = \Illuminate\Support\Facades\DB::table('invoices')->insertGetId([
        'uuid'                  => (string) Str::uuid(),
        'customer_id'           => $user->customer->id,
        'status'                => InvoiceStatus::Sent->value,
        'type'                  => 'invoice',
        'series'                => 'CZ',
        'number'                => 'CZ-DISP-' . uniqid(),
        'vat_scenario'          => 'cz_standard',
        'currency'              => 'CZK',
        'subtotal'              => 2000,
        'tax_amount'            => 420,
        'total'                 => 2420,
        'due_date'              => now()->addDays(10)->toDateString(),
        'snapshot_name'         => 'Test Disp',
        'snapshot_street'       => 'Test 1',
        'snapshot_city'         => 'Praha',
        'snapshot_zip'          => '11000',
        'snapshot_country_code' => 'CZ',
        'created_at'            => now(),
        'updated_at'            => now(),
    ]);
    return \App\Domains\Billing\Models\Invoice::find($id);
}

it('customer can file an invoice dispute', function (): void {
    $user    = customerUser();
    $invoice = makeDisputableInvoice($user);

    $this->actingAs($user)
         ->post(route('panel.billing.invoices.dispute', $invoice), [
             'reason' => 'Tato faktura obsahuje nesprávné položky.',
         ])
         ->assertRedirect();

    expect(InvoiceDispute::where('invoice_id', $invoice->id)->exists())->toBeTrue();
    expect(InvoiceDispute::where('invoice_id', $invoice->id)->value('status'))->toBe('open');
});

it('dispute reason must be at least 10 characters', function (): void {
    $user    = customerUser();
    $invoice = makeDisputableInvoice($user);

    $this->actingAs($user)
         ->post(route('panel.billing.invoices.dispute', $invoice), ['reason' => 'Krátce.'])
         ->assertSessionHasErrors(['reason']);
});

it('customer cannot dispute another customers invoice', function (): void {
    adminUser();
    $owner   = customerUser();
    $other   = customerUser();
    $invoice = makeDisputableInvoice($owner);

    $this->actingAs($other)
         ->post(route('panel.billing.invoices.dispute', $invoice), [
             'reason' => 'Pokus o podvod na cizí fakturu.',
         ])
         ->assertForbidden();
});

it('admin can view invoice disputes list', function (): void {
    $admin   = adminUser();
    $user    = customerUser();
    $invoice = makeDisputableInvoice($user);

    InvoiceDispute::create([
        'invoice_id'  => $invoice->id,
        'customer_id' => $user->customer->id,
        'reason'      => 'Test dispute reason here.',
        'status'      => 'open',
    ]);

    $this->actingAs($admin)
         ->get(route('admin.invoice-disputes.index'))
         ->assertOk()
         ->assertSee('Námitky');
});

it('admin can resolve a dispute', function (): void {
    $admin   = adminUser();
    $user    = customerUser();
    $invoice = makeDisputableInvoice($user);

    $dispute = InvoiceDispute::create([
        'invoice_id'  => $invoice->id,
        'customer_id' => $user->customer->id,
        'reason'      => 'Test dispute reason.',
        'status'      => 'open',
    ]);

    $this->actingAs($admin)
         ->patch(route('admin.invoice-disputes.resolve', $dispute), [
             'status'     => 'resolved',
             'admin_note' => 'Uznáváme námitku, opravíme fakturu.',
         ])
         ->assertRedirect();

    expect(InvoiceDispute::find($dispute->id)->status)->toBe('resolved');
});
