<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Http;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Observability\ErrorReporter;
use Onhost\Platform\Observability\Tracer;

/*
 * Error tracking and traces without SDKs (audit §5q-2): unexpected exceptions become redacted Sentry envelopes tagged
 * with the correlation id; commands, operation steps and provider calls become OTLP spans of one trace per correlation
 * id. Both stay silent without an endpoint.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

it('ships unexpected exceptions to Sentry with the correlation tags and never the expected ones', function () {
    $reporter = app(ErrorReporter::class);
    expect($reporter->enabled())->toBeFalse()->and($reporter->report(new RuntimeException('boom')))->toBeNull();

    config()->set('onhost.observability.sentry_dsn', 'https://abc123@o1.ingest.sentry.io/42');
    Http::fake(['https://o1.ingest.sentry.io/api/42/envelope/' => Http::response(['id' => 'x'], 200)]);
    Context::add('correlation_id', 'corr-test-1');
    Context::add('command', 'wallet.topup');
    $id = $reporter->report(new RuntimeException('token=ptla_SECRETVALUE1234567890 failed'), ['secret' => 'hidden']);
    expect($id)->toMatch('/^[0-9a-f]{32}$/');
    Http::assertSent(function (Request $r) {
        $lines = explode("\n", $r->body());
        $event = json_decode($lines[2], true);

        return str_contains($r->header('X-Sentry-Auth')[0], 'sentry_key=abc123') && $r->header('Content-Type')[0] === 'application/x-sentry-envelope'
            && $event['tags']['correlation_id'] === 'corr-test-1' && $event['tags']['command'] === 'wallet.topup' && $event['exception']['values'][0]['type'] === RuntimeException::class
            && ! str_contains($lines[2], 'ptla_SECRETVALUE1234567890') && ! str_contains($lines[2], 'hidden');
    });
    expect($reporter->report(new DomainError('nope', 'expected', 422)))->toBeNull()->and(ErrorReporter::expected(new DomainError('nope', 'expected', 422)))->toBeTrue();
    Http::assertSentCount(1);
});

it('exports command, step and provider-call spans as one OTLP trace per correlation id', function () {
    $tracer = app(Tracer::class);
    expect($tracer)->toBe(app(Tracer::class))->and($tracer->enabled())->toBeFalse()->and($tracer->span('x', [], fn () => 7))->toBe(7)->and($tracer->flush())->toBe(0);

    config()->set('onhost.observability.otlp_endpoint', 'http://otel-collector:4318');
    config()->set('onhost.observability.otlp_headers', 'Authorization=Bearer t0k3n,X-Scope-OrgID=onhost');
    Http::fake(['http://otel-collector:4318/v1/traces' => Http::response([], 200)]);
    Context::add('correlation_id', 'corr-trace-9');
    [$user, $org] = $this->customerWithOrganization();
    $this->actingAs($user, 'sanctum')->withHeaders(['X-Organization' => $org->id, 'Idempotency-Key' => 'obs-1'])->postJson("/v1/organizations/{$org->id}/projects", ['name' => 'Tracing'])->assertCreated(); // a real write through the bus → a command span
    Context::add('correlation_id', 'corr-trace-9'); // the request middleware set its own; the rest of the test stays on one id
    $tracer->finished('provider.call proxmox vm.status', 120, ['onhost.provider' => 'proxmox', 'http.status_code' => 200, 'password' => 'never']);
    expect(fn () => $tracer->span('operation.step failing', ['onhost.operation' => 'op_1'], fn () => throw new RuntimeException('step broke')))->toThrow(RuntimeException::class);
    expect($tracer->flush())->toBeGreaterThanOrEqual(3);
    Http::assertSent(function (Request $r) {
        $spans = data_get($r->data(), 'resourceSpans.0.scopeSpans.0.spans', []);
        $names = array_column($spans, 'name');
        $failing = collect($spans)->firstWhere('name', 'operation.step failing');
        $call = collect($spans)->firstWhere('name', 'provider.call proxmox vm.status');
        $attrs = collect($call['attributes'] ?? [])->pluck('value', 'key');

        return $r->header('Authorization')[0] === 'Bearer t0k3n' && $r->header('X-Scope-OrgID')[0] === 'onhost'
            && collect($names)->contains(fn ($n) => str_starts_with($n, 'command organization.')) && $failing['status']['code'] === 2 && str_contains($failing['status']['message'], 'step broke')
            && $failing['traceId'] === md5('corr-trace-9') && $call['traceId'] === md5('corr-trace-9')
            && ($attrs['http.status_code']['intValue'] ?? null) === '200' && ! str_contains($r->body(), 'never')
            && data_get($r->data(), 'resourceSpans.0.resource.attributes.0.value.stringValue') === 'onhost-control-plane';
    });
    expect($tracer->flush())->toBe(0); // the buffer is empty after a flush
});
