<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * @property bool $up
 * @property ?Carbon $last_success_at
 * @property ?Carbon $last_failure_at
 * @property ?Carbon $checked_at
 */
final class IntegrationHealth extends Model
{
    protected static string $idPrefix = 'ih';

    protected $table = 'integration_health';

    protected function casts(): array
    {
        return ['up' => 'boolean', 'last_success_at' => 'datetime', 'last_failure_at' => 'datetime', 'checked_at' => 'datetime', 'error_rate_1h' => 'float', 'budget_used_pct' => 'float'];
    }
}
