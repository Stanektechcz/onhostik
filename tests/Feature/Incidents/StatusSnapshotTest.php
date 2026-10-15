<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Onhost\Domain\Incidents\Models\StatusComponent;
use Onhost\Domain\Incidents\StatusSnapshot;

/* N14: the public status snapshot — allowlisted fields, last good value on failure, dropped by incident.resolved; status.onhost.cz behind config. */

it('keeps only allowlisted fields in the public status snapshot', function () {
    $out = StatusSnapshot::allowlist([
        'overall' => 'operational', 'generated_at' => 'now', 'secret' => 'x',
        'components' => [['key' => 'dns', 'n' => 'DNS', 'group' => null, 'state' => 'operational', 'u' => 100.0, 'bad' => [], 'node_ip' => '10.0.0.1']],
        'incidents' => [['number' => 'INC-1', 'title' => 'T', 'affected_organizations' => ['org-1'], 'hist' => [['state' => 'resolved', 'note' => 'n', 'author' => 'staff@x']]]],
        'maintenance' => [['number' => 'M-1', 'title' => 'M', 'internal_note' => 'x']],
    ]);

    expect($out)->not->toHaveKey('secret')
        ->and($out['components'][0])->not->toHaveKey('node_ip')
        ->and($out['incidents'][0])->not->toHaveKey('affected_organizations')
        ->and($out['incidents'][0]['hist'][0])->toBe(['state' => 'resolved', 'note' => 'n'])
        ->and($out['maintenance'][0])->toBe(['number' => 'M-1', 'title' => 'M']);
});

it('serves the cached snapshot, the last good one on failure, and drops it on incident.resolved', function () {
    Cache::forget(StatusSnapshot::KEY);
    Cache::forget(StatusSnapshot::LAST_GOOD_KEY);
    $first = $this->getJson('/v1/status')->assertOk()->json('data');
    expect(Cache::get(StatusSnapshot::KEY))->toEqual($first)->and(Cache::get(StatusSnapshot::LAST_GOOD_KEY))->toEqual($first); // JSON turns 100.0 into 100

    Event::dispatch('onhost.incident.resolved', [null]);
    expect(Cache::has(StatusSnapshot::KEY))->toBeFalse();

    expect($first['components'])->not->toBeEmpty(); // StatusSeeder components, so the failure below is reached
    StatusComponent::retrieved(fn () => throw new RuntimeException('db down'));
    try {
        $this->getJson('/v1/status')->assertOk()->assertJsonPath('data.stale', true)->assertJsonPath('data.overall', $first['overall']);
    } finally {
        StatusComponent::flushEventListeners();
    }
});

it('routes the platform status host to /stav only when enabled', function () {
    $this->get('http://status.onhost.cz/')->assertStatus(200);
    config(['onhost.status.host_enabled' => true]);
    $this->get('http://status.onhost.cz/')->assertRedirect('/stav');
});
