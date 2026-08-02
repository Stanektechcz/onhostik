<?php

declare(strict_types=1);

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Prometheus /api/metrics — disabled until a token is set; then token-gated and
 * exposing only operational gauges.
 */

it('is disabled (404) when no token is configured', function (): void {
    config(['metrics.token' => '']);

    $this->get('/api/metrics')->assertNotFound();
});

it('rejects a missing or wrong token with 403', function (): void {
    config(['metrics.token' => 'scrape-secret']);

    $this->get('/api/metrics')->assertForbidden();
    $this->withToken('wrong')->get('/api/metrics')->assertForbidden();
});

it('serves Prometheus-format gauges with the right token', function (): void {
    config(['metrics.token' => 'scrape-secret']);

    $response = $this->withToken('scrape-secret')->get('/api/metrics')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/plain')
        ->and($response->getContent())->toContain('onhost_up 1')
        ->toContain('# TYPE onhost_failed_jobs_total gauge')
        ->toContain('onhost_scheduler_stale');
});

it('also accepts the token as a query parameter', function (): void {
    config(['metrics.token' => 'scrape-secret']);

    $this->get('/api/metrics?token=scrape-secret')->assertOk();
});

it('exposes only operational gauges — no revenue or customer data', function (): void {
    config(['metrics.token' => 'scrape-secret']);

    $body = $this->withToken('scrape-secret')->get('/api/metrics')->getContent();

    expect($body)->not->toContain('revenue')->not->toContain('customer')->not->toContain('email');
});
