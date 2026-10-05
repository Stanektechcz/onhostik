<?php

declare(strict_types=1);

use App\Http\Presenters\Presenters;
use Illuminate\Support\Facades\Http;

/*
 * A failed service keeps the failure text in `health.error`, with the `[panel:CODE]` prefix the provider layer puts in front.
 * A customer reads the class of the failure, never the vendor (found by the E3 mail flow); staff keep the raw text.
 */

beforeEach(fn () => Http::preventStrayRequests());

it('shows a customer the failure without the vendor, and staff the raw text', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureMailService($org);
    $service->forceFill(['health' => ['status' => 'failed', 'error' => '[ispconfig:CONFLICT] The mail domain shop.cz already exists', 'checked_at' => now()->toISOString()]])->save();

    $customer = Presenters::service($service->refresh());
    expect($customer['health']['status'])->toBe('failed')->and($customer['health']['error'])->not->toContain('ispconfig')->and($customer['health']['error'])->toBe('Zdroj se stejným názvem už existuje.');
    expect(Presenters::service($service, true)['health']['error'])->toContain('[ispconfig:CONFLICT]');
});
