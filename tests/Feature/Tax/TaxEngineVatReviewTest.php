<?php

declare(strict_types=1);

use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Tax\TaxEngine;
use Onhost\Platform\Money\Money;

/*
 * TASK-0031 WP B (D31.4): a business of another member state that gave a VAT ID which is not verified now pays the destination
 * VAT of today — and the decision says so with `vat_review`, so finance sees who asked for reverse charge and did not get it.
 */

beforeEach(fn () => $this->seed(TaxRuleSeeder::class));

function vatReviewLines(): array
{
    return [['key' => 'l1', 'net' => Money::minor(10000, 'EUR'), 'product_class' => 'esd']];
}

it('flags a VAT ID that was given but is not verified, and keeps the destination rate', function () {
    $result = app(TaxEngine::class)->calculate(['country' => 'DE', 'customer_class' => 'b2b', 'vat_id' => 'DE1', 'vat_status' => 'unknown'], vatReviewLines(), 'EUR');

    expect($result['vat_review'])->toBeTrue()->and($result['review_required'])->toBeTrue()->and($result['lines'][0]['rate'])->toBe('19')->and($result['lines'][0]['category'])->toBe('S')
        ->and($result['calculation']->result['vat_review'])->toBeTrue()
        ->and(implode(' | ', $result['reasons']))->toContain('VAT ID given but not verified in VIES (status unknown)');
});

it('does not flag a business that gave no VAT ID', function () {
    $result = app(TaxEngine::class)->calculate(['country' => 'DE', 'customer_class' => 'b2b', 'vat_status' => 'unknown'], vatReviewLines(), 'EUR');

    expect($result['vat_review'])->toBeFalse()->and($result['lines'][0]['rate'])->toBe('19');
});

it('does not flag a verified business of another member state: reverse charge', function () {
    $result = app(TaxEngine::class)->calculate(['country' => 'SK', 'customer_class' => 'b2b', 'vat_id' => 'SK1234567890', 'vat_status' => 'valid'], vatReviewLines(), 'EUR');

    expect($result['vat_review'])->toBeFalse()->and($result['lines'][0]['category'])->toBe('AE')->and($result['lines'][0]['rate'])->toBe('0');
});

it('flags a reverse charge that rests only on a row from before the check', function () {
    $result = app(TaxEngine::class)->calculate(['country' => 'SK', 'customer_class' => 'b2b', 'vat_id' => 'SK1234567890', 'vat_status' => 'valid', 'vat_reason' => 'legacy_unverified'], vatReviewLines(), 'EUR');

    expect($result['vat_review'])->toBeTrue()->and($result['lines'][0]['category'])->toBe('AE');
});
