<?php

declare(strict_types=1);

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\IntegrationHealthProbe;
use Onhost\Domain\Provisioning\Models\IntegrationHealth;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\ProviderHttp\ProviderHttpClient;
use Onhost\Platform\Resilience\TokenBucket;

/*
 * A reserve for diagnostics in every panel quota (Brain card H323). Our own flood of installs must not blind the
 * health probe: a call we refused ourselves never reached the panel, so it proves nothing about the panel. The top
 * slice of each window belongs to health reads alone, the total never exceeds what the vendor allows, and a probe that
 * could not be sent leaves the last verdict standing instead of calling a working panel down.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    if (time() % 60 >= 57) {
        sleep(4); // the quota window is a wall-clock minute: do not start a scenario on its edge
    }
});

function diagnosticPanelUp(): void
{
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/api2/json/version')) {
            return Http::response(['data' => ['version' => '8.2.4', 'release' => '8.2']]);
        }
        if (str_ends_with($request->url(), '/api2/json/cluster/status')) {
            return Http::response(['data' => [['type' => 'cluster', 'quorate' => 1]]]);
        }

        return Http::response(['data' => []]);
    });
}

it('keeps the last slice of a quota for diagnostic reads and never lets the total pass the limit', function () {
    $bucket = new TokenBucket(app(CacheRepository::class), 'h323-unit', 40, 3600, 0.1, 3);

    $ordinary = 0;
    while ($bucket->tryConsume()) {
        $ordinary++;
    }
    expect($ordinary)->toBe(33)->and($bucket->remaining())->toBe(0)->and($bucket->remaining(true))->toBe(4)->and($bucket->remaining(false, true))->toBe(7);

    // critical work (suspend, renewals) keeps its reserve — a tenth of the work — but does not reach the diagnostic slice
    $critical = 0;
    while ($bucket->tryConsume(true)) {
        $critical++;
    }
    expect($critical)->toBe(4)->and($bucket->used())->toBe(37);

    // health reads still get through, exactly as many as were kept for them
    expect($bucket->tryConsume(false, 1, true))->toBeTrue()->and($bucket->tryConsume(false, 1, true))->toBeTrue()->and($bucket->tryConsume(false, 1, true))->toBeTrue()
        ->and($bucket->tryConsume(false, 1, true))->toBeFalse()
        ->and($bucket->used())->toBe(40); // the vendor's limit holds for everyone together

    // without a slice nothing changes for the quotas that do not ask for one
    $plain = new TokenBucket(app(CacheRepository::class), 'h323-plain', 10, 3600, 0.1);
    expect($plain->remaining())->toBe(9)->and($plain->remaining(true))->toBe(10)->and($plain->diagnosticTokens())->toBe(0);

    // and a quota too small to spare three calls keeps all of it for work: the client gives it no slice
    $http = app(ProviderHttpClient::class);
    $http->configureBucket('h323-tiny', 2, 3600, 0.5);
    $http->configureBucket('h323-hourly', 100, 3600, 0.15);
    expect($http->bucket('h323-tiny')->diagnosticTokens())->toBe(0)->and($http->bucket('h323-tiny')->remaining(true))->toBe(2)
        ->and($http->bucket('h323-hourly')->diagnosticTokens())->toBe(5)->and($http->bucket('h323-hourly')->remaining())->toBe(80)->and($http->bucket('h323-hourly')->remaining(true))->toBe(95);
});

it('still measures a panel whose quota our own work has used up, and does not call it down when even the probe cannot be sent', function () {
    diagnosticPanelUp();
    $instance = pveLab();
    $instance->forceFill(['rate_limits' => ['per_minute' => 40]])->save();
    $http = app(ProviderHttpClient::class);
    $probe = app(IntegrationHealthProbe::class);

    expect($probe->probeInstance($instance->fresh())['up'])->toBeTrue(); // the baseline verdict, and the adapter has configured its bucket
    $bucket = $http->bucket($instance->key);
    expect($bucket)->not->toBeNull()->and($bucket->limit())->toBe(40)->and($bucket->diagnosticTokens())->toBe(3);

    // a flood of installs: ordinary and critical work take everything they are allowed to
    while ($bucket->tryConsume(true)) {
        // keep going
    }
    expect($bucket->remaining(true))->toBe(0)->and($bucket->remaining(false, true))->toBe(3);
    expect(fn () => app(ProviderRegistry::class)->forInstance($instance->fresh())->health())->not->toThrow(Throwable::class);
    expect(app(ProviderRegistry::class)->forInstance($instance->fresh())->health()->healthy)->toBeFalse(); // outside the diagnostic lane the same read is refused locally

    // the probe reads through its own slice and sees the panel as it is
    $result = $probe->probeInstance($instance->fresh());
    expect($result['up'])->toBeTrue()->and($result['detail'])->not->toHaveKey('unmeasured')
        ->and($bucket->used())->toBeLessThanOrEqual(40);

    // even the slice is gone (several probes in one minute): nothing was sent, so nothing is concluded
    while ($bucket->tryConsume(false, 1, true)) {
        // keep going
    }
    $sentBefore = count(Http::recorded());
    $unmeasured = $probe->probeInstance($instance->fresh());
    expect($unmeasured['up'])->toBeTrue()->and($unmeasured['detail'])->toBe(['unmeasured' => true])->and($unmeasured['error'])->toContain('local quota')
        ->and(count(Http::recorded()))->toBe($sentBefore)
        ->and(IntegrationHealth::query()->where('provider_instance_id', $instance->id)->value('up'))->toBeTruthy()
        ->and(OutboxMessage::query()->where('name', 'integration.down')->count())->toBe(0)
        ->and($bucket->used())->toBe(40);
});
