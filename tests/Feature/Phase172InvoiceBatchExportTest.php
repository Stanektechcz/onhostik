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

test('invoice batch export validates max 50 invoices', function (): void {
    $admin = adminUser();
    $ids = range(1, 51);
    $response = $this->actingAs($admin)->post(route('admin.invoice-batch.export'), ['invoice_ids' => $ids]);
    $response->assertSessionHasErrors('invoice_ids');
});
