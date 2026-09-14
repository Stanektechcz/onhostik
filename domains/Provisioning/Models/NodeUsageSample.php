<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Models;

use Onhost\Platform\Eloquent\Model;

/** One hourly reading of a node's load (and, when a probe reports it, its measured power). */
final class NodeUsageSample extends Model
{
    protected static string $idPrefix = 'nus';

    protected $table = 'node_usage_samples';

    protected function casts(): array
    {
        return ['sampled_at' => 'datetime', 'cpu_pct' => 'integer', 'ram_used_mb' => 'integer', 'disk_used_gb' => 'integer', 'power_w' => 'integer'];
    }
}
