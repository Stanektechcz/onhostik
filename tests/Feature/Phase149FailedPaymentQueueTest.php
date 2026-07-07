<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Notifications\RenewalPaymentFailedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

function makeRenewalInvoice(\App\Models\User $user): \App\Domains\Billing\Models\Invoice
{
    $service = \App\Domains\Provisioning\Models\Service::factory()->create([
        'customer_id' => $user->customer->id,
        'label'       => 'Test Renewal VPS',
    ]);

    $id = \Illuminate\Support\Facades\DB::table('invoices')->insertGetId([
        'uuid'                          => (string) Str::uuid(),
        'customer_id'                   => $user->customer->id,
        'status'                        => InvoiceStatus::Overdue->value,
        'type'                          => 'invoice',
        'series'                        => 'CZ',
        'number'                        => 'CZ-REN-' . uniqid(),
        'vat_scenario'                  => 'cz_standard',
        'currency'                      => 'CZK',
        'subtotal'                      => 5000,
        'tax_amount'                    => 1050,
        'total'                         => 6050,
        'due_date'                      => now()->subDays(5)->toDateString(),
        'renewal_service_id'            => $service->id,
        'renewal_failure_notified_at'   => now()->subDay(),
        'snapshot_name'                 => 'Test Renewal',
        'snapshot_street'               => 'Test 1',
        'snapshot_city'                 => 'Praha',
        'snapshot_zip'                  => '11000',
        'snapshot_country_code'         => 'CZ',
        'created_at'                    => now(),
        'updated_at'                    => now(),
    ]);
    return \App\Domains\Billing\Models\Invoice::find($id);
}

it('admin can view failed payment queue', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    makeRenewalInvoice($customer);

    $this->actingAs($admin)
         ->get(route('admin.failed-payment-queue.index'))
         ->assertOk()
         ->assertSee('Fronta');
});

it('failed payment queue shows overdue renewal invoices', function (): void {
    $admin    = adminUser();
    $customer = customerUser();
    $invoice  = makeRenewalInvoice($customer);

    $response = $this->actingAs($admin)
         ->get(route('admin.failed-payment-queue.index'));

    $response->assertOk();
    expect($response->getContent())->toContain($invoice->number);
});

it('admin can resend payment failure notification', function (): void {
    Notification::fake();
    $admin    = adminUser();
    $customer = customerUser();
    $invoice  = makeRenewalInvoice($customer);

    $this->actingAs($admin)
         ->post(route('admin.failed-payment-queue.resend', $invoice))
         ->assertRedirect();

    Notification::assertSentTo($customer, RenewalPaymentFailedNotification::class);
});

it('resend updates renewal_failure_notified_at timestamp', function (): void {
    Notification::fake();
    $admin    = adminUser();
    $customer = customerUser();
    $invoice  = makeRenewalInvoice($customer);

    $before = $invoice->renewal_failure_notified_at;

    $this->actingAs($admin)
         ->post(route('admin.failed-payment-queue.resend', $invoice));

    expect(\App\Domains\Billing\Models\Invoice::find($invoice->id)->renewal_failure_notified_at)->not->toBe($before);
});

it('customer cannot access failed payment queue', function (): void {
    adminUser();
    $user = customerUser();

    $this->actingAs($user)
         ->get(route('admin.failed-payment-queue.index'))
         ->assertForbidden();
});
