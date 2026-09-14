<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents\Models;

use Onhost\Platform\Eloquent\Model;

/** External synthetic probe definition (blueprint §66.6): ≥3 locations per component, 2/3 quorum. */
final class SlaProbe extends Model
{
    protected static string $idPrefix = 'prb';

    protected $table = 'sla_probes';

    protected $hidden = ['token_hash'];

    public const KINDS = ['http', 'dns', 'tcp', 'icmp'];

    protected function casts(): array
    {
        return ['expected' => 'array', 'interval_seconds' => 'integer', 'last_seen_at' => 'datetime'];
    }
}
