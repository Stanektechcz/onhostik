<?php

declare(strict_types=1);

/*
 * W3-2: read-only CI timing baseline.
 *
 *   php scripts/ci/ci-timings.php --collect --workflow=tests.yml --branch=development --runs=20 [--out=.ai/baseline/ci-timings.json]
 *   php scripts/ci/ci-timings.php --from-file=<runs.json> [--out=<file>]
 *
 * --collect only issues GET requests through `gh api` (actions/workflows/{wf}/runs and runs/{id}/jobs).
 * --from-file reads {"workflow":..,"branch":..,"runs":[{"id":..,"head_sha":..,"conclusion":..,"jobs":[{"name":..,
 * "conclusion":..,"started_at":..,"completed_at":..}]}]} (newest run first) and computes offline.
 * Only runs and jobs with conclusion=success are used. Per job: n, median, p90 (nearest rank), min, max in seconds;
 * per run: wall = max over jobs (the slowest shard decides). spread = (p90 - median) / median; spread > 0.20 marks
 * the metric inconclusive. Output never contains user names, tokens or environment values.
 * Exit codes: 0 ok, 1 no usable data or gh request failed, 2 usage error or gh not authenticated.
 */

function ciTimingsFail(string $message, int $code): never
{
    fwrite(STDERR, $message.PHP_EOL);
    exit($code);
}

/**
 * @param  list<float>  $values
 * @return array{n: int, median_s: float, p90_s: float, min_s: float, max_s: float, spread: float, inconclusive: bool}
 */
function ciTimingsMetric(array $values): array
{
    sort($values);
    $n = count($values);
    $median = $n % 2 === 1 ? $values[intdiv($n, 2)] : ($values[intdiv($n, 2) - 1] + $values[intdiv($n, 2)]) / 2;
    $p90 = $values[max(0, (int) ceil(0.9 * $n) - 1)];
    $spread = $median > 0 ? ($p90 - $median) / $median : 0.0;

    return [
        'n' => $n,
        'median_s' => round($median, 1),
        'p90_s' => round($p90, 1),
        'min_s' => round($values[0], 1),
        'max_s' => round($values[$n - 1], 1),
        'spread' => round($spread, 3),
        'inconclusive' => $spread > 0.20,
    ];
}

/** @return array<string, mixed> */
function ciTimingsGh(string $endpoint): array
{
    $out = [];
    exec('gh api -X GET '.escapeshellarg($endpoint).' 2>/dev/null', $out, $code);
    if ($code !== 0) {
        ciTimingsFail('gh api request failed: '.$endpoint, 1);
    }
    $data = json_decode(implode("\n", $out), true);

    return is_array($data) ? $data : [];
}

$opts = getopt('', ['collect', 'from-file:', 'workflow:', 'branch:', 'runs:', 'out:']);
$opts = is_array($opts) ? $opts : [];

if (isset($opts['from-file'])) {
    $raw = @file_get_contents((string) $opts['from-file']);
    $input = is_string($raw) ? json_decode($raw, true) : null;
    if (! is_array($input) || ! isset($input['runs']) || ! is_array($input['runs'])) {
        ciTimingsFail('invalid input file', 2);
    }
} elseif (isset($opts['collect'])) {
    $workflow = (string) ($opts['workflow'] ?? 'tests.yml');
    $branch = (string) ($opts['branch'] ?? 'development');
    $limit = max(1, min(100, (int) ($opts['runs'] ?? 20)));
    $authOut = [];
    exec('gh auth status >/dev/null 2>&1', $authOut, $authCode);
    if ($authCode !== 0) {
        ciTimingsFail('gh is not authenticated', 2);
    }
    $base = 'repos/{owner}/{repo}';
    $list = ciTimingsGh($base.'/actions/workflows/'.rawurlencode($workflow).'/runs?branch='.rawurlencode($branch)
        .'&status=success&per_page='.$limit);
    $input = ['workflow' => $workflow, 'branch' => $branch, 'runs' => []];
    foreach (array_slice((array) ($list['workflow_runs'] ?? []), 0, $limit) as $run) {
        $jobs = ciTimingsGh($base.'/actions/runs/'.(int) $run['id'].'/jobs?per_page=100');
        $input['runs'][] = [
            'id' => (int) $run['id'],
            'head_sha' => (string) ($run['head_sha'] ?? ''),
            'conclusion' => (string) ($run['conclusion'] ?? ''),
            'jobs' => array_map(static fn (array $j): array => [
                'name' => (string) ($j['name'] ?? ''),
                'conclusion' => (string) ($j['conclusion'] ?? ''),
                'started_at' => (string) ($j['started_at'] ?? ''),
                'completed_at' => (string) ($j['completed_at'] ?? ''),
            ], (array) ($jobs['jobs'] ?? [])),
        ];
    }
} else {
    ciTimingsFail('usage: --collect --workflow=tests.yml --branch=development --runs=20 | --from-file=<json>', 2);
}

$perJob = [];
$walls = [];
$runIds = [];
$shas = [];
foreach ($input['runs'] as $run) {
    if (! is_array($run) || ($run['conclusion'] ?? null) !== 'success') {
        continue;
    }
    $wall = null;
    foreach ((array) ($run['jobs'] ?? []) as $job) {
        if (! is_array($job) || ($job['conclusion'] ?? null) !== 'success') {
            continue;
        }
        $start = strtotime((string) ($job['started_at'] ?? ''));
        $end = strtotime((string) ($job['completed_at'] ?? ''));
        if ($start === false || $end === false || $end < $start) {
            continue;
        }
        $seconds = (float) ($end - $start);
        $perJob[(string) ($job['name'] ?? '')][] = $seconds;
        $wall = $wall === null ? $seconds : max($wall, $seconds);
    }
    if ($wall !== null) {
        $walls[] = $wall;
        $runIds[] = (int) ($run['id'] ?? 0);
        $shas[] = (string) ($run['head_sha'] ?? '');
    }
}

if ($walls === []) {
    ciTimingsFail('no successful runs with successful jobs', 1);
}

ksort($perJob);
$result = [
    'collected_at' => gmdate('Y-m-d\TH:i:s\Z'),
    'workflow' => (string) ($input['workflow'] ?? ''),
    'branch' => (string) ($input['branch'] ?? ''),
    'run_ids' => $runIds,
    'head_sha' => ['from' => $shas[count($shas) - 1], 'to' => $shas[0]],
    'wall' => ciTimingsMetric($walls),
    'jobs' => array_map('ciTimingsMetric', $perJob),
];

$json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
if (isset($opts['out'])) {
    file_put_contents((string) $opts['out'], $json);
}
echo $json;
exit(0);
