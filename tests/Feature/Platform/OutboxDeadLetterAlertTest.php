<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Onhost\Domain\Platform\GoLiveChecks;
use Onhost\Domain\Platform\OutboxDeadLetters;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * G7 (TASK-0115): a message the relay gave up on after its attempts is never delivered, and nothing said so — the lag gauge
 * counted it as pending (keeping /healthz and the lag alert red for a reason the relay cannot fix) and the doctor counted
 * nothing. Now it has a doctor row with its count, age and remedy, its own gauges and alert rules, and a requeue command.
 */

function outboxDeadLetterRow(string $id, int $attempts, string $name = 'g7.test', ?string $writtenAt = null): void
{
    DB::table('outbox_messages')->insert(['id' => $id, 'aggregate_type' => 'test', 'aggregate_id' => 'x', 'name' => $name, 'payload' => '{"secret_token":"never-printed"}', 'correlation_id' => 'c',
        'available_at' => now()->subHours(2), 'attempts' => $attempts, 'last_error' => 'listener exploded', 'created_at' => $writtenAt ?? now()->subHours(3), 'updated_at' => now()]);
}

/** @return array<string,mixed> the doctor row of the dead letters */
function outboxDeadLetterDoctorRow(): array
{
    return collect((new GoLiveChecks)->rows())->firstWhere('check', 'no outbox dead letters');
}

it('agrees with the relay on what a dead letter is: the relay never picks a message at the limit again', function () {
    outboxDeadLetterRow('evt_g7_dead_limit_00000000000001', OutboxDeadLetters::MAX_ATTEMPTS);
    outboxDeadLetterRow('evt_g7_dead_limit_00000000000002', OutboxDeadLetters::MAX_ATTEMPTS - 1);
    Event::fake();

    app(OutboxPublisher::class)->relayPending();

    expect(OutboxMessage::query()->find('evt_g7_dead_limit_00000000000001')->published_at)->toBeNull()
        ->and(OutboxMessage::query()->find('evt_g7_dead_limit_00000000000002')->published_at)->not->toBeNull()
        ->and(OutboxDeadLetters::query()->pluck('id')->all())->toBe(['evt_g7_dead_limit_00000000000001']);
});

it('shows a doctor row with the count, the oldest age and the remedy, and nothing of the payload', function () {
    expect(outboxDeadLetterDoctorRow())->toMatchArray(['ok' => true, 'remedy' => '', 'blocking' => false]);

    outboxDeadLetterRow('evt_g7_dead_doctor_0000000000001', 10, 'invoice.issued', now()->subDays(2)->toDateTimeString());
    outboxDeadLetterRow('evt_g7_dead_doctor_0000000000002', 12, 'ticket.created');
    $row = outboxDeadLetterDoctorRow();

    expect($row['ok'])->toBeFalse()->and($row['area'])->toBe('observability')
        ->and($row['detail'])->toContain('2 message(s)')->toContain('oldest 2 d')->toContain('invoice.issued')->toContain('ticket.created')->not->toContain('never-printed')
        ->and($row['remedy'])->toContain('onhost:outbox:dead-letters')->toContain('--requeue')->toContain('docs/runbooks/outbox-dead-letters.md');
});

it('exports the dead letters as their own gauges and keeps them out of the outbox lag and /healthz', function () {
    config()->set('onhost.metrics.token', 'metrics-token');
    outboxDeadLetterRow('evt_g7_dead_metric_0000000000001', 10);

    $body = (string) $this->get('/metrics', ['Authorization' => 'Bearer metrics-token'])->assertOk()->getContent();
    expect($body)->toContain('onhost_outbox_dead_letters 1')->toContain('# TYPE onhost_outbox_dead_letter_oldest_seconds gauge')
        ->toContain('onhost_outbox_pending_oldest_seconds 0')
        ->and((int) preg_replace('/.*onhost_outbox_dead_letter_oldest_seconds (\d+).*/s', '$1', $body))->toBeGreaterThanOrEqual(3 * 3600 - 5);
    expect($this->getJson('/healthz')->json('checks.outbox.ok'))->toBeTrue();

    $rules = (string) file_get_contents(base_path('infra/monitoring/slo-alerts.yml'));
    expect($rules)->toContain('alert: OnhostOutboxDeadLetters')->toContain('expr: onhost_outbox_dead_letters > 0')
        ->toContain('alert: OnhostOutboxDeadLettersOld')->toContain('runbook: docs/runbooks/outbox-dead-letters.md')
        ->and(file_exists(base_path('docs/runbooks/outbox-dead-letters.md')))->toBeTrue();
});

it('lists the dead letters without their payload and puts them back on the relay with --requeue', function () {
    outboxDeadLetterRow('evt_g7_dead_requeue_000000000001', 10, 'order.paid');
    outboxDeadLetterRow('evt_g7_dead_requeue_000000000002', 10, 'ticket.created');

    $this->artisan('onhost:outbox:dead-letters')->expectsOutputToContain('order.paid')->doesntExpectOutputToContain('never-printed')->assertSuccessful();
    $this->artisan('onhost:outbox:dead-letters', ['--requeue' => true, '--id' => ['evt_g7_dead_requeue_000000000001']])->expectsOutputToContain('1 message(s) back on the relay')->assertSuccessful();

    expect(OutboxDeadLetters::query()->pluck('id')->all())->toBe(['evt_g7_dead_requeue_000000000002'])
        ->and(OutboxMessage::query()->find('evt_g7_dead_requeue_000000000001')->attempts)->toBe(0);
    Event::fake();
    app(OutboxPublisher::class)->relayPending();
    expect(OutboxMessage::query()->find('evt_g7_dead_requeue_000000000001')->published_at)->not->toBeNull();
});
