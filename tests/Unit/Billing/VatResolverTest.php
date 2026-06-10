<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\VatScenario;
use App\Domains\Billing\Services\VatResolver;
use App\Domains\Customer\Models\Customer;

function makeCustomer(string $country, bool $vatValidated = false): Customer
{
    return Customer::make([
        'country_code'     => $country,
        'vat_number'       => $vatValidated ? 'CZ12345678' : null,
        'vat_validated_at' => $vatValidated ? now() : null,
    ]);
}

it('resolves Czech B2C at 21 %', function (): void {
    $resolver = new VatResolver();
    $customer = makeCustomer('CZ');

    expect($resolver->resolveScenario($customer))->toBe(VatScenario::CzechB2C)
        ->and($resolver->resolveRate($customer))->toBe(21.0);
});

it('resolves Czech B2B with validated VAT id at 21 %', function (): void {
    $resolver = new VatResolver();
    $customer = makeCustomer('CZ', vatValidated: true);

    expect($resolver->resolveScenario($customer))->toBe(VatScenario::CzechB2B)
        ->and($resolver->resolveRate($customer))->toBe(21.0);
});

it('resolves EU B2C to the OSS rate of the customer country', function (): void {
    $resolver = new VatResolver();
    $customer = makeCustomer('DE');

    expect($resolver->resolveScenario($customer))->toBe(VatScenario::EuB2C)
        ->and($resolver->resolveRate($customer))->toBe(19.0);
});

it('resolves EU B2B with validated VAT id to reverse charge at 0 %', function (): void {
    $resolver = new VatResolver();
    $customer = makeCustomer('DE', vatValidated: true);

    expect($resolver->resolveScenario($customer))->toBe(VatScenario::EuB2BReverseCharge)
        ->and($resolver->resolveRate($customer))->toBe(0.0);
});

it('resolves non-EU customers to 0 %', function (): void {
    $resolver = new VatResolver();
    $customer = makeCustomer('US');

    expect($resolver->resolveScenario($customer))->toBe(VatScenario::NonEu)
        ->and($resolver->resolveRate($customer))->toBe(0.0);
});
