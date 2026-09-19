<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Services\ServiceFreshness;

/*
 * What we show about a service is the last thing the reconciler read from the panel (Brain card H325). When that
 * reading is old — the reconciler stopped, the panel's API is down — it must say so instead of looking current.
 */

beforeEach(fn () => Http::preventStrayRequests());

it('tells a fresh reading from a stale one and from one never taken', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');

    // just activated, nothing read yet: waiting for the first pass, not an alarm
    $service->forceFill(['activated_at' => now()->subMinutes(2), 'last_reconciled_at' => null, 'health' => []])->save();
    expect(ServiceFreshness::of($service->fresh()))->toMatchArray(['verdict' => 'pending', 'stale' => false, 'measured_at' => null]);

    // read a moment ago
    $service->forceFill(['last_reconciled_at' => now()->subMinutes(3), 'health' => ['status' => 'active', 'checked_at' => now()->subMinutes(3)->toISOString()]])->save();
    $fresh = ServiceFreshness::of($service->fresh());
    expect($fresh['verdict'])->toBe('fresh')->and($fresh['stale'])->toBeFalse()->and($fresh['age_seconds'])->toBeGreaterThanOrEqual(170);

    // the reconciler has missed more than three passes: the same "active" is now an old claim
    $service->forceFill(['last_reconciled_at' => now()->subHours(2), 'health' => ['status' => 'active', 'checked_at' => now()->subHours(2)->toISOString()]])->save();
    $stale = ServiceFreshness::of($service->fresh());
    expect($stale['verdict'])->toBe('stale')->and($stale['stale'])->toBeTrue()->and($stale['stale_after_seconds'])->toBe(2700);

    // long active and never read at all
    $service->forceFill(['activated_at' => now()->subDays(3), 'last_reconciled_at' => null, 'health' => []])->save();
    expect(ServiceFreshness::of($service->fresh()))->toMatchArray(['verdict' => 'never', 'stale' => true]);

    // a stricter SLA is read more often, so its reading goes stale sooner
    $service->forceFill(['sla_class' => 'premium'])->save();
    expect(ServiceFreshness::of($service->fresh())['stale_after_seconds'])->toBe(900);
});

it('carries the verdict in the customer API and names a stale reading in the panel rows', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $service->forceFill(['last_reconciled_at' => now()->subHours(5), 'health' => ['status' => 'active', 'checked_at' => now()->subHours(5)->toISOString()]])->save();
    $this->actingAs($owner, 'sanctum');

    $api = $this->getJson("/v1/services/{$service->id}")->assertOk()->json('data');
    expect($api['freshness']['verdict'])->toBe('stale')->and($api['freshness']['stale'])->toBeTrue()->and($api['freshness']['measured_at'])->not->toBeNull();

    $panel = $this->get('/surfaces/onhost-panel.js')->assertOk()->getContent();
    expect($panel)->toContain('zastaral'); // "stav z … (zastaralý)" on the service row
});
