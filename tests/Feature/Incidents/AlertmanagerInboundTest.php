<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Incidents\Models\OnCallAlert;
use Onhost\Platform\Ops\PlatformBackup;

/*
 * Prometheus rules become on-call alerts (infra/monitoring): Alertmanager posts firing and resolved groups with the
 * inbound secret; a firing rule opens one alert per subject (paged by severity), a resolved one closes it; the metrics
 * endpoint carries the series the new rules watch (backups, automation heartbeats, on-call).
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

it('turns Alertmanager notifications into on-call alerts and back', function () {
    $payload = ['alerts' => [
        ['status' => 'firing', 'labels' => ['alertname' => 'OnhostProviderDown', 'severity' => 'page', 'instance_key' => 'proxmox-cz1', 'job' => 'onhost-app'], 'annotations' => ['summary' => 'Provider proxmox-cz1 is down', 'runbook' => 'docs/runbooks/provider-outage.md']],
        ['status' => 'firing', 'labels' => ['alertname' => 'OnhostBackupStale', 'severity' => 'ticket'], 'annotations' => ['summary' => 'No verified platform backup for 26 h']],
        ['status' => 'firing', 'labels' => ['severity' => 'page']],
    ]];
    $this->postJson('/v1/webhooks/alertmanager', $payload)->assertStatus(401)->assertJsonPath('error', 'oncall_inbound_unauthorized');
    config()->set('onhost.oncall.inbound_secret', 'am-secret');
    $this->postJson('/v1/webhooks/alertmanager', $payload, ['Authorization' => 'Bearer wrong'])->assertStatus(401);
    $this->postJson('/v1/webhooks/alertmanager', $payload, ['Authorization' => 'Bearer am-secret'])->assertStatus(202)->assertJsonPath('data', ['opened' => 2, 'resolved' => 0, 'ignored' => 1]);

    $down = OnCallAlert::query()->where('event', 'prometheus.OnhostProviderDown')->firstOrFail();
    expect($down->severity)->toBe('hot')->and($down->state)->toBe('open')->and($down->title)->toBe('Provider proxmox-cz1 is down')->and($down->body)->toContain('instance_key=proxmox-cz1')->toContain('provider-outage.md');
    expect(OnCallAlert::query()->where('event', 'prometheus.OnhostBackupStale')->value('severity'))->toBe('warn');
    // firing again does not duplicate; resolved closes
    $this->postJson('/v1/webhooks/alertmanager', $payload, ['Authorization' => 'Bearer am-secret'])->assertStatus(202);
    expect(OnCallAlert::query()->count())->toBe(2)->and($down->refresh()->meta['repeats'])->toBe(1);
    $resolved = ['alerts' => [['status' => 'resolved', 'labels' => ['alertname' => 'OnhostProviderDown', 'severity' => 'page', 'instance_key' => 'proxmox-cz1']]]];
    $this->postJson('/v1/webhooks/alertmanager', $resolved, ['Authorization' => 'Bearer am-secret'])->assertStatus(202)->assertJsonPath('data.resolved', 1);
    expect($down->refresh()->state)->toBe('resolved')->and($down->resolved_by)->toBe('alertmanager');
});

it('exports the operations series the alert rules and the dashboard read', function () {
    config()->set('onhost.metrics.token', 'metrics-token');
    // one instant, read twice: `now()` on both lines made this test red whenever the second ticked over between them
    $verifiedAt = now()->subHours(2);
    cache()->forever(PlatformBackup::VERIFIED_KEY, ['set' => 'platform-backups/x', 'at' => $verifiedAt->toIso8601String()]);
    $body = $this->get('/metrics', ['Authorization' => 'Bearer metrics-token'])->assertOk()->getContent();
    expect($body)->toContain('onhost_platform_backup_verified_timestamp '.$verifiedAt->getTimestamp())->toContain('onhost_automation_alive{machine="scheduler"}')->toContain('onhost_automation_alive{machine="worker"} 1')
        ->toContain('onhost_oncall_assigned 0')->toContain('# TYPE onhost_oncall_alerts_active gauge')->not->toContain("\nonhost_virus_scanner_up ");
    $rules = (string) file_get_contents(base_path('infra/monitoring/slo-alerts.yml'));
    foreach (['OnhostBackupStale', 'OnhostAutomationDead', 'OnhostVirusScannerDown', 'OnhostOnCallUnacknowledged', 'OnhostNobodyOnCall'] as $rule) {
        expect($rules)->toContain('alert: '.$rule);
    }
    expect($rules)->toContain('{{ $labels.machine }}');
    $dashboard = json_decode((string) file_get_contents(base_path('infra/monitoring/grafana/dashboards/onhost-operations.json')), true);
    expect($dashboard['uid'])->toBe('onhost-operations')->and(count($dashboard['panels']))->toBeGreaterThan(10);
});
