<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceStatus;
use Illuminate\Support\Str;

function makeTestInvoice(int $customerId, string $suffix): int
{
    return \Illuminate\Support\Facades\DB::table('invoices')->insertGetId([
        'uuid'                  => (string) Str::uuid(),
        'customer_id'           => $customerId,
        'status'                => InvoiceStatus::Paid->value,
        'type'                  => 'invoice',
        'series'                => 'CZ',
        'number'                => "CZ-2026-ZIP{$suffix}",
        'vat_scenario'          => 'cz_standard',
        'currency'              => 'CZK',
        'subtotal'              => 10000,
        'tax_amount'            => 2100,
        'total'                 => 12100,
        'due_date'              => now()->subDays(30)->toDateString(),
        'paid_at'               => now()->subDays(29),
        'snapshot_name'         => 'ZIP Test Customer',
        'snapshot_street'       => 'Testovací 1',
        'snapshot_city'         => 'Praha',
        'snapshot_zip'          => '11000',
        'snapshot_country_code' => 'CZ',
        'created_at'            => now(),
        'updated_at'            => now(),
    ]);
}

it('customer can download a zip of their invoices', function (): void {
    $user       = customerUser();
    $invoiceId  = makeTestInvoice($user->customer->id, uniqid());

    $response = $this->actingAs($user)
         ->post(route('panel.billing.invoices.bulk-zip'), [
             'invoice_ids' => [$invoiceId],
         ]);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('zip');
});

it('bulk zip requires at least one invoice id', function (): void {
    $user = customerUser();

    $this->actingAs($user)
         ->post(route('panel.billing.invoices.bulk-zip'), [
             'invoice_ids' => [],
         ])
         ->assertSessionHasErrors(['invoice_ids']);
});

it('customer cannot download another customers invoices', function (): void {
    adminUser();
    $owner = customerUser();
    $other = customerUser();

    $invoiceId = makeTestInvoice($owner->customer->id, uniqid());

    $response = $this->actingAs($other)
         ->post(route('panel.billing.invoices.bulk-zip'), [
             'invoice_ids' => [$invoiceId],
         ]);

    // Should redirect back with error (no invoices found for 'other')
    $response->assertRedirect();
    expect(session('errors')?->first())->not->toBeNull();
});

it('bulk zip max 50 invoices validation', function (): void {
    $user = customerUser();
    $ids  = range(1, 51);

    $this->actingAs($user)
         ->post(route('panel.billing.invoices.bulk-zip'), [
             'invoice_ids' => $ids,
         ])
         ->assertSessionHasErrors(['invoice_ids']);
});

it('guest is redirected from bulk zip route', function (): void {
    $this->post(route('panel.billing.invoices.bulk-zip'), [
             'invoice_ids' => [1],
         ])
         ->assertRedirect(route('login'));
});
