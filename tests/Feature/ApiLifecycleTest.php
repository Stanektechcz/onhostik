<?php

declare(strict_types=1);

/**
 * Audit 103 — API version lifecycle: a public changelog and in-band deprecation
 * headers so an integration is warned before a version disappears, not after.
 */

// ── public changelog ────────────────────────────────────────────────────────────

it('serves the changelog without authentication', function (): void {
    $this->getJson('/api/changelog')
        ->assertOk()
        ->assertJsonStructure([
            'versions'  => [['version', 'status', 'sunset']],
            'changelog' => [['date', 'version', 'type', 'summary']],
        ]);
});

it('reports each configured version’s status in the changelog', function (): void {
    config(['api.versions' => [
        'v1' => ['status' => 'deprecated', 'sunset' => '2027-01-01'],
        'v2' => ['status' => 'active', 'sunset' => null],
    ]]);

    $versions = collect($this->getJson('/api/changelog')->json('versions'))
        ->keyBy('version');

    expect($versions['v1']['status'])->toBe('deprecated')
        ->and($versions['v1']['sunset'])->toBe('2027-01-01')
        ->and($versions['v2']['status'])->toBe('active');
});

// ── deprecation headers ───────────────────────────────────────────────────────

it('adds no deprecation header while a version is active', function (): void {
    config(['api.versions.v1.status' => 'active']);

    $token = customerUser()->createToken('t')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/profile')->assertOk();

    expect($response->headers->has('Deprecation'))->toBeFalse();
    // The latest-version pointer is always advertised, active or not.
    expect($response->headers->get('Link'))->toContain('/api/changelog');
});

it('marks a deprecated version with Deprecation and Sunset headers', function (): void {
    config(['api.versions.v1' => ['status' => 'deprecated', 'sunset' => '2027-01-01']]);

    $token = customerUser()->createToken('t')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/profile')->assertOk();

    expect($response->headers->get('Deprecation'))->toBe('true')
        // RFC 8594 wants an HTTP-date, not the raw ISO string.
        ->and($response->headers->get('Sunset'))->toContain('2027')
        ->and($response->headers->get('Sunset'))->toContain('GMT');
});

it('does not 500 on a malformed sunset date — it just omits Sunset', function (): void {
    config(['api.versions.v1' => ['status' => 'deprecated', 'sunset' => 'not-a-date']]);

    $token = customerUser()->createToken('t')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/profile')->assertOk();

    // Still flagged deprecated; a bad config value must not break a live call.
    expect($response->headers->get('Deprecation'))->toBe('true')
        ->and($response->headers->has('Sunset'))->toBeFalse();
});
