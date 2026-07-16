<?php

declare(strict_types=1);

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\InvoicePdfService;
use Database\Seeders\ProductCatalogSeeder;

/**
 * Guards the invoice PDF against the two regressions the user hit:
 *  - a fixed 1100px wrapper that overflowed the A4 page, and
 *  - Czech diacritics turning into "?" in bold text / uppercased titles.
 */

it('invoice PDF template has no fixed pixel wrapper wider than the A4 page', function (): void {
    $view = file_get_contents(resource_path('views/pdf/invoice.blade.php'));

    // The wrapper must be fluid, never a fixed multi-hundred-px width.
    expect($view)->not->toContain('width: 1100px')
        ->and($view)->toContain('.wrap { width: 100%');
});

it('invoice PDF uppercases the title with a multibyte-safe function', function (): void {
    $view = file_get_contents(resource_path('views/pdf/invoice.blade.php'));

    // strtoupper() corrupts ě/ň/č; the template must use mb_strtoupper.
    expect($view)->toContain('mb_strtoupper($invoice->type->label()')
        ->and($view)->not->toContain('{{ strtoupper(');
});

it('dompdf default font covers Czech diacritics', function (): void {
    expect(strtolower((string) config('dompdf.options.default_font')))
        ->toContain('dejavu');
});

it('generates a non-empty PDF for a real invoice without errors', function (): void {
    $this->seed(ProductCatalogSeeder::class);

    $user     = customerUser();
    $customer = $user->customer;
    $customer->addresses()->create([
        'type' => 'billing', 'street' => 'Testovací 1', 'city' => 'Praha',
        'zip' => '11000', 'country_code' => 'CZ', 'is_primary' => true,
    ]);

    $invoice = Invoice::factory()->create([
        'customer_id'    => $customer->id,
        'snapshot_name'  => 'Zákazník s.r.o.',
        'variable_symbol' => '2026000123',
    ]);

    $pdf = app(InvoicePdfService::class)->generate($invoice);

    expect(strlen($pdf))->toBeGreaterThan(1000)
        ->and(substr($pdf, 0, 4))->toBe('%PDF');
});
