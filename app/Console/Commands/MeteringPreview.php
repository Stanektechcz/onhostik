<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Services\UsageWatch;

/**
 * Read before switching `usage.rotation` on (TASK-0023 metering-core): measures the next batch the rotating watch would
 * visit — managed databases included — and says how many would be told, blocked or upgraded. It asks the panels (the
 * same quota and usage reads the watch makes) but writes nothing: no sample, no service tag, no event, no order, no
 * automation ledger entry.
 */
final class MeteringPreview extends Command
{
    protected $signature = 'onhost:metering:preview {--limit=200 : services to measure in this preview (at least the rotation share)} {--json : machine-readable output}';

    protected $description = 'Preview what the rotating usage watch would do for the next batch of services (reads the panels, writes nothing)';

    public function handle(UsageWatch $watch): int
    {
        $result = $watch->preview(max(1, (int) $this->option('limit')));
        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }
        $this->table(['what', 'services'], array_map(fn (string $key) => [$key, $result[$key] ?? '—'], array_keys($result)));

        return self::SUCCESS;
    }
}
