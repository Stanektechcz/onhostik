<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Services\Penpot\PenpotSweep as Sweep;

/** TASK-0123: every running Penpot answers? daily backups due? (docs/runbooks/penpot.md) */
final class PenpotSweep extends Command
{
    protected $signature = 'onhost:penpot:sweep {--no-backups : probe only, start no backup} {--limit=500}';

    protected $description = 'Probe every running Penpot instance on its node and start its daily backup when due';

    public function handle(Sweep $sweep): int
    {
        $result = $sweep->run(! (bool) $this->option('no-backups'), max(1, (int) $this->option('limit')));
        $this->table(array_keys($result), [$result]);

        return self::SUCCESS;
    }
}
