<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;
use Onhost\Domain\Services\Access\ServiceAccessService;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\ManagedCertificate;
use Onhost\Domain\Services\Models\UptimeMonitor;
use Onhost\Domain\Services\ServiceHealthCheck;
use Onhost\Platform\Commands\CommandContext;

/*
 * "Is my service all right?" (ServiceHealthCheck): one pass over what the platform already knows — no panel is asked —
 * with a verdict and a sentence per finding. The assistant answers it with a model or without one, for the services the
 * person may see; nothing about money is in it, so a guest a service was shared with may read it too.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Queue::fake();
    config(['onhost.ai.enabled' => false]); // the rules answer: staging may run without a model
});

it('finds what is wrong with a service from the platform\'s own records and says it the same way to everybody', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'aapanel');
    $check = app(ServiceHealthCheck::class);

    // a fresh site: runs, but nothing protects it yet
    $first = $check->run($site);
    expect($first['verdict'])->toBe('warn')->and(collect($first['findings'])->pluck('level', 'key')->all())->toBe(['state' => 'ok', 'backup' => 'warn', 'certificate' => 'warn', 'monitor' => 'warn']);

    // backed up last night, certificate running out, the monitor sees it down, an action failed, the disk is nearly full
    Backup::query()->create(['service_id' => $site->id, 'organization_id' => $org->id, 'kind' => 'scheduled', 'state' => 'completed', 'started_at' => now()->subHours(9), 'finished_at' => now()->subHours(9)]);
    Backup::query()->create(['service_id' => $site->id, 'organization_id' => $org->id, 'kind' => 'final', 'state' => 'completed', 'started_at' => now(), 'finished_at' => now()]); // the archive of a cancellation is not "a backup of the living site"
    ManagedCertificate::query()->create(['service_id' => $site->id, 'organization_id' => $org->id, 'domains' => ['shop.cz'], 'state' => 'issued', 'issued_at' => now()->subDays(85), 'expires_at' => now()->addDays(5)]);
    UptimeMonitor::query()->create(['service_id' => $site->id, 'organization_id' => $org->id, 'url' => 'https://shop.cz', 'enabled' => true, 'state' => 'down', 'interval_seconds' => 300]);
    Operation::query()->create(['service_id' => $site->id, 'organization_id' => $org->id, 'kind' => 'service.action', 'workflow' => ServiceActionWorkflow::class, 'state' => Operation::FAILED, 'step' => 0, 'steps_total' => 1, 'actor_type' => 'user',
        'idempotency_key' => 'hc-1', 'correlation_id' => 'c', 'desired' => ['action' => 'php.set'], 'queued_at' => now()->subHours(2), 'finished_at' => now()->subHours(2), 'error' => ['message' => 'x']]);
    $site->forceFill(['tags' => array_merge((array) $site->tags, ['usage' => ['level' => 'critical', 'metrics' => ['disk' => ['used' => 47, 'limit' => 50, 'pct' => 94]], 'checked_at' => now()->toIso8601String()]])])->save();

    $second = $check->run($site->fresh());
    expect($second['verdict'])->toBe('bad')->and(collect($second['findings'])->pluck('level', 'key')->all())->toBe(['state' => 'ok', 'backup' => 'ok', 'certificate' => 'warn', 'monitor' => 'bad', 'operations' => 'warn', 'usage' => 'bad']);
    $text = ServiceHealthCheck::text($second, 'cs');
    expect($text)->toContain('shop.cz')->toContain('Monitoring hlásí výpadek')->toContain('vyprší za')->toContain('94 %')->toContain('php.set')
        ->and(strpos($text, 'Monitoring hlásí výpadek'))->toBeLessThan(strpos($text, 'Služba je aktivní')); // the worst first
    expect(ServiceHealthCheck::text($second, 'en'))->toContain('Monitoring reports an outage')->not->toContain('Monitoring hlásí');

    // the API gives the same to whoever may see the service — and to nobody else
    $this->actingAs($owner, 'sanctum')->getJson("/v1/services/{$site->id}/health")->assertOk()->assertJsonPath('data.verdict', 'bad')->assertJsonPath('data.findings.2.key', 'certificate');
    [$stranger] = $this->customerWithOrganization();
    $this->actingAs($stranger, 'sanctum')->getJson("/v1/services/{$site->id}/health")->assertForbidden();
});

it('answers "zkontroluj mi web" in the chat without a model, for the service the person named or the only one they see', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'aapanel');
    $mail = featureMailService($org, 'posta-firma.cz');
    $this->actingAs($owner, 'sanctum')->withHeader('X-Organization', $org->id);

    $named = $this->postJson('/v1/assistant/chat', ['text' => 'Zkontroluj mi prosím shop.cz, je všechno v pořádku?'])->assertOk()->json('data');
    expect($named['text'])->toContain('shop.cz')->toContain('Služba je aktivní')->toContain('žádnou dokončenou zálohu')->not->toContain('posta-firma.cz');

    // two services and none named: it asks which one instead of guessing
    $which = $this->postJson('/v1/assistant/chat', ['text' => 'udělej mi kontrolu služby'])->assertOk()->json('data');
    expect($which['text'])->toContain('Kterou službu')->toContain('shop.cz')->toContain('posta-firma.cz');

    // "check my invoice" is a question about money, not about a service
    $money = $this->postJson('/v1/assistant/chat', ['text' => 'Zkontrolujte mi prosím fakturu za shop.cz'])->assertOk()->json('data');
    expect($money['text'])->not->toContain('Služba je aktivní');

    // a guest sees one shared service: that is the one checked — and never the other
    $guest = $this->customer(['email' => 'kontrola-host@example.cz']);
    $access = app(ServiceAccessService::class);
    $access->share($org, $site, 'kontrola-host@example.cz', ['view', 'assistant'], $this->contextFor($owner, $org, 'totp'));
    app(OrganizationService::class)->attachMember($org, $guest, 'guest', CommandContext::system('test'), true);
    $access->activatePending($guest, $org);
    $this->actingAs($guest, 'sanctum');
    $shared = $this->postJson('/v1/assistant/chat', ['text' => 'is everything ok?', 'locale' => 'en'])->assertOk()->json('data');
    expect($shared['text'])->toContain('shop.cz')->toContain('The service is active')->not->toContain('posta-firma');
    $this->getJson("/v1/services/{$mail->id}/health")->assertForbidden();
});
