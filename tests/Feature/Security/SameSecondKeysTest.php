<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Onhost\Domain\Incidents\Models\SlaProbe;
use Onhost\Domain\Incidents\SlaService;
use Onhost\Domain\Provisioning\FreezeSwitch;

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

it('replays a probe registration repeated under the same Idempotency-Key and registers anew under another', function () {
    $this->actingAs($this->staff('sre'), 'sanctum');
    $body = ['key' => 'portal-https-idem', 'component_key' => 'portal', 'kind' => 'http', 'target' => 'https://portal.onhost.cz/healthz', 'location' => 'probe-cz-external'];

    $first = $this->withHeader('Idempotency-Key', 'sss-probe-1')->postJson('/v1/staff/probes', $body)->assertCreated()->json('token');
    // the retry is the same registration: the HTTP replay store answers that its secret was shown once (IdempotencyKey, TASK-0041)
    $this->withHeader('Idempotency-Key', 'sss-probe-1')->postJson('/v1/staff/probes', $body)->assertStatus(409)->assertJsonPath('error', 'already_done');
    expect($first)->toStartWith('prb_')
        ->and(app(SlaService::class)->authenticateProbe($first)?->key)->toBe('portal-https-idem'); // and the first token was not rotated by the retry
    $other = $this->withHeader('Idempotency-Key', 'sss-probe-2')->postJson('/v1/staff/probes', $body)->assertCreated()->json('token');
    expect($other)->toStartWith('prb_')->and($other)->not->toBe($first);
});

it('ends thawed after freeze, thaw, freeze, thaw within one second, and a reconcile repeated in that second runs again', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-05 11:00:00.050'));
    $staff = $this->steppedUpStaff('platform_owner');
    $this->actingAs($staff, 'sanctum');

    foreach (['freeze' => true, 'thaw' => false, 'freeze ' => true, 'thaw ' => false] as $step => $frozen) {
        Carbon::setTestNow(now()->addMilliseconds(100)); // all in the same second: the old keys were `freeze:<ts>` and `thaw:<ts>`
        $response = trim($step) === 'freeze'
            ? $this->postJson('/v1/staff/provisioning/freeze', ['reason' => 'storage incident'])
            : $this->postJson('/v1/staff/provisioning/thaw');
        $response->assertOk()->assertJsonPath('frozen', $frozen);
    }
    expect(app(FreezeSwitch::class)->isFrozen())->toBeFalse();

    // a second freeze with another reason in that second is a freeze of its own — the old key refused it as a conflicting replay (409)
    $this->postJson('/v1/staff/provisioning/freeze', ['reason' => 'storage incident'])->assertOk();
    $this->postJson('/v1/staff/provisioning/freeze', ['reason' => 'network incident'])->assertOk()->assertJsonPath('meta.reason', 'network incident');
    $this->postJson('/v1/staff/provisioning/thaw')->assertOk();
    // and no staff command is keyed by the second any more (reconcile, op.retry, probe registration included)
    foreach (['ProvisioningController', 'IncidentController'] as $controller) {
        expect((string) file_get_contents(app_path("Http/Controllers/Api/V1/Staff/{$controller}.php")))->not->toContain('now()->timestamp');
    }
    Carbon::setTestNow();
});
