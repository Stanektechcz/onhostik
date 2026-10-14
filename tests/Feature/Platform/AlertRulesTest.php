<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/*
 * P12 (phase Q): an alert is only as good as the metric it reads and the test that proves it fires. Every alert of
 * infra/monitoring/slo-alerts.yml has a unit test in slo-alerts.test.yml (CI runs `promtool test rules` on it), and every
 * `onhost_*` series an alert or a recording rule reads is one /metrics exports (HealthController) — an alert on a metric nobody
 * collects never fires.
 */

/** @return array<string, string> alert => expression */
function alertRulesAlerts(): array
{
    $rules = Yaml::parseFile(base_path('infra/monitoring/slo-alerts.yml'));
    $out = [];
    foreach ($rules['groups'] as $group) {
        foreach ($group['rules'] as $rule) {
            if (isset($rule['alert'])) {
                $out[$rule['alert']] = (string) $rule['expr'];
            }
        }
    }

    return $out;
}

it('has a promtool unit test for every alert', function () {
    $tests = Yaml::parseFile(base_path('infra/monitoring/slo-alerts.test.yml'));
    expect($tests['rule_files'])->toBe(['slo-alerts.yml']);
    $tested = [];
    foreach ($tests['tests'] as $case) {
        foreach ($case['alert_rule_test'] ?? [] as $check) {
            if (($check['exp_alerts'] ?? []) !== []) {
                $tested[] = $check['alertname'];
            }
        }
    }
    $alerts = array_keys(alertRulesAlerts());
    expect(count($alerts))->toBeGreaterThanOrEqual(22)
        ->and(array_values(array_diff($alerts, $tested)))->toBe([], 'alerts without a firing test');
});

it('reads only metrics that /metrics exports', function () {
    $controller = (string) file_get_contents(base_path('app/Http/Controllers/Web/HealthController.php'));
    preg_match_all("/emit\\('(onhost_[a-z0-9_]+)'/", $controller, $m);
    $exported = array_unique($m[1]);
    $rules = (string) file_get_contents(base_path('infra/monitoring/slo-alerts.yml'));
    preg_match_all('/\b(onhost_[a-z0-9_]+)\b/', $rules, $used);
    $missing = array_values(array_diff(array_unique($used[1]), $exported));
    expect($missing)->toBe([], 'metrics read by the rules but not exported');
});

it('routes pages to the platform webhook and to e-mail, tickets to e-mail', function () {
    $config = Yaml::parseFile(base_path('infra/monitoring/alertmanager.yml'));
    $receivers = collect($config['receivers'])->keyBy('name');
    $pageRoute = collect($config['route']['routes'])->first(fn (array $r) => in_array('severity = page', $r['matchers'] ?? [], true));
    $pager = $receivers[$pageRoute['receiver']];
    expect($pager['webhook_configs'][0]['url'])->toEndWith('/v1/webhooks/alertmanager')
        ->and($pager['email_configs'] ?? [])->not->toBeEmpty()
        ->and($receivers[$config['route']['receiver']]['email_configs'] ?? [])->not->toBeEmpty();
});
