<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Platform\OutboxDeadLetters;
use Onhost\Platform\Outbox\OutboxMessage;

/**
 * The outbox messages the relay gave up on (G7, TASK-0115): their name, age, attempts and last (already redacted) error —
 * never the payload. `--requeue` puts them back on the relay once the failing listener is fixed: the attempts start again
 * from zero and the next `onhost:outbox:relay` picks them up. Consumers dedupe on the message id, so a message delivered to
 * some listeners before it failed is not doubled where they keep that rule (docs/runbooks/outbox-dead-letters.md).
 */
final class OutboxDeadLettersCommand extends Command
{
    protected $signature = 'onhost:outbox:dead-letters {--requeue : put the listed messages back on the relay} {--id=* : only these message ids} {--name= : only messages of this event name} {--json : machine-readable output}';

    protected $description = 'List outbox messages the relay gave up on, and requeue them after the cause is fixed';

    public function handle(): int
    {
        $query = OutboxDeadLetters::query()->orderBy('created_at');
        $ids = array_values(array_filter((array) $this->option('id'), fn ($id) => is_string($id) && $id !== ''));
        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }
        $name = $this->option('name');
        if (is_string($name) && $name !== '') {
            $query->where('name', $name);
        }
        $rows = $query->limit(500)->get(['id', 'name', 'organization_id', 'attempts', 'last_error', 'created_at']);

        if ($this->option('requeue')) {
            $requeued = $rows->isEmpty() ? 0 : OutboxMessage::query()->whereIn('id', $rows->pluck('id')->all())->whereNull('published_at')
                ->update(['attempts' => 0, 'available_at' => now(), 'updated_at' => now()]);
            $this->info("{$requeued} message(s) back on the relay; the next onhost:outbox:relay delivers them.");

            return self::SUCCESS;
        }
        $present = $rows->map(fn (OutboxMessage $m) => ['id' => $m->id, 'name' => (string) $m->name, 'organization_id' => $m->organization_id, 'attempts' => (int) $m->attempts, 'created_at' => $m->created_at?->toIso8601String(), 'last_error' => (string) $m->last_error])->all();
        if ($this->option('json')) {
            $this->line((string) json_encode(['dead_letters' => $present], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }
        $this->table(['Id', 'Event', 'Organization', 'Attempts', 'Written', 'Last error'], array_map(fn (array $r) => [$r['id'], $r['name'], (string) $r['organization_id'], (string) $r['attempts'], (string) $r['created_at'], mb_substr($r['last_error'], 0, 120)], $present));
        $this->line(count($present).' dead letter(s)'.(count($present) > 0 ? ' — fix the listener, then --requeue' : ''));

        return self::SUCCESS;
    }
}
