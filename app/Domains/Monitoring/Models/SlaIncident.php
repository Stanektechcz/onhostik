<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Models;

use App\Domains\Provisioning\Models\Service;
use App\Domains\Shared\Traits\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property 'critical'|'high'|'medium'|'low' $severity
 * @property 'open'|'investigating'|'resolved' $status
 */
class SlaIncident extends Model
{
    use HasUuid;

    protected $fillable = [
        'service_id', 'title', 'description', 'severity', 'status',
        'started_at', 'resolved_at', 'downtime_minutes', 'sla_breached',
        'credit_haler', 'created_by',
    ];

    protected $casts = [
        'started_at'    => 'datetime',
        'resolved_at'   => 'datetime',
        'sla_breached'  => 'boolean',
    ];

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<SlaIncidentUpdate, $this> */
    public function updates(): HasMany
    {
        return $this->hasMany(SlaIncidentUpdate::class, 'incident_id');
    }

    public function severityLabel(): string
    {
        return match ($this->severity) {
            'critical' => 'Kritická',
            'high'     => 'Vysoká',
            'medium'   => 'Střední',
            'low'      => 'Nízká',
        };
    }

    public function severityBadgeClass(): string
    {
        return match ($this->severity) {
            'critical' => 'badge bg-danger',
            'high'     => 'badge bg-warning text-dark',
            'medium'   => 'badge bg-info text-dark',
            'low'      => 'badge bg-secondary',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'open'          => 'Otevřená',
            'investigating' => 'Šetření',
            'resolved'      => 'Vyřešená',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'open'          => 'badge bg-danger',
            'investigating' => 'badge bg-warning text-dark',
            'resolved'      => 'badge bg-success',
        };
    }

    public function durationLabel(): string
    {
        $min = $this->downtime_minutes;
        if ($min < 60) {
            return "{$min} min";
        }
        $h = intdiv($min, 60);
        $m = $min % 60;
        return $m > 0 ? "{$h}h {$m}min" : "{$h}h";
    }
}
