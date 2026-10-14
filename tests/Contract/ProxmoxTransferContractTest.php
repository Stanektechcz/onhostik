<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Proxmox\ProxmoxComputeProvider;

/*
 * Phase T, metering M2: a VM's monthly transfer comes from the node's RRD (`GET /nodes/{node}/qemu/{vmid}/rrddata`, timeframe month,
 * cf AVERAGE). `netin`/`netout` are average rates in bytes per second over one RRD step, so the bytes of a step are the rate times the
 * step; a point without data is skipped, never read as zero. ASSUMED until PX-1 (no Proxmox on staging): the units, the 12-hour step
 * of the month archive and that the archive survives a restart and a migration inside the cluster.
 */

/** RRD points every 12 hours from $from to $to (inclusive); $rate(time) gives [netin, netout] or null for a point without data. */
function t3RrdPoints(Carbon $from, Carbon $to, callable $rate): array
{
    $points = [];
    for ($t = $from->getTimestamp(); $t <= $to->getTimestamp(); $t += 43200) {
        $r = $rate($t);
        $points[] = ['time' => $t, 'cpu' => 0.02] + ($r === null ? [] : ['netin' => $r[0], 'netout' => $r[1]]);
    }

    return $points;
}

function t3Adapter(): ProxmoxComputeProvider
{
    $registry = app(ProviderRegistry::class);
    $registry->register('proxmox', ProxmoxComputeProvider::class);

    return $registry->forInstance(pveLab());
}

function t3PveFake(array $points, array &$asked): void
{
    Http::fake(function (Request $request) use ($points, &$asked) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $asked[] = $path.'?'.(string) parse_url($request->url(), PHP_URL_QUERY);

        return match (true) {
            str_ends_with($path, '/qemu/1042/status/current') => Http::response(['data' => ['status' => 'running', 'cpu' => 0.1, 'mem' => 1024, 'maxmem' => 2048, 'netin' => 5, 'netout' => 7]]),
            str_ends_with($path, '/qemu/1042/rrddata') => Http::response(['data' => $points]),
            default => Http::response(['errors' => ['unexpected' => $path]], 500),
        };
    });
}

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-20 10:00:00', 'UTC')); // travel first, then build anything dated (time bomb, TASK-0192)
});

it('sums the transfer of the calendar month from the RRD and skips the points before it and without data', function () {
    $monthStart = Carbon::parse('2026-10-01 00:00:00', 'UTC');
    $points = t3RrdPoints(Carbon::parse('2026-09-26 00:00:00', 'UTC'), Carbon::parse('2026-10-20 00:00:00', 'UTC'), function (int $t) use ($monthStart) {
        if ($t === Carbon::parse('2026-10-05 12:00:00', 'UTC')->getTimestamp()) {
            return null; // the node was down: no data, not zero
        }

        return $t < $monthStart->getTimestamp() ? [9_000_000, 9_000_000] : [1_000_000, 500_000];
    });
    $asked = [];
    t3PveFake($points, $asked);

    $usage = t3Adapter()->usage(new ResourceRef('qemu', '1042', 'prg1-n2'), $monthStart->toIso8601String());

    // 1 Oct 00:00 … 20 Oct 00:00 every 12 h = 39 points, one without data: 38 × 1,5 MB/s × 43 200 s
    expect($usage->metrics['transfer_bytes'])->toBe(38 * 1_500_000 * 43200)
        ->and($usage->metrics['mem_bytes'])->toBe(1024)
        ->and($usage->periodStart)->toBe($monthStart->toIso8601String());
    expect(collect($asked)->first(fn (string $a) => str_contains($a, '/rrddata')))->toContain('timeframe=month')->toContain('cf=AVERAGE');
});

it('reports no transfer at all when the RRD has no data for the month', function () {
    $asked = [];
    t3PveFake(t3RrdPoints(Carbon::parse('2026-10-01 00:00:00', 'UTC'), Carbon::parse('2026-10-20 00:00:00', 'UTC'), fn () => null), $asked);

    $usage = t3Adapter()->usage(new ResourceRef('qemu', '1042', 'prg1-n2'), '2026-10-01T00:00:00+00:00');

    expect($usage->metrics)->toHaveKey('transfer_bytes')->and($usage->metrics['transfer_bytes'])->toBeNull();
});

it('asks the RRD only when a period is asked for', function () {
    $asked = [];
    t3PveFake([], $asked);

    $usage = t3Adapter()->usage(new ResourceRef('qemu', '1042', 'prg1-n2'));

    expect($usage->metrics)->not->toHaveKey('transfer_bytes')
        ->and(collect($asked)->contains(fn (string $a) => str_contains($a, '/rrddata')))->toBeFalse();
});
