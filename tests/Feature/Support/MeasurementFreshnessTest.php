<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Services\Freshness;
use Onhost\Domain\Services\ServiceHealthCheck;
use Onhost\Domain\Services\UsageWatch;

/*
 * A finding built from collected data says when it was observed, and old, missing or failed measurements can never be
 * reported as "ok".
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Queue::fake();
});

function freshnessFinding(array $check, string $key): array
{
    return collect($check['findings'])->firstWhere('key', $key);
}

function withTls($site, ?string $checkedAt): void
{
    $tls = ['expires_at' => now()->addDays(60)->toIso8601String(), 'issuer' => 'LE', 'names' => [(string) $site->hostname], 'mismatch' => false];
    if ($checkedAt !== null) {
        $tls['checked_at'] = $checkedAt;
    }
    $site->forceFill(['tags' => array_merge((array) $site->tags, ['tls' => $tls])])->save();
}

it('calls a recent certificate check ok and current', function () {
    [, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'aapanel');
    withTls($site, now()->subHours(3)->toIso8601String());

    $f = freshnessFinding(app(ServiceHealthCheck::class)->run($site->fresh()), 'certificate');

    expect($f['level'])->toBe('ok')->and($f['state'])->toBe('current')->and($f['observed_at'])->not->toBeNull();
});

it('downgrades an old certificate check to warn/stale and names when it was last seen', function () {
    [, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'aapanel');
    withTls($site, now()->subDays(5)->toIso8601String());

    $f = freshnessFinding(app(ServiceHealthCheck::class)->run($site->fresh()), 'certificate');

    expect($f['level'])->toBe('warn')->and($f['state'])->toBe('stale')->and($f['en'])->toContain('last observed')->and($f['cs'])->toContain('naposledy změřeno');
});

it('calls a certificate that was never measured warn/unknown', function () {
    [, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'aapanel');
    withTls($site, null);

    $f = freshnessFinding(app(ServiceHealthCheck::class)->run($site->fresh()), 'certificate');

    expect($f['level'])->toBe('warn')->and($f['state'])->toBe('unknown')->and($f['observed_at'])->toBeNull();
});

it('reports a collector error as collection_error and never as ok', function () {
    [, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'aapanel');
    withTls($site, now()->subHours(1)->toIso8601String());
    app(AutomationLedger::class)->record('certificates.watch', [], 'connection refused');

    $f = freshnessFinding(app(ServiceHealthCheck::class)->run($site->fresh()), 'certificate');

    expect($f['level'])->toBe('warn')->and($f['state'])->toBe('collection_error');
});

it('keeps a bad finding bad when its data is old, only stamping it', function () {
    [, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'aapanel');
    $site->forceFill(['tags' => array_merge((array) $site->tags, ['usage' => ['level' => 'full', 'metrics' => ['disk' => ['used' => 50, 'limit' => 50, 'pct' => 100]], 'checked_at' => now()->subDays(3)->toIso8601String()]])])->save();

    $f = freshnessFinding(app(ServiceHealthCheck::class)->run($site->fresh()), 'usage');

    expect($f['level'])->toBe('bad')->and($f['state'])->toBe('stale');
});

it('derives freshness limits from the collector intervals', function () {
    expect(Freshness::limitMinutes('certificate'))->toBe(2880)->and(Freshness::limitMinutes('node_usage'))->toBe(30)->and(Freshness::limitMinutes('usage'))->toBe(120);
});

it('never shows an unreported traffic reading as zero', function () {
    $reading = (new ReflectionMethod(UsageWatch::class, 'reading'))->invoke(null, 'traffic', null, 1000, 'quotas');

    expect($reading->value)->toBeNull()->and($reading->quality)->toBe('unavailable');
});
