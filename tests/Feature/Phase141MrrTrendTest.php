<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceStatus;
use Illuminate\Support\Str;

function insertPaidInvoice(int $customerId, int $totalMinor, string $paidAt): void
{
    \Illuminate\Support\Facades\DB::table('invoices')->insert([
        'uuid'                  => (string) Str::uuid(),
        'customer_id'           => $customerId,
        'status'                => InvoiceStatus::Paid->value,
        'type'                  => 'invoice',
        'series'                => 'CZ',
        'number'                => 'CZ-MRR-' . uniqid(),
        'vat_scenario'          => 'cz_standard',
        'currency'              => 'CZK',
        'subtotal'              => (int) round($totalMinor / 1.21),
        'tax_amount'            => $totalMinor - (int) round($totalMinor / 1.21),
        'total'                 => $totalMinor,
        'due_date'              => $paidAt,
        'paid_at'               => $paidAt,
        'snapshot_name'         => 'MRR Test',
        'snapshot_street'       => 'Test 1',
        'snapshot_city'         => 'Praha',
        'snapshot_zip'          => '11000',
        'snapshot_country_code' => 'CZ',
        'created_at'            => now(),
        'updated_at'            => now(),
    ]);
}

it('admin can view MRR trend page', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.metrics.mrr-trend'))
         ->assertOk()
         ->assertSee('MRR');
});

it('MRR trend shows 12 months of data', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.metrics.mrr-trend'))
         ->assertOk()
         ->assertSee(now()->format('Y-m'));
});

it('MRR trend includes paid invoices in current month', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    insertPaidInvoice($customer->customer->id, 50000, now()->format('Y-m-d H:i:s'));

    $response = $this->actingAs($admin)
         ->get(route('admin.metrics.mrr-trend'));

    $response->assertOk();
    // Should show 500 Kč (50000 minor = 500.00 Kč)
    expect($response->getContent())->toContain('500');
});

it('MRR trend excludes unpaid invoices', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    \Illuminate\Support\Facades\DB::table('invoices')->insert([
        'uuid'                  => (string) Str::uuid(),
        'customer_id'           => $customer->customer->id,
        'status'                => InvoiceStatus::Overdue->value,
        'type'                  => 'invoice',
        'series'                => 'CZ',
        'number'                => 'CZ-MRR-UNPAID',
        'vat_scenario'          => 'cz_standard',
        'currency'              => 'CZK',
        'subtotal'              => 1000000,
        'tax_amount'            => 210000,
        'total'                 => 1210000,
        'due_date'              => now()->subDays(5)->toDateString(),
        'snapshot_name'         => 'Unpaid MRR',
        'snapshot_street'       => 'Test 1',
        'snapshot_city'         => 'Praha',
        'snapshot_zip'          => '11000',
        'snapshot_country_code' => 'CZ',
        'created_at'            => now(),
        'updated_at'            => now(),
    ]);

    $response = $this->actingAs($admin)
         ->get(route('admin.metrics.mrr-trend'));

    $response->assertOk();
    // 12 100 Kč from unpaid invoice should NOT appear
    expect($response->getContent())->not->toContain('12 100');
});

it('customer cannot access MRR trend', function (): void {
    adminUser();
    $user = customerUser();

    $this->actingAs($user)
         ->get(route('admin.metrics.mrr-trend'))
         ->assertForbidden();
});
