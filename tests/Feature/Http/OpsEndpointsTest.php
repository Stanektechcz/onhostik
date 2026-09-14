<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Incidents\Models\SlaProbe;

it('answers readiness with component checks and exports Prometheus metrics behind a token', function () {
    $health = $this->getJson('/healthz')->assertOk();
    expect($health->json('status'))->toBe('ok')->and($health->json('checks.database.ok'))->toBeTrue()->and($health->json('checks.cache.ok'))->toBeTrue()->and($health->json('checks.outbox.ok'))->toBeTrue();

    config(['onhost.metrics.token' => 'prom-token', 'onhost.metrics.allow_ips' => ['203.0.113.9']]);
    $this->get('/metrics')->assertUnauthorized();
    SlaProbe::query()->create(['key' => 'portal-cz', 'component_key' => 'portal', 'kind' => 'http', 'target' => 'https://onhost.cz/up', 'location' => 'probe-cz-external', 'interval_seconds' => 60, 'token_hash' => hash('sha256', 'x'), 'state' => 'active', 'last_seen_at' => now()]);
    DB::table('outbox_messages')->insert(['id' => 'obx_metrics_test_0000000000000001', 'aggregate_type' => 'test', 'aggregate_id' => 'x', 'name' => 'test.pending', 'payload' => '{}', 'correlation_id' => 'c', 'available_at' => now()->subSeconds(90), 'attempts' => 0, 'created_at' => now(), 'updated_at' => now()]);

    $metrics = $this->withToken('prom-token')->get('/metrics')->assertOk()->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
    $body = $metrics->getContent();
    expect($body)->toContain('# TYPE onhost_http_requests_total counter')->toContain('onhost_http_requests_total{family="web",status="2xx"}')
        ->toContain('onhost_outbox_pending_total 1')->toContain('onhost_probe_last_seen_timestamp{probe="portal-cz",component="portal",location="probe-cz-external"}')
        ->toContain('onhost_status_component_operational{component="portal",state="operational"} 1')->toContain('onhost_registrar_credit_czk');
    expect((int) preg_replace('/\D/', '', (string) preg_replace('/.*onhost_outbox_pending_oldest_seconds (\d+).*/s', '$1', $body)))->toBeGreaterThanOrEqual(89);
});

it('sends hardening headers with a CSP that allows only the vendored runtime and Google Fonts', function () {
    $response = $this->get('/')->assertOk();
    $csp = (string) $response->headers->get('Content-Security-Policy');
    expect($csp)->toContain("script-src 'self' 'unsafe-inline' 'unsafe-eval' blob:")->toContain('https://fonts.googleapis.com')->toContain("frame-ancestors 'self'")->not->toContain('unpkg.com');
    $response->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    expect($this->getJson('/v1/status')->assertOk()->headers->get('Content-Security-Policy'))->toBeNull(); // JSON responses carry the generic headers only
    $support = $this->get('/surfaces/support.js')->assertOk();
    expect($support->getContent())->toContain('/surfaces/vendor/react.production.min.js')->not->toContain('unpkg.com');
});

it('serves the OpenAPI contract the documentation page offers for download', function () {
    $response = $this->get('/openapi.yaml')->assertOk();
    expect((string) $response->headers->get('Content-Type'))->toContain('yaml')->and((string) file_get_contents($response->baseResponse->getFile()->getPathname()))->toContain('openapi: 3.1')->toContain('/services/{service}/game-files/upload');
});
