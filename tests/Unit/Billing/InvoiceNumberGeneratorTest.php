<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceSeries;
use App\Domains\Billing\Services\InvoiceNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates sequential numbers within a series and year', function (): void {
    $generator = new InvoiceNumberGenerator();

    expect($generator->next(InvoiceSeries::Czech, 2026))->toBe('CZ-2026-000001')
        ->and($generator->next(InvoiceSeries::Czech, 2026))->toBe('CZ-2026-000002')
        ->and($generator->next(InvoiceSeries::Czech, 2026))->toBe('CZ-2026-000003');
});

it('keeps independent sequences per series', function (): void {
    $generator = new InvoiceNumberGenerator();

    $generator->next(InvoiceSeries::Czech, 2026);

    expect($generator->next(InvoiceSeries::Eu, 2026))->toBe('EU-2026-000001')
        ->and($generator->next(InvoiceSeries::International, 2026))->toBe('INT-2026-000001');
});

it('keeps independent sequences per year', function (): void {
    $generator = new InvoiceNumberGenerator();

    $generator->next(InvoiceSeries::Czech, 2026);

    expect($generator->next(InvoiceSeries::Czech, 2027))->toBe('CZ-2027-000001');
});
