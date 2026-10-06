<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Invoicing\Models\LegalEntity;
use Onhost\Domain\Platform\GoLiveChecks;
use Onhost\Domain\Tax\TaxEngine;
use Onhost\Domain\Tax\VatPayerMode;
use Onhost\Platform\Money\Money;

/*
 * H0, owner decision H-R0 (2026-10-06): ONhost is not a VAT payer now and becomes one later. The declaration defaults to
 * "not a payer" (ONHOST_VAT_PAYER=false), a legal entity created now is a non-payer, and a platform without its legal entity
 * follows the declaration instead of charging VAT. Becoming a payer stays finance's switch with a step-up and a second person
 * (G2VatPayerTest "H1"). The suite runs as a payer (phpunit.xml) — here the production default is put back.
 */

/** Runs `$body` with ONHOST_VAT_PAYER absent from the environment, the way a server without the line sees it. */
function h0WithoutVatPayerEnv(Closure $body): mixed
{
    $saved = [getenv('ONHOST_VAT_PAYER'), $_ENV['ONHOST_VAT_PAYER'] ?? null, $_SERVER['ONHOST_VAT_PAYER'] ?? null];
    putenv('ONHOST_VAT_PAYER');
    unset($_ENV['ONHOST_VAT_PAYER'], $_SERVER['ONHOST_VAT_PAYER']);
    try {
        return $body();
    } finally {
        if ($saved[0] !== false) {
            putenv('ONHOST_VAT_PAYER='.$saved[0]);
        }
        if ($saved[1] !== null) {
            $_ENV['ONHOST_VAT_PAYER'] = $saved[1];
        }
        if ($saved[2] !== null) {
            $_SERVER['ONHOST_VAT_PAYER'] = $saved[2];
        }
    }
}

it('declares the seller a non-payer when ONHOST_VAT_PAYER is not set', function () {
    $declared = h0WithoutVatPayerEnv(fn () => (require base_path('config/vat.php'))['payer']);
    expect($declared)->toBeFalse();
    expect((string) file_get_contents(base_path('.env.example')))->toContain("\nONHOST_VAT_PAYER=false");
});

it('creates the legal entity as a non-payer and charges no VAT, the doctor agreeing', function () {
    config(['vat.payer' => false]);
    $this->seed([TaxRuleSeeder::class, LegalEntitySeeder::class]);

    expect(LegalEntity::query()->findOrFail('onhost-cz')->vat_payer)->toBeFalse()
        ->and(app(VatPayerMode::class)->isPayer())->toBeFalse();
    $report = app(VatPayerMode::class)->report();
    expect($report['consistent'])->toBeTrue()->and($report['detail'])->toContain('not a VAT payer');

    $decision = app(TaxEngine::class)->calculate(['country' => 'CZ', 'customer_class' => 'b2c'], [['key' => 'a', 'net' => Money::minor(10000, 'CZK')]], 'CZK');
    expect($decision['lines'][0]['category'])->toBe(TaxEngine::CAT_EXEMPT);
});

it('follows the declaration while no legal entity exists yet, instead of charging VAT', function () {
    LegalEntity::query()->delete();
    VatPayerMode::forget();
    config(['vat.payer' => false]);
    expect(VatPayerMode::legalEntityIsPayer())->toBeFalse();

    VatPayerMode::forget();
    config(['vat.payer' => true]);
    expect(VatPayerMode::legalEntityIsPayer())->toBeTrue();
});

it('never switches an existing legal entity by seeding again: becoming a payer later is the staff switch', function () {
    config(['vat.payer' => false]);
    $this->seed(LegalEntitySeeder::class);
    config(['vat.payer' => true]);
    $this->seed(LegalEntitySeeder::class);
    VatPayerMode::forget();

    expect(LegalEntity::query()->findOrFail('onhost-cz')->vat_payer)->toBeFalse();
    $report = app(VatPayerMode::class)->report();
    expect($report['consistent'])->toBeFalse()->and($report['remedy'])->toContain('/v1/staff/tax/vat-payer-mode');
});

it('fails the go-live check while the legal entity or its VAT mode is missing (review M4)', function () {
    $row = fn () => collect((new GoLiveChecks(production: true))->rows())->firstWhere('check', 'legal entity carries its VAT mode');

    LegalEntity::query()->delete();
    $missing = $row();
    expect($missing['ok'])->toBeFalse()->and($missing['blocking'])->toBeTrue()->and($missing['remedy'])->toContain('onhost:production:prepare --legal');

    config(['vat.payer' => false]);
    $this->seed(LegalEntitySeeder::class);
    $present = $row();
    expect($present['ok'])->toBeTrue()->and($present['detail'])->toContain('non-payer');
});
