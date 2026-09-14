<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Models;

use Onhost\Platform\Eloquent\Model;

final class IntegrationHealth extends Model
{
    protected static string $idPrefix = 'ih';

    protected $table = 'integration_health';

    protected function casts(): array
    {
        return ['up' => 'boolean', 'last_success_at' => 'datetime', 'last_failure_at' => 'datetime', 'checked_at' => 'datetime', 'error_rate_1h' => 'float', 'budget_used_pct' => 'float'];
    }
}
