<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceStatus;
use Illuminate\Support\Str;

it('customer can view billing statement form', function (): void {
    $user = customerUser();

    $this->actingAs($user)
         ->get(route('panel.billing.statement'))
         ->assertOk()
         ->assertSee('výkaz');
});

it('billing statement download returns PDF content type', function (): void {
    $user     = customerUser();
    $year     = now()->year;
    $month    = now()->month;

    $response = $this->actingAs($user)
         ->get(route('panel.billing.statement.download', ['year' => $year, 'month' => $month]));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

it('billing statement includes paid invoice amount', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    $year     = now()->year;
    $month    = now()->month;

    \Illuminate\Support\Facades\DB::table('invoices')->insert([
        'uuid'                  => (string) Str::uuid(),
        'customer_id'           => $customer->id,
        'status'                => InvoiceStatus::Paid->value,
        'type'                  => 'invoice',
        'series'                => 'CZ',
        'number'                => 'CZ-STMT-' . uniqid(),
        'vat_scenario'          => 'cz_standard',
        'currency'              => 'CZK',
        'subtotal'              => 1000,
        'tax_amount'            => 210,
        'total'                 => 1210,
        'due_date'              => now()->toDateString(),
        'paid_at'               => now()->toDateTimeString(),
        'snapshot_name'         => 'Test Customer',
        'snapshot_street'       => 'Test 1',
        'snapshot_city'         => 'Praha',
        'snapshot_zip'          => '11000',
        'snapshot_country_code' => 'CZ',
        'created_at'            => now(),
        'updated_at'            => now(),
    ]);

    $response = $this->actingAs($user)
         ->get(route('panel.billing.statement.download', ['year' => $year, 'month' => $month]));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

it('future month returns redirect with error', function (): void {
    $user  = customerUser();
    $year  = now()->addYear()->year;
    $month = now()->month;

    $this->actingAs($user)
         ->get(route('panel.billing.statement.download', ['year' => $year, 'month' => $month]))
         ->assertRedirect();
});

it('guest is redirected to login', function (): void {
    $this->get(route('panel.billing.statement'))
         ->assertRedirect('/login');
});
