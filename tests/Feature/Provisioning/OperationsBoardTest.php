<?php

declare(strict_types=1);

use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\OperationsBoard;
use Onhost\Domain\Provisioning\Scheduling\NodeScheduler;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Staff operations board (audit §5e-3): what is stuck across tenants, the nodes behind it, drain/resume by hand and
 * the automatic drain of a node that only fails on transient errors — with the scheduler no longer picking it.
 */

it('lists stalled, failed and long-running operations, drains a failing node automatically, resumes it once it succeeds, and lets staff drain by hand', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $instance = ProviderInstance::query()->where('key', 'ispconfig-shared01')->firstOrFail();
    $node = Node::query()->where('provider_instance_id', $instance->id)->firstOrFail();
    $make = fn (string $key, array $attrs) => Operation::query()->create(array_merge([
        'organization_id' => $org->id, 'service_id' => $service->id, 'provider_instance_id' => $instance->id, 'kind' => 'provision.website', 'workflow' => 'provision.website', 'queue' => 'provider-ispconfig', 'idempotency_key' => $key, 'attempts' => 1, 'queued_at' => now()->subMinutes(5),
    ], $attrs));
    $stalled = $make('ob-stalled', ['state' => Operation::WAITING, 'next_run_at' => now()->addMinute(), 'error' => ['message' => '[ispconfig:TRANSIENT] Connection reset', 'retryable' => true]]);
    $make('ob-stalled-2', ['state' => Operation::WAITING, 'next_run_at' => now()->addMinute(), 'error' => ['message' => '[ispconfig:TRANSIENT] timeout', 'retryable' => true]]);
    $failed = $make('ob-failed', ['state' => Operation::FAILED, 'finished_at' => now()->subHours(2), 'error' => ['message' => '[ispconfig:TRANSIENT] timeout', 'retryable' => true]]);
    $make('ob-failed-old', ['state' => Operation::FAILED, 'finished_at' => now()->subDays(3), 'error' => ['message' => 'old', 'retryable' => false]]);
    $long = $make('ob-long', ['state' => Operation::RUNNING, 'started_at' => now()->subMinutes(25), 'step' => 3, 'steps_total' => 7, 'step_label' => 'Certifikát']);
    $make('ob-fresh', ['state' => Operation::RUNNING, 'started_at' => now()->subMinutes(2)]);

    $staff = $this->staff('infrastructure_admin'); // reads operations, manages provider instances and their nodes
    $this->actingAs($staff, 'sanctum');
    $board = $this->getJson('/v1/staff/provisioning/board')->assertOk()->json('data');
    expect(array_column($board['stalled'], 'id'))->toContain($stalled->id)->and($board['counts'])->toMatchArray(['stalled' => 2, 'failed_24h' => 1, 'long_running' => 1, 'draining' => 0])
        ->and(array_column($board['failed'], 'id'))->toBe([$failed->id])->and(array_column($board['long_running'], 'id'))->toBe([$long->id])->and($board['long_running'][0]['step_label'])->toBe('Certifikát');
    $row = collect($board['nodes'])->firstWhere('id', $node->id);
    expect($row)->toMatchArray(['state' => 'active', 'succeeded' => 0, 'transient_failures' => 3, 'suggest_drain' => true])->and($row['instance']['key'])->toBe('ispconfig-shared01');
    expect(json_encode($board))->not->toContain('remote-secret');

    // the automatic drain takes the node out of placement; the scheduler no longer picks it; staff hear about it
    expect(app(OperationsBoard::class)->autoDrain())->toMatchArray(['drained' => 1, 'resumed' => 0]);
    $node->refresh();
    expect($node->state)->toBe('draining')->and(data_get($node->tags, 'auto_drain.reason'))->toContain('3 transient failures');
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'internal')->where('title', 'like', 'Uzel % odstaven z umísťování (automaticky)')->exists())->toBeTrue();
    expect(fn () => app(NodeScheduler::class)->pick(['role' => $node->role, 'region' => $node->region_code]))->toThrow('No schedulable node');

    // operations succeed again and the transient errors are gone: the automatic drain resumes the node
    Operation::query()->whereIn('idempotency_key', ['ob-stalled', 'ob-stalled-2', 'ob-failed'])->update(['state' => Operation::SUCCEEDED, 'error' => null, 'finished_at' => now()]);
    expect(app(OperationsBoard::class)->autoDrain())->toMatchArray(['drained' => 0, 'resumed' => 1]);
    expect($node->refresh()->state)->toBe('active')->and($node->tags)->not->toHaveKey('auto_drain');

    // staff drain by hand through the API (audited, not resumed automatically), then resume
    $this->postJson("/v1/staff/integrations/{$instance->key}/nodes/{$node->id}/state", ['state' => 'draining', 'reason' => 'maintenance window'])->assertOk()->assertJsonPath('state', 'draining');
    Operation::query()->where('idempotency_key', 'ob-fresh')->update(['state' => Operation::SUCCEEDED, 'finished_at' => now()]);
    expect(app(OperationsBoard::class)->autoDrain()['resumed'])->toBe(0)->and($node->refresh()->state)->toBe('draining');
    $this->postJson("/v1/staff/integrations/{$instance->key}/nodes/{$node->id}/state", ['state' => 'active'])->assertOk()->assertJsonPath('state', 'active');
    $this->postJson("/v1/staff/integrations/{$instance->key}/nodes/{$node->id}/state", ['state' => 'broken'])->assertStatus(422);

    // a customer cannot see the board
    $this->actingAs($user, 'sanctum')->getJson('/v1/staff/provisioning/board')->assertForbidden();

    // the Blade page is served to staff
    $this->actingAs($staff)->get('/sprava/nastaveni/provoz')->assertOk()->assertSee('Provoz: operace a uzly')->assertSee('/staff/provisioning/board');
});

it('tells staff which operations drained a node, resumes a quiet drained node after two healthy probes, and leaves a node staff asked to keep drained', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $instance = ProviderInstance::query()->where('key', 'ispconfig-shared01')->firstOrFail();
    $node = Node::query()->where('provider_instance_id', $instance->id)->firstOrFail();
    $healthy = false;
    Http::fake(function ($request) use (&$healthy) {
        if (! str_starts_with($request->url(), ISP)) {
            return null;
        }
        if (! $healthy) {
            return Http::response(['code' => 'remote_fault', 'message' => 'maintenance', 'response' => false], 503);
        }
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);

        return Http::response(['code' => 'ok', 'message' => '', 'response' => match ($function) {
            'login' => 'sess-ok', 'sites_web_domain_get' => [], 'monitor_jobqueue_count' => 0, 'server_get' => ['hostname' => 'shared01'], default => true
        }]);
    });
    for ($i = 1; $i <= 3; $i++) {
        Operation::query()->create(['organization_id' => $org->id, 'service_id' => $service->id, 'provider_instance_id' => $instance->id, 'kind' => 'service.action', 'workflow' => 'service.action', 'queue' => 'provider-ispconfig', 'idempotency_key' => "fb-{$i}", 'attempts' => 2, 'queued_at' => now()->subMinutes(3), 'state' => Operation::WAITING, 'next_run_at' => now()->addMinute(), 'step_label' => 'Certifikát', 'error' => ['message' => '[ispconfig:TRANSIENT] Connection reset', 'retryable' => true]]);
    }
    $board = app(OperationsBoard::class);
    expect($board->autoDrain())->toMatchArray(['drained' => 1, 'resumed' => 0, 'probed' => 0]);
    app(OutboxPublisher::class)->relayPending();
    $notice = Notification::query()->where('audience', 'internal')->where('title', 'like', 'Uzel % odstaven z umísťování (automaticky)')->latest('created_at')->orderByDesc('id')->first();
    expect($notice)->not->toBeNull()->and($notice->body)->toContain('Certifikát')->toContain('"failed":[{"id":"op_'); // which operations, which step — not just "drained"

    // the operations move elsewhere, the node is quiet: the board probes the integration instead of waiting for a success that cannot come
    Operation::query()->where('idempotency_key', 'like', 'fb-%')->update(['state' => Operation::CANCELLED, 'error' => null, 'finished_at' => now()->subHour(), 'queued_at' => now()->subHour()]);
    expect($board->autoDrain())->toMatchArray(['resumed' => 0, 'probed' => 1])->and(data_get($node->fresh()->tags, 'auto_drain.probe_ok'))->toBe(0)->and($node->fresh()->state)->toBe('draining');
    $healthy = true;
    expect($board->autoDrain())->toMatchArray(['resumed' => 0, 'probed' => 1])->and(data_get($node->fresh()->tags, 'auto_drain.probe_ok'))->toBe(1);
    expect($board->autoDrain())->toMatchArray(['resumed' => 1])->and($node->fresh()->state)->toBe('active')->and($node->fresh()->tags)->not->toHaveKey('auto_drain');

    // staff keep a node drained on purpose: the feedback loop never touches it
    $staff = $this->staff('infrastructure_admin');
    $this->actingAs($staff, 'sanctum')->postJson("/v1/staff/integrations/{$instance->key}/nodes/{$node->id}/state", ['state' => 'draining', 'reason' => 'hardware swap', 'keep' => true])->assertOk()->assertJsonPath('tags.auto_drain.keep', true);
    expect($board->autoDrain())->toMatchArray(['resumed' => 0, 'probed' => 0])->and($node->fresh()->state)->toBe('draining');
});
