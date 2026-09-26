<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One usage reading of one metric of a service (raw, per hour) or its roll-up (per day, per month). `value` null means
 * the reading could not be taken — never 0. Telemetry only; billing reads `usage_events`, never this table.
 */
final class ServiceUsageSample extends Model
{
    public const GRANULARITY_SAMPLE = 'sample';

    public const GRANULARITY_DAY = 'day';

    public const GRANULARITY_MONTH = 'month';

    public const UPDATED_AT = null;

    protected $table = 'service_usage_samples';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'window_start' => 'datetime', 'window_end' => 'datetime', 'observed_at' => 'datetime', 'created_at' => 'datetime',
            'value' => 'integer', 'limit_value' => 'integer', 'samples_total' => 'integer', 'samples_measured' => 'integer',
        ];
    }
}
