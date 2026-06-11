<?php

declare(strict_types=1);

use App\Domains\Provisioning\Drivers\WedosMockRegistrar;

it('reports unlisted domains as available', function (): void {
    $result = (new WedosMockRegistrar())->checkDomain('uplne-nova-domena.cz');

    expect($result->available)->toBeTrue()
        ->and($result->fqdn)->toBe('uplne-nova-domena.cz');
});

it('reports listed and taken-prefixed domains as taken', function (string $fqdn): void {
    $result = (new WedosMockRegistrar())->checkDomain($fqdn);

    expect($result->available)->toBeFalse()
        ->and($result->reason)->toBe('taken');
})->with(['onhost.cz', 'google.cz', 'taken-cokoliv.cz']);

it('rejects invalid syntax and unsupported TLDs', function (): void {
    $registrar = new WedosMockRegistrar();

    expect($registrar->checkDomain('-spatny.cz')->reason)->toBe('invalid_syntax')
        ->and($registrar->checkDomain('bez-tecky')->reason)->toBe('invalid_syntax')
        ->and($registrar->checkDomain('neco.xyz')->reason)->toBe('unsupported_tld');
});

it('registers an available domain with mock identifiers', function (): void {
    $result = (new WedosMockRegistrar())->registerDomain('registrace-test.cz');

    expect($result->success)->toBeTrue()
        ->and((string) $result->externalId)->toStartWith('MOCK-WD-')
        ->and($result->metadata['nameservers'])->toBe(['ns1.onhost.cz', 'ns2.onhost.cz'])
        ->and($result->metadata['mock'])->toBeTrue();
});

it('fails registration for a taken domain', function (): void {
    $result = (new WedosMockRegistrar())->registerDomain('onhost.cz');

    expect($result->success)->toBeFalse()
        ->and((string) $result->errorMessage)->toContain('taken');
});

it('supports one-shot simulated registration failure', function (): void {
    $result = (new WedosMockRegistrar())->registerDomain('jinak-volna.cz', ['simulate_failure' => true]);

    expect($result->success)->toBeFalse()
        ->and((string) $result->errorMessage)->toContain('Simulated');
});
