<?php

declare(strict_types=1);

use Onhost\Domain\Catalog\PricingRules;
use Onhost\Domain\Services\DeletionPolicy;

/*
 * Phase P, owner decisions P-R29 (LG-5, L-27) and P-R30 (LG-6, L-16), 2026-10-13: a customer from another EU country pays the same
 * price as at home (geo-blocking, Regulation (EU) 2018/302 art. 4(1)(b) names web hosting), and downloading the final archive costs
 * nothing from launch (Data Act art. 29: no switching or egress charges from 12 Jan 2027; until then at most cost — the owner chose
 * none). Both are only defaults: staff may still change them through the four-eyes price paths, and an environment may override.
 */
it('prices the EU region like home by default (P-R29)', function () {
    $eu = app(PricingRules::class)->regionFor('DE');

    expect($eu['key'])->toBe('eu')->and($eu['adjust_pct'])->toBe(0.0);
});

it('lets a customer download the final archive without a fee by default (P-R30)', function () {
    $policy = app(DeletionPolicy::class);

    expect($policy->downloadFeeMinor('CZK'))->toBe(0)->and($policy->downloadFeeMinor('EUR'))->toBe(0)
        ->and($policy->all()['download_fee_minor'])->toBe(['CZK' => 0, 'EUR' => 0]);
});
