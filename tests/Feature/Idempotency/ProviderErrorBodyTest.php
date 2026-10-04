<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;

/*
 * Phase D5 — a vendor failure rendered for a customer named the vendor behind the service (`"provider":"ispconfig"`, and again
 * in the message prefix `[ispconfig:TRANSIENT]`). Customers are told what failed and whether to retry; which panel, registrar
 * or gateway it was is for staff (VendorNeutralityTest's rule, applied to error bodies). Staff in the staff console still see it.
 */

beforeEach(function () {
    $fail = fn () => throw new ProviderException('ispconfig', ProviderErrorCode::TRANSIENT, 'remote API timed out', retryAfterSeconds: 30);
    Route::middleware(['api', 'auth:sanctum'])->get('v1/peb-failure', $fail);
    Route::middleware(['api', 'auth:sanctum'])->get('v1/staff/peb-failure', $fail);
});

it('hides the provider from a customer, in the field and in the message, and keeps the rest of the contract', function () {
    [$owner] = $this->customerWithOrganization();

    $response = $this->actingAs($owner, 'sanctum')->getJson('/v1/peb-failure')->assertStatus(503)->assertHeader('Retry-After', '30');

    expect($response->json())->not->toHaveKey('provider')
        ->and($response->json())->toMatchArray(['error' => 'provider_transient', 'status' => 503, 'retryable' => true])
        ->and((string) $response->getContent())->not->toContain('ispconfig')
        ->and($response->json('message'))->toBe('remote API timed out');
});

it('hides it from a member of staff on a customer route as well: there they are the customer', function () {
    $staff = $this->staff('platform_owner');

    $response = $this->actingAs($staff, 'sanctum')->getJson('/v1/peb-failure')->assertStatus(503);

    expect($response->json())->not->toHaveKey('provider');
});

it('shows the provider to staff in the staff console', function () {
    $staff = $this->staff('platform_owner');

    $response = $this->actingAs($staff, 'sanctum')->getJson('/v1/staff/peb-failure')->assertStatus(503);

    expect($response->json())->toMatchArray(['error' => 'provider_transient', 'provider' => 'ispconfig', 'status' => 503])
        ->and($response->json('message'))->toContain('[ispconfig:TRANSIENT]');
});

it('does not show it to a customer who calls a staff route', function () {
    [$owner] = $this->customerWithOrganization();

    $body = (string) $this->actingAs($owner, 'sanctum')->getJson('/v1/staff/peb-failure')->getContent();

    expect($body)->not->toContain('ispconfig');
});
