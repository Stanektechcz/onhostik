<?php

use App\Domains\Billing\Models\Invoice;
use App\Domains\Customer\Models\Customer;
use App\Models\InvoiceDispute;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('customer can view their disputes list', function (): void {
    $user = customerUser();
    $this->actingAs($user)
        ->get(route('panel.invoices.disputes.index'))
        ->assertOk()
        ->assertViewIs('panel.invoices.disputes');
});

it('customer can file a dispute on their own invoice', function (): void {
    $user    = customerUser();
    $invoice = Invoice::factory()->create(['customer_id' => $user->customer->id]);

    $this->actingAs($user)
        ->post(route('panel.billing.invoices.dispute', $invoice), ['reason' => 'Faktura obsahuje chybné položky'])
        ->assertRedirect();

    expect(InvoiceDispute::where('invoice_id', $invoice->id)->exists())->toBeTrue();
});

it('customer cannot file dispute on another customer invoice', function (): void {
    $user     = customerUser();
    $other    = Customer::factory()->create();
    $invoice  = Invoice::factory()->create(['customer_id' => $other->id]);

    $this->actingAs($user)
        ->post(route('panel.billing.invoices.dispute', $invoice), ['reason' => 'Chybná faktura lorem ipsum'])
        ->assertForbidden();
});

it('duplicate open dispute is rejected', function (): void {
    $user    = customerUser();
    $invoice = Invoice::factory()->create(['customer_id' => $user->customer->id]);
    InvoiceDispute::create([
        'invoice_id'  => $invoice->id,
        'customer_id' => $user->customer->id,
        'reason'      => 'První námitka',
        'status'      => 'open',
    ]);

    $this->actingAs($user)
        ->post(route('panel.billing.invoices.dispute', $invoice), ['reason' => 'Druhá námitka o položce'])
        ->assertSessionHasErrors('dispute');
});

it('admin can resolve a dispute', function (): void {
    $admin   = adminUser();
    $dispute = InvoiceDispute::factory()->create(['status' => 'open']);

    $this->actingAs($admin)
        ->patch(route('admin.invoice-disputes.resolve', $dispute), [
            'status'     => 'resolved',
            'admin_note' => 'Prověřeno a schváleno.',
        ])
        ->assertRedirect();

    expect($dispute->fresh()->status)->toBe('resolved');
});
