<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Onhost\Domain\Incidents\Models\SlaProbe;
use Onhost\Domain\Incidents\SlaService;

/*
 * TASK-0098 (goal 3): staff commands keyed by `now()->timestamp` replay the first answer to a repeat in the same second. Where
 * that answer is a secret handed out once, the replay store keeps it masked (IdempotencyStore + Redactor) — so the repeat got a
 * masked token for a probe whose real token it had never seen. Registering a probe answers with its token: one request, one key
 * (as the console token, ServiceController::consoleToken), and each answer is a token that works.
 */

it('hands out a working probe token to a repeat of the same registration within the same second', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-05 10:00:00.100'));
    $this->actingAs($this->staff('sre'), 'sanctum');
    $body = ['key' => 'portal-https-sss', 'component_key' => 'portal', 'kind' => 'http', 'target' => 'https://portal.onhost.cz/healthz', 'location' => 'probe-cz-external', 'interval_seconds' => 60];

    $token = fn ($response) => $response->assertCreated()->json('token') ?? $response->json('data.token');
    $first = $token($this->postJson('/v1/staff/probes', $body));
    Carbon::setTestNow(Carbon::parse('2026-10-05 10:00:00.700')); // the same second: the old key was `probe.register:portal-https-sss:<same timestamp>`
    $second = $token($this->postJson('/v1/staff/probes', $body));
    Carbon::setTestNow();

    expect($first)->toStartWith('prb_')->and($second)->toStartWith('prb_')->and($second)->not->toBe($first);
    // the token of the last answer is the one the probe now accepts — the operator holds a working one
    expect(app(SlaService::class)->authenticateProbe($second)?->key)->toBe('portal-https-sss')
        ->and(SlaProbe::query()->where('key', 'portal-https-sss')->count())->toBe(1);
});
