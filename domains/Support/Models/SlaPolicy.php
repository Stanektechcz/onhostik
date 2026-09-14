<?php

declare(strict_types=1);

namespace Onhost\Domain\Support\Models;

use Onhost\Platform\Eloquent\Model;

/** Support response targets per priority in minutes (blueprint §68.5) — separate from uptime SLA. */
final class SlaPolicy extends Model
{
    protected static string $idPrefix = 'slap';

    protected $table = 'support_sla_policies';

    protected function casts(): array
    {
        return ['targets' => 'array', 'business_hours' => 'array', 'business_hours_only' => 'boolean', 'version' => 'integer'];
    }

    /** @return array{ack:int, first:int, next:int, resolve:int} */
    public function targetsFor(string $priority): array
    {
        $t = (array) ($this->targets[$priority] ?? $this->targets['p3'] ?? []);

        return ['ack' => (int) ($t['ack'] ?? 60), 'first' => (int) ($t['first'] ?? 240), 'next' => (int) ($t['next'] ?? 480), 'resolve' => (int) ($t['resolve'] ?? 4320)];
    }
}
