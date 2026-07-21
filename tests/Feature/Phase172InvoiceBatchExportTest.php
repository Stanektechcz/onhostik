<?php

declare(strict_types=1);

use App\Domains\Billing\Models\Invoice;
use App\Http\Controllers\Admin\InvoiceBatchExportController;

test('invoice batch export controller exists', function (): void {
    expect(class_exists(InvoiceBatchExportController::class))->toBeTrue();
});

test('invoice batch export route exists', function (): void {
    expect(Route::has('admin.invoice-batch.export'))->toBeTrue();
});

test('invoice batch export requires authentication', function (): void {
    $response = $this->post(route('admin.invoice-batch.export'), ['invoice_ids' => [1]]);
    $response->assertRedirect();
});

test('invoice batch export validates invoice_ids required', function (): void {
    $admin = adminUser();
    $response = $this->actingAs($admin)->post(route('admin.invoice-batch.export'), []);
    $response->assertSessionHasErrors('invoice_ids');
});

/*
 | The cap used to be 50 — not a business rule, but the most dompdf renders
 | that fit inside a web request before it timed out. The export now runs on
 | the queue (audit L120), so that reason is gone and the cap is 500: still
 | bounded, but no longer dictated by the request deadline.
 */
test('invoice batch export still bounds the batch size', function (): void {
    $admin = adminUser();
    $ids = range(1, 501);
    $response = $this->actingAs($admin)->post(route('admin.invoice-batch.export'), ['invoice_ids' => $ids]);
    $response->assertSessionHasErrors('invoice_ids');
});

test('invoice batch export accepts a batch larger than the old inline limit', function (): void {
    $admin = adminUser();
    $invoices = Invoice::factory()->count(51)->create();

    $this->actingAs($admin)
        ->post(route('admin.invoice-batch.export'), ['invoice_ids' => $invoices->pluck('id')->all()])
        ->assertSessionHasNoErrors();
});
