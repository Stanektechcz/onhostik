<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Providers\Contracts\EInvoiceProvider;
use Onhost\Providers\Peppol\NullEInvoiceProvider;

/*
 * W7-1 (Peppol layer 1): the driver `null` is the default e-invoice provider. It keeps the shape of the contract and never
 * reaches the network; an unknown driver is refused when it is resolved, never silently replaced.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake();
});

it('resolves the null driver by default and keeps the contract shape without any request', function () {
    config(['onhost.einvoice.driver' => 'null']);
    $provider = app(EInvoiceProvider::class);
    expect($provider)->toBeInstanceOf(NullEInvoiceProvider::class)->and($provider::providerKey())->toBe('null');

    $delivered = $provider->deliver('FA-2026-0001', '<Invoice/>', ['organization_id' => 'org_x']);
    expect(array_keys($delivered))->toBe(['delivery_id', 'state', 'raw'])
        ->and($delivered['delivery_id'])->toBe('null:'.sha1('FA-2026-0001'))
        ->and($delivered['state'])->toBe('skipped')
        ->and($delivered['raw'])->toBe([]);

    $status = $provider->status($delivered['delivery_id']);
    expect(array_keys($status))->toBe(['state', 'detail', 'raw'])->and($status['state'])->toBe('skipped')->and($status['detail'])->toBeNull()->and($status['raw'])->toBe([]);

    $validated = $provider->validate('<Invoice/>');
    expect(array_keys($validated))->toBe(['valid', 'errors'])->and($validated['valid'])->toBeFalse()
        ->and($validated['errors'])->toBe(['no e-invoice validator configured (driver null)']);

    Http::assertNothingSent();
});

it('refuses an unknown or not yet implemented e-invoice driver instead of falling back', function (string $driver) {
    config(['onhost.einvoice.driver' => $driver]);

    expect(fn () => app(EInvoiceProvider::class))->toThrow(RuntimeException::class, "E-invoice driver '{$driver}' is not implemented");
    Http::assertNothingSent();
})->with(['peppol', 'nonsense']);
