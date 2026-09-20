<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\OperationLatency;
use Onhost\Domain\Provisioning\OperationsBoard;
use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;
use Onhost\Domain\Services\Models\Backup;

/*
 * "An action is done in seconds" as a number (OperationLatency): from accepted to finished, by panel and action, the wait
 * in the queue apart from the run; only what a person waits for is judged against the target. The doctor reads it, next
 * to two other rules the platform keeps about itself: finished operations hold no secrets, web services have backups.
 */

function latencyOperation(string $serviceId, string $organizationId, string $instanceId, string $action, int $waited, int $ran, int $n): void
{
    $finished = now()->startOfSecond()->subMinutes(5 + $n); // the table keeps whole seconds
    Operation::query()->create([
        'service_id' => $serviceId, 'organization_id' => $organizationId, 'provider_instance_id' => $instanceId, 'kind' => 'service.action', 'workflow' => ServiceActionWorkflow::class,
        'state' => Operation::SUCCEEDED, 'step' => 1, 'steps_total' => 1, 'actor_type' => 'system', 'idempotency_key' => "lat-{$action}-{$n}", 'correlation_id' => "c-{$n}", 'desired' => ['action' => $action],
        'queued_at' => $finished->copy()->subSeconds($waited + $ran), 'started_at' => $finished->copy()->subSeconds($ran), 'finished_at' => $finished, 'secrets_scrubbed_at' => now(),
    ]);
}

it('measures what a person waits for by panel and action, the queue apart from the run, and judges only the quick kinds', function () {
    config(['onhost.provisioning.latency_target_seconds' => 30]);
    [, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'ispconfig');
    $instance = (string) $site->provider_instance_id;
    foreach ([4, 5, 6, 8, 70] as $i => $ran) { // one panel job sat behind the server's queue for over a minute
        latencyOperation($site->id, $org->id, $instance, 'php.set', 2, $ran, $i);
    }
    foreach ([2, 2, 3] as $i => $ran) {
        latencyOperation($site->id, $org->id, $instance, 'database.create', 1, $ran, 10 + $i);
    }
    foreach ([300, 420, 600] as $i => $ran) { // a backup is long by nature: reported, never judged
        latencyOperation($site->id, $org->id, $instance, 'backup', 2, $ran, 20 + $i);
    }

    $rows = collect(app(OperationLatency::class)->summary())->keyBy(fn (array $r) => $r['provider'].' '.$r['action']);
    expect($rows['ispconfig php.set'])->toMatchArray(['count' => 5, 'p50_s' => 8.0, 'p95_s' => 72.0, 'max_s' => 72.0, 'wait_p95_s' => 2.0, 'judged' => true, 'slow' => true]);
    expect($rows['ispconfig database.create'])->toMatchArray(['count' => 3, 'p95_s' => 4.0, 'judged' => true, 'slow' => false]);
    expect($rows['ispconfig backup'])->toMatchArray(['count' => 3, 'judged' => false, 'slow' => false])->and($rows['ispconfig backup']['p95_s'])->toBeGreaterThan(600);
    expect(collect(app(OperationLatency::class)->slow())->pluck('action')->all())->toBe(['php.set']);

    // the operations board shows the same numbers to staff
    $board = app(OperationsBoard::class)->board();
    expect($board['latency']['target_s'])->toBe(30)->and(collect($board['latency']['rows'])->first())->toMatchArray(['action' => 'php.set', 'slow' => true]);

    // and the doctor names them, together with a web service that has no backup and an operation that still holds what it was given
    Operation::query()->where('idempotency_key', 'lat-php.set-0')->update(['secrets_scrubbed_at' => null, 'finished_at' => now()->subHours(3)]);
    $site->forceFill(['created_at' => now()->subDays(10)])->save();
    Artisan::call('onhost:doctor', ['--json' => true]);
    $checks = collect(json_decode(Artisan::output(), true)['checks'])->keyBy('check');
    expect($checks['actions a person waits for finish within 30 s (p95, 24 h)'])->toMatchArray(['status' => 'WARN'])->and($checks['actions a person waits for finish within 30 s (p95, 24 h)']['detail'])->toContain('ispconfig php.set: p95 72 s');
    expect($checks['finished operations hold no secrets']['status'])->toBe('WARN')->and($checks['finished operations hold no secrets']['detail'])->toContain('1 finished operation');
    expect($checks['every web service has a backup from the last 3 days']['status'])->toBe('WARN')->and($checks['every web service has a backup from the last 3 days']['detail'])->toContain($site->id);

    Backup::query()->create(['service_id' => $site->id, 'organization_id' => $org->id, 'kind' => 'scheduled', 'state' => 'completed', 'started_at' => now()->subDay(), 'finished_at' => now()->subDay()]);
    Artisan::call('onhost:doctor', ['--json' => true]);
    expect(collect(json_decode(Artisan::output(), true)['checks'])->firstWhere('check', 'every web service has a backup from the last 3 days')['status'])->toBe('OK');
});
