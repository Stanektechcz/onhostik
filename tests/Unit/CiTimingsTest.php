<?php

declare(strict_types=1);

// W3-2: scripts/ci/ci-timings.php computes CI timing metrics offline from a saved JSON file.
function runCiTimingsW32(?array $fixture): array
{
    $root = dirname(__DIR__, 2);
    $args = [PHP_BINARY, $root.'/scripts/ci/ci-timings.php'];
    $file = null;
    if ($fixture !== null) {
        $file = (string) tempnam(sys_get_temp_dir(), 'w32-timings-');
        file_put_contents($file, (string) json_encode($fixture));
        $args[] = '--from-file='.$file;
    }
    $proc = proc_open($args, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = (string) stream_get_contents($pipes[1]);
    $err = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    if ($file !== null) {
        @unlink($file);
    }

    return [$code, json_decode($out, true), $err];
}

function ciTimingsRunW32(int $id, string $conclusion, array $shardSeconds): array
{
    $jobs = [];
    foreach ($shardSeconds as $i => $seconds) {
        $jobs[] = [
            'name' => 'pest-postgres shard '.($i + 1).'/4',
            'conclusion' => 'success',
            'started_at' => '2026-10-01T10:00:00Z',
            'completed_at' => gmdate('Y-m-d\TH:i:s\Z', strtotime('2026-10-01T10:00:00Z') + $seconds),
        ];
    }

    return ['id' => $id, 'head_sha' => 'sha'.$id, 'conclusion' => $conclusion, 'jobs' => $jobs];
}

it('computes median, nearest-rank p90 and wall as the slowest shard', function () {
    $runs = [];
    foreach ([100, 101, 102, 103, 104, 105, 106, 107, 108, 110] as $i => $s) {
        $runs[] = ciTimingsRunW32(10 - $i, 'success', [$s, 50, 60, 70]);
    }
    [$code, $json] = runCiTimingsW32(['workflow' => 'tests.yml', 'branch' => 'development', 'runs' => $runs]);

    expect($code)->toBe(0)
        ->and($json['wall']['n'])->toBe(10)
        ->and($json['wall']['median_s'])->toEqual(104.5)
        ->and($json['wall']['p90_s'])->toEqual(108)
        ->and($json['wall']['max_s'])->toEqual(110)
        ->and($json['wall']['inconclusive'])->toBeFalse()
        ->and($json['jobs']['pest-postgres shard 2/4']['median_s'])->toEqual(50)
        ->and($json['head_sha'])->toBe(['from' => 'sha1', 'to' => 'sha10']);
});

it('skips runs that did not succeed', function () {
    $runs = [ciTimingsRunW32(1, 'success', [100]), ciTimingsRunW32(2, 'failure', [900]), ciTimingsRunW32(3, 'cancelled', [5])];
    [$code, $json] = runCiTimingsW32(['runs' => $runs]);

    expect($code)->toBe(0)->and($json['run_ids'])->toBe([1])->and($json['wall']['max_s'])->toEqual(100);
});

it('marks a metric inconclusive when the spread exceeds twenty percent', function () {
    $runs = [];
    foreach ([100, 100, 100, 100, 200] as $i => $s) {
        $runs[] = ciTimingsRunW32($i + 1, 'success', [$s]);
    }
    [, $json] = runCiTimingsW32(['runs' => $runs]);

    expect($json['wall']['spread'])->toEqual(1)->and($json['wall']['inconclusive'])->toBeTrue();
});

it('exits with code 2 without --from-file or --collect', function () {
    [$code, , $err] = runCiTimingsW32(null);

    expect($code)->toBe(2)->and($err)->toContain('usage');
});
