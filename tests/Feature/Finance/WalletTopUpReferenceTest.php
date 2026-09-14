<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Onhost\Domain\Invoicing\InvoiceNumberAllocator;

beforeEach(fn () => $this->seed([LegalEntitySeeder::class]));

it('gives bank-transfer top-ups their own variable symbol series that cannot collide with proforma symbols', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $this->actingAs($owner, 'sanctum');

    $first = $this->postJson('/v1/payments/init', ['amount' => 5000, 'currency' => 'CZK', 'provider' => 'bank'], ['Idempotency-Key' => 'topup-vs-1'])->assertCreated();
    $second = $this->postJson('/v1/payments/init', ['amount' => 5000, 'currency' => 'CZK', 'provider' => 'bank'], ['Idempotency-Key' => 'topup-vs-2'])->assertCreated();

    $vs1 = (string) $first->json('instructions.variable_symbol');
    $vs2 = (string) $second->json('instructions.variable_symbol');
    expect($first->json('provider'))->toBe('bank')->and($first->json('redirect_url'))->toBeNull()
        ->and($vs1)->toMatch('/^9\d{8}$/')->and($vs2)->toMatch('/^9\d{8}$/')->and($vs2)->not->toBe($vs1)
        ->and($first->json('instructions.qr_spd'))->toContain('X-VS:'.$vs1)
        // the proforma series keeps year + sequence, so a top-up symbol never equals a document symbol
        ->and(InvoiceNumberAllocator::variableSymbol('PF-2026-0001'))->toBe('20260001')->and(strlen($vs1))->toBe(9);
});
