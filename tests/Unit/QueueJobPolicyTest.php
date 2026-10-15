<?php

declare(strict_types=1);

/*
 * A8: a job may only be re-reserved after the worker has given up on it. retry_after must exceed the
 * queue:work --timeout of the systemd worker unit, otherwise a long provisioning or payment job runs twice.
 */

function queueJobPolicyUnit(): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/infra/systemd/onhost-queue@.service');
}

function queueJobPolicyWorkerTimeout(): int
{
    expect(preg_match('/^ExecStart=.*queue:work.*--timeout=(\d+)/m', queueJobPolicyUnit(), $m))->toBe(1);

    return (int) $m[1];
}

/** @return array<string, int> */
function queueJobPolicyRetryAfterDefaults(): array
{
    $config = (string) file_get_contents(dirname(__DIR__, 2).'/config/queue.php');
    preg_match_all("/env\('([A-Z_]+_QUEUE_RETRY_AFTER)',\s*(\d+)\)/", $config, $m, PREG_SET_ORDER);
    $out = [];
    foreach ($m as $row) {
        $out[$row[1]] = (int) $row[2];
    }

    return $out;
}

it('keeps retry_after above the worker timeout for redis, database and beanstalkd', function () {
    $timeout = queueJobPolicyWorkerTimeout();
    $defaults = queueJobPolicyRetryAfterDefaults();

    expect(array_keys($defaults))->toContain('REDIS_QUEUE_RETRY_AFTER', 'DB_QUEUE_RETRY_AFTER', 'BEANSTALKD_QUEUE_RETRY_AFTER');
    foreach ($defaults as $key => $retryAfter) {
        expect($retryAfter)->toBeGreaterThan($timeout, "{$key} must exceed --timeout={$timeout}");
    }
});

it('stops the worker unit only after the job timeout has elapsed', function () {
    expect(preg_match('/^TimeoutStopSec=(\d+)/m', queueJobPolicyUnit(), $m))->toBe(1)
        ->and((int) $m[1])->toBeGreaterThan(queueJobPolicyWorkerTimeout());
});
