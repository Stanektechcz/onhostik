<?php

declare(strict_types=1);

/**
 * Phase L (L156): every queue a job dispatches to must be processed.
 *
 * The failure this guards against is silent and total: provisioning jobs were
 * dispatched to `provisioning-high` / `provisioning`, but Horizon's only
 * supervisor processed `default`. Nothing errored — the jobs simply queued and
 * were never picked up, so on production no service ever activated through a
 * worker. A config drift, invisible until a real deploy.
 */

/** @return list<string> Every queue name any Horizon supervisor processes. */
function processedQueues(): array
{
    $queues = [];

    foreach ((array) config('horizon.defaults', []) as $supervisor) {
        foreach ((array) ($supervisor['queue'] ?? []) as $queue) {
            $queues[] = $queue;
        }
    }

    return array_values(array_unique($queues));
}

it('processes every provisioning queue', function (): void {
    $configured = [
        config('provisioning.queues.high', 'provisioning-high'),
        config('provisioning.queues.default', 'provisioning'),
        config('provisioning.queues.low', 'provisioning-low'),
    ];

    $processed = processedQueues();

    // A queue no supervisor processes is a queue whose jobs never run.
    $unprocessed = array_values(array_diff($configured, $processed));

    expect($unprocessed)->toBe([], 'Fronty bez Horizon workera: ' . implode(', ', $unprocessed));
});

it('still processes the default queue for e-mails and notifications', function (): void {
    expect(processedQueues())->toContain('default');
});

it('keeps provisioning and default work on separate supervisors', function (): void {
    // So a backlog of one workload cannot starve the other.
    $supervisors = (array) config('horizon.defaults', []);

    $provisioningSupervisors = [];
    $defaultSupervisors      = [];

    foreach ($supervisors as $name => $supervisor) {
        $queues = (array) ($supervisor['queue'] ?? []);

        if (in_array('default', $queues, true)) {
            $defaultSupervisors[] = $name;
        }

        if (array_intersect($queues, ['provisioning', 'provisioning-high', 'provisioning-low']) !== []) {
            $provisioningSupervisors[] = $name;
        }
    }

    expect($provisioningSupervisors)->not->toBeEmpty()
        ->and($defaultSupervisors)->not->toBeEmpty()
        // No supervisor should carry both, or the isolation is meaningless.
        ->and(array_intersect($provisioningSupervisors, $defaultSupervisors))->toBe([]);
});

it('drains the high-priority provisioning queue first', function (): void {
    $supervisors = (array) config('horizon.defaults', []);

    foreach ($supervisors as $supervisor) {
        $queues = (array) ($supervisor['queue'] ?? []);

        if (in_array('provisioning-high', $queues, true)) {
            // Horizon works queues left-to-right; high must lead.
            expect(array_search('provisioning-high', $queues, true))->toBe(0);
        }
    }
});
