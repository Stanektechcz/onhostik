<?php

declare(strict_types=1);

use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Tax\TaxEngine;
use Onhost\Platform\Money\Money;

beforeEach(function () {
    $this->seed(TaxRuleSeeder::class);
});

function taxLines(string $currency = 'CZK'): array
{
    return [['key' => 'web', 'net' => Money::decimal('189', $currency)], ['key' => 'domain', 'net' => Money::decimal('179', $currency), 'product_class' => 'domain']];
}

it('applies domestic Czech VAT for CZ customers', function () {
    $r = app(TaxEngine::class)->calculate(['country' => 'CZ', 'customer_class' => 'b2c'], taxLines(), 'CZK');
    expect($r['lines'][0]['rate'])->toBe('21')->and($r['lines'][0]['category'])->toBe('S')
        ->and($r['tax_total']->minor)->toBe(3969 + 3759)
        ->and($r['review_required'])->toBeFalse()
        ->and($r['calculation']->rule_version_id)->not->toBeNull();
});

it('reverse-charges validated EU B2B customers and taxes unvalidated ones at destination via OSS', function () {
    $engine = app(TaxEngine::class);
    $b2b = $engine->calculate(['country' => 'SK', 'customer_class' => 'b2b', 'vat_status' => 'valid'], taxLines('EUR'), 'EUR');
    expect($b2b['lines'][0]['rate'])->toBe('0')->and($b2b['lines'][0]['category'])->toBe('AE')->and($b2b['lines'][0]['note'])->toContain('Article 196');

    $b2c = $engine->calculate(['country' => 'SK', 'customer_class' => 'b2c'], taxLines('EUR'), 'EUR');
    expect($b2c['lines'][0]['rate'])->toBe('23')->and($b2c['lines'][0]['category'])->toBe('S');

    $invalid = $engine->calculate(['country' => 'DE', 'customer_class' => 'b2b', 'vat_status' => 'invalid'], taxLines('EUR'), 'EUR');
    expect($invalid['lines'][0]['rate'])->toBe('19')->and($invalid['review_required'])->toBeTrue();
});

it('treats non-EU customers as out of scope and flags evidence conflicts', function () {
    $engine = app(TaxEngine::class);
    $us = $engine->calculate(['country' => 'US', 'customer_class' => 'b2c'], taxLines('EUR'), 'EUR');
    expect($us['lines'][0]['rate'])->toBe('0')->and($us['lines'][0]['category'])->toBe('O');

    $conflict = $engine->calculate(['country' => 'AT', 'customer_class' => 'b2c', 'ip_country' => 'CZ'], taxLines('EUR'), 'EUR');
    expect($conflict['review_required'])->toBeTrue()->and($conflict['reasons'])->toContain('country evidence conflict: billing AT vs ip CZ');
});
