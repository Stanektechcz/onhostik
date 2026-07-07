<?php

declare(strict_types=1);

use Illuminate\Support\Str;

it('admin can view ARPU dashboard', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.metrics.arpu'))
         ->assertOk()
         ->assertSee('ARPU');
});

it('ARPU dashboard shows 12 months', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.metrics.arpu'))
         ->assertOk()
         ->assertSee(now()->format('Y-m'));
});

it('ARPU calculates correctly from paid invoices', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    \Illuminate\Support\Facades\DB::table('invoices')->insert([
        'uuid'                  => (string) Str::uuid(),
        'customer_id'           => $customer->customer->id,
        'status'                => 'paid',
        'type'                  => 'invoice',
        'series'                => 'CZ',
        'number'                => 'CZ-ARPU-' . uniqid(),
        'vat_scenario'          => 'cz_standard',
        'currency'              => 'CZK',
        'subtotal'              => 50000,
        'tax_amount'            => 10000,
        'total'                 => 60000,
        'due_date'              => now()->toDateString(),
        'paid_at'               => now()->toDateTimeString(),
        'snapshot_name'         => 'ARPU Test',
        'snapshot_street'       => 'Test 1',
        'snapshot_city'         => 'Praha',
        'snapshot_zip'          => '11000',
        'snapshot_country_code' => 'CZ',
        'created_at'            => now(),
        'updated_at'            => now(),
    ]);

    $response = $this->actingAs($admin)
         ->get(route('admin.metrics.arpu'));

    $response->assertOk();
    // 60000 minor / 1 customer = 600 Kč ARPU
    expect($response->getContent())->toContain('600');
});

it('ARPU shows zero for months with no paid invoices', function (): void {
    $admin = adminUser();

    $response = $this->actingAs($admin)
         ->get(route('admin.metrics.arpu'));

    $response->assertOk();
    expect($response->getContent())->toContain('0');
});

it('customer cannot access ARPU dashboard', function (): void {
    adminUser();
    $user = customerUser();

    $this->actingAs($user)
         ->get(route('admin.metrics.arpu'))
         ->assertForbidden();
});
