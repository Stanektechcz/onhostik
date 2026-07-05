<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Models;

use App\Domains\Provisioning\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property 'up'|'down'|'degraded'|'unknown' $status
 */
class SlaUptimeCheck extends Model
{
    protected $fillable = [
        'service_id', 'status', 'response_ms', 'check_url', 'error_message', 'checked_at',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
    ];

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'up'       => 'Dostupný',
            'down'     => 'Nedostupný',
            'degraded' => 'Degradovaný',
            'unknown'  => 'Neznámý',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'up'       => 'badge bg-success',
            'down'     => 'badge bg-danger',
            'degraded' => 'badge bg-warning text-dark',
            'unknown'  => 'badge bg-secondary',
        };
    }
}
