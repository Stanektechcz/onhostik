<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\UsageWatch;
use Onhost\Providers\AaPanel\AaPanelWebProvider;
use Onhost\Providers\Shell\ScriptedShell;

/*
 * Every web hosting plan sells an amount of traffic. ISPConfig counts it (`trafficquota_get_by_user` → `this_month`);
 * aaPanel has no traffic counter at all, so on a managed site the sold number was measured against **nothing** — the
 * customer could serve a hundred times the plan and the platform would report 0 %. The node does write every answer
 * it sends into the site's access log, with the bytes, so that is where the number comes from.
 */

beforeEach(fn () => Http::preventStrayRequests());
afterEach(fn () => AaPanelWebProvider::$shellFactory = null);

/** The node, answering the disk measurement and the traffic sum. */
function trafficNode(string $trafficAnswer, int $exitCode = 0): ScriptedShell
{
    $shell = new ScriptedShell([
        '/du -sb/' => [0, (string) (3 * 1024 ** 3)."\n2400\n"],
        '/wwwlogs/' => [$exitCode, $trafficAnswer],
    ]);
    AaPanelWebProvider::$shellFactory = fn () => $shell;

    return $shell;
}

it('counts what a managed site served this month, out of its own access log', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok', 'data' => [], 'page' => '']));
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $shell = trafficNode('128849018880 412553'); // 120 GB over 412 553 answers

    $quotas = app(ServiceFeatures::class)->resources($service, 'quotas', true);

    expect($quotas['traffic_used_bytes'])->toBe(128849018880)
        ->and($quotas['traffic_period'])->toBe(now()->format('Y-m'))
        ->and($quotas['disk_used_bytes'])->toBe(3 * 1024 ** 3);

    // only this month's files, only this month's lines, and the error log is never traffic
    $command = collect($shell->calls)->pluck('command')->first(fn (string $c) => str_contains($c, 'wwwlogs'));
    expect($command)->toContain('shop.cz.log*')
        ->toContain("'*error*'")
        ->toContain(now()->startOfMonth()->format('Y-m-d'))
        ->toContain('/'.now()->format('M').'/'.now()->format('Y'))
        ->toContain('zcat -f'); // rotation may have compressed them
});

it('says nothing rather than zero when the log cannot be read', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok', 'data' => [], 'page' => '']));
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    trafficNode('0 0'); // no line the platform recognised: a format it does not know, or a site with no visitors

    $quotas = app(ServiceFeatures::class)->resources($service, 'quotas', true);

    // „no traffic“ and „we cannot read it“ are not the same thing to a customer whose plan is being measured
    expect($quotas['traffic_used_bytes'])->toBeNull()->and($quotas['disk_used_bytes'])->toBe(3 * 1024 ** 3);
});

it('measures the plan against what the site really served', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok', 'data' => [], 'page' => '']));
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $service->forceFill(['entitlements' => array_replace((array) $service->entitlements, ['traffic_gb' => 100, 'nvme_gb' => 50])])->save();
    trafficNode((string) (92 * 1024 ** 3).' 300000'); // 92 of the plan's 100 GB

    app(UsageWatch::class)->run();

    $usage = (array) data_get($service->fresh()->tags, 'usage');
    expect(data_get($usage, 'metrics.traffic.pct'))->toBe(92)
        ->and($usage['level'])->toBe('warn')
        ->and(data_get($usage, 'metrics.traffic.limit'))->toBe(100 * 1024 ** 3);
});
