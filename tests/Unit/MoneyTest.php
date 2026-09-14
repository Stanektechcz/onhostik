<?php

declare(strict_types=1);

use Onhost\Platform\Money\Currency;
use Onhost\Platform\Money\CurrencyMismatchException;
use Onhost\Platform\Money\Money;

it('parses decimals into minor units with half-up rounding', function () {
    expect(Money::decimal('1290', 'CZK')->minor)->toBe(129000)
        ->and(Money::decimal('1290.50', 'CZK')->minor)->toBe(129050)
        ->and(Money::decimal('0.005', 'EUR')->minor)->toBe(1)
        ->and(Money::decimal('-0.005', 'EUR')->minor)->toBe(-1)
        ->and(Money::decimal('12 400,10', 'CZK')->minor)->toBe(1240010);
});

it('computes VAT deterministically', function () {
    $net = Money::decimal('2790', 'CZK');
    expect($net->percent(21)->minor)->toBe(58590)
        ->and($net->add($net->percent(21))->toDecimal())->toBe('3375.90');
});

it('prorates by share without float drift', function () {
    $month = Money::decimal('1290', 'CZK');
    $tenDays = $month->share(10, 31);
    expect($tenDays->minor)->toBe(41613); // 1290 * 10 / 31 = 416.129… -> 416.13
});

it('formats for Czech locale', function () {
    expect(Money::decimal('12400', 'CZK')->format('cs'))->toBe('12 400 Kč')
        ->and(Money::decimal('12400.5', 'CZK')->format('cs'))->toBe('12 400,50 Kč')
        ->and(Money::minor(-129000, Currency::CZK)->format('cs'))->toBe('-1 290 Kč');
});

it('never mixes currencies silently', function () {
    Money::decimal('1', 'CZK')->add(Money::decimal('1', 'EUR'));
})->throws(CurrencyMismatchException::class);
