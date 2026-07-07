<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceStatus;

it('admin can view customer LTV report', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.customers.ltv-report'))
         ->assertOk()
         ->assertSee('LTV');
});

it('LTV report shows top customers with paid invoices', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    \Illuminate\Support\Facades\DB::table('invoices')->insert([
        'uuid'                  => (string) \Illuminate\Support\Str::uuid(),
        'customer_id'           => $customer->customer->id,
        'status'                => InvoiceStatus::Paid->value,
        'type'                  => 'invoice',
        'series'                => 'CZ',
        'number'                => 'CZ-2026-LTV001',
        'vat_scenario'          => 'cz_standard',
        'currency'              => 'CZK',
        'subtotal'              => 150000,
        'tax_amount'            => 31500,
        'total'                 => 181500,
        'due_date'              => now()->subDays(30)->toDateString(),
        'paid_at'               => now()->subDays(29),
        'snapshot_name'         => 'LTV Test Customer',
        'snapshot_street'       => 'Testovací 1',
        'snapshot_city'         => 'Praha',
        'snapshot_zip'          => '11000',
        'snapshot_country_code' => 'CZ',
        'created_at'            => now(),
        'updated_at'            => now(),
    ]);

    $this->actingAs($admin)
         ->get(route('admin.customers.ltv-report'))
         ->assertOk()
         ->assertSee($customer->customer->email);
});

it('LTV report shows segment average stats', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.customers.ltv-report'))
         ->assertOk()
         ->assertSee('Průměrné LTV');
});

it('LTV report excludes unpaid invoices', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    \Illuminate\Support\Facades\DB::table('invoices')->insert([
        'uuid'                  => (string) \Illuminate\Support\Str::uuid(),
        'customer_id'           => $customer->customer->id,
        'status'                => InvoiceStatus::Overdue->value,
        'type'                  => 'invoice',
        'series'                => 'CZ',
        'number'                => 'CZ-2026-LTV002',
        'vat_scenario'          => 'cz_standard',
        'currency'              => 'CZK',
        'subtotal'              => 999900,
        'tax_amount'            => 209979,
        'total'                 => 1209879,
        'due_date'              => now()->subDays(10)->toDateString(),
        'snapshot_name'         => 'Overdue Customer',
        'snapshot_street'       => 'Testovací 2',
        'snapshot_city'         => 'Brno',
        'snapshot_zip'          => '60200',
        'snapshot_country_code' => 'CZ',
        'created_at'            => now(),
        'updated_at'            => now(),
    ]);

    // Only this customer has invoices, but all are overdue — should not appear in top list
    $response = $this->actingAs($admin)
         ->get(route('admin.customers.ltv-report'));

    $response->assertOk();
    // Customer appears only if they have PAID invoices
    $content = $response->getContent();
    expect($content)->not->toContain('1 209 879');
});

it('customer cannot access LTV report', function (): void {
    adminUser();
    $user = customerUser();

    $this->actingAs($user)
         ->get(route('admin.customers.ltv-report'))
         ->assertForbidden();
});
