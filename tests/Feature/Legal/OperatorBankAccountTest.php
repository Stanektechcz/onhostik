<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Illuminate\Support\Facades\Artisan;
use Onhost\Domain\Invoicing\Models\LegalEntity;
use Onhost\Platform\Money\Money;
use Onhost\Providers\Payments\Bank\BankTransferPaymentProvider;

/*
 * I-R15 (owner, 2026-10-08): the operator's business account 6478243359/0800 (IBAN CZ34 0800 0000 0064 7824 3359, BIC GIBACZPX) is
 * the default of the legal entity and of the bank-transfer instructions, so `onhost:production:prepare --legal` completes and the
 * doctor's "legal entity bank details real" passes — without anybody editing .env on the server.
 */

const IR15_IBAN = 'CZ3408000000006478243359';

/** @return array<string, mixed> */
function ir15DoctorRow(string $check): array
{
    Artisan::call('onhost:doctor', ['--json' => true]);

    return (array) collect(json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR)['checks'])->firstWhere('check', $check);
}

it('carries the owner account as the default of the legal entity and of the transfer instructions', function () {
    expect(config('onhost.legal_entity'))->toMatchArray(['iban' => IR15_IBAN, 'bic' => 'GIBACZPX', 'bank_account' => '6478243359/0800'])
        ->and(config('onhost.payments.bank'))->toMatchArray(['iban' => IR15_IBAN, 'bic' => 'GIBACZPX', 'account_number' => '6478243359/0800']);

    // the IBAN is the same account as the domestic number (bank 0800, number 6478243359) and its check digits are right
    expect(substr(IR15_IBAN, 4, 4))->toBe('0800')->and(ltrim(substr(IR15_IBAN, 8), '0'))->toBe('6478243359');
    $digits = '';
    foreach (str_split(substr(IR15_IBAN, 4).substr(IR15_IBAN, 0, 4)) as $c) {
        $digits .= ctype_alpha($c) ? (string) (ord($c) - 55) : $c;
    }
    expect(bcmod($digits, '97'))->toBe('1');
});

it('seeds the account, and the doctor says the bank details are real', function () {
    $this->seed(LegalEntitySeeder::class);

    $entity = LegalEntity::query()->findOrFail('onhost-cz');
    expect($entity->iban)->toBe(IR15_IBAN)->and($entity->bic)->toBe('GIBACZPX')->and($entity->bank_account)->toBe('6478243359/0800');
    expect(ir15DoctorRow('legal entity bank details real')['status'])->toBe('OK')
        ->and(ir15DoctorRow('bank account for transfers')['status'])->toBe('OK');
});

it('does not take an IBAN with wrong check digits for real bank details', function () {
    $this->seed(LegalEntitySeeder::class);
    LegalEntity::query()->update(['iban' => 'CZ3508000000006478243359']);

    $row = ir15DoctorRow('legal entity bank details real');
    expect($row['status'])->toBe('WARN')->and($row['detail'])->toContain('check digits');
});

it('lets production:prepare --legal write the legal entity without any environment variable', function () {
    config(['vat.payer' => false]); // the production declaration (H-R0); the suite runs as a payer (phpunit.xml)
    $this->artisan('onhost:production:prepare --legal')->expectsOutputToContain('Legal entity written from the environment.');

    expect(LegalEntity::query()->findOrFail('onhost-cz')->only(['name', 'ico', 'iban']))->toBe(['name' => 'Adrian Staněk', 'ico' => '08094616', 'iban' => IR15_IBAN]);
});

it('still refuses to write placeholders when the account is blanked in the configuration', function () {
    config(['vat.payer' => false, 'onhost.legal_entity.iban' => '', 'onhost.legal_entity.bank_account' => '']);

    $this->artisan('onhost:production:prepare --legal')->expectsOutputToContain('ONHOST_BANK_IBAN, ONHOST_BANK_ACCOUNT')->assertExitCode(1);
    expect(LegalEntity::query()->count())->toBe(0);
});

it('prints the account and the QR payment string on a bank transfer', function () {
    $intent = app(BankTransferPaymentProvider::class)->createPaymentIntent(Money::decimal('1210.00', 'CZK'), ['reference' => 'OH-2026-1234', 'description' => 'Objednávka OH-2026-1234']);
    $instructions = $intent['raw']['instructions'];

    expect($instructions)->toMatchArray(['iban' => IR15_IBAN, 'bic' => 'GIBACZPX', 'account_number' => '6478243359/0800', 'variable_symbol' => '20261234'])
        ->and($instructions['qr_spd'])->toStartWith('SPD*1.0*ACC:'.IR15_IBAN.'*AM:1210.00*CC:CZK*X-VS:20261234');
});
