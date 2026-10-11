<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Incidents\Models\OnCallAlert;
use Onhost\Domain\Incidents\OnCallService;

/* One subject raised again while its alert is active is one row with a count; a resolved alert starts a new one. */

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('onhost.oncall.provider', '');
});

it('folds five identical raises into one alert with five occurrences', function () {
    $svc = app(OnCallService::class);
    foreach (range(1, 5) as $_) {
        $svc->open('integration.down', 'provider_instance', 'pi_1', 'Down', 'x', null, 'warn');
    }

    $rows = OnCallAlert::query()->where('dedup_key', OnCallService::dedupKey('integration.down', 'provider_instance', 'pi_1'))->get();
    expect($rows)->toHaveCount(1)->and($rows->first()->occurrences)->toBe(5)->and($rows->first()->last_seen_at)->not->toBeNull();
});

it('starts a new alert once the old one is resolved', function () {
    $svc = app(OnCallService::class);
    $first = $svc->open('integration.down', 'provider_instance', 'pi_2', 'Down', null, null, 'warn');
    $svc->resolve($first, 'tester');
    $second = $svc->open('integration.down', 'provider_instance', 'pi_2', 'Down', null, null, 'warn');

    expect($second->id)->not->toBe($first->id)->and($second->occurrences)->toBe(1)->and(OnCallAlert::query()->where('event', 'integration.down')->count())->toBe(2);
});

it('raises severity on a more severe repeat and never lowers it', function () {
    $svc = app(OnCallService::class);
    $svc->open('queue.stalled', 'queue', 'default', 'Stalled', null, null, 'warn');
    $up = $svc->open('queue.stalled', 'queue', 'default', 'Stalled', null, null, 'hot');
    $down = $svc->open('queue.stalled', 'queue', 'default', 'Stalled', null, null, 'info');

    expect($up->id)->toBe($down->id)->and($down->fresh()->severity)->toBe('hot')->and($down->fresh()->occurrences)->toBe(3)
        ->and(OnCallService::present($down->fresh()))->toHaveKeys(['occurrences', 'last_seen_at']);
});
