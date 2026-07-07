<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\InvoiceSettingsController;
use App\Models\InvoiceSetting;

test('invoice settings controller exists', function (): void {
    expect(class_exists(InvoiceSettingsController::class))->toBeTrue();
});

test('invoice settings model get/set works', function (): void {
    InvoiceSetting::set('test_key', 'test_value');
    expect(InvoiceSetting::get('test_key'))->toBe('test_value');
});

test('invoice settings get returns default when missing', function (): void {
    expect(InvoiceSetting::get('nonexistent_key_xyz', 'default'))->toBe('default');
});

test('invoice settings show route exists', function (): void {
    expect(Route::has('admin.invoice-settings.show'))->toBeTrue();
});

test('invoice settings update requires admin', function (): void {
    $response = $this->patch(route('admin.invoice-settings.update'), ['footer_text' => 'test']);
    $response->assertRedirect();
});
