<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Onhost\Platform\Eloquent\Model;

/**
 * Service incident (blueprint §73.2). The public status page shows only
 * `public` updates; security incidents never expose exploit detail.
 */
final class Incident extends Model
{
    protected static string $idPrefix = 'inc';

    protected $table = 'incidents';

    public const SEVERITIES = ['p1', 'p2', 'p3', 'p4'];

    /** Prototype INC_FLOW keys (Onhost-app.dc.html) per lifecycle state. */
    public const UI_STATES = [
        'DETECTED' => 'vysetrovani', 'INVESTIGATING' => 'vysetrovani', 'IDENTIFIED' => 'identifikovano',
        'MITIGATING' => 'identifikovano', 'MONITORING' => 'monitoring', 'RESOLVED' => 'vyreseno', 'POSTMORTEM' => 'vyreseno',
    ];

    protected function casts(): array
    {
        return [
            'components' => 'array', 'affected_services' => 'array', 'affected_organizations' => 'array', 'postmortem' => 'array', 'meta' => 'array',
            'security' => 'boolean', 'sla_relevant' => 'boolean',
            'started_at' => 'datetime', 'detected_at' => 'datetime', 'mitigated_at' => 'datetime', 'resolved_at' => 'datetime',
        ];
    }

    public function updates(): HasMany
    {
        return $this->hasMany(IncidentUpdate::class, 'incident_id')->orderBy('created_at');
    }

    public function isOpen(): bool
    {
        return ! in_array($this->state, ['RESOLVED', 'POSTMORTEM'], true);
    }

    public function uiState(): string
    {
        return self::UI_STATES[$this->state] ?? 'vysetrovani';
    }

    /** Seconds from start to resolution (or now while open). */
    public function durationSeconds(): int
    {
        $end = $this->resolved_at ?? now();

        return max(0, (int) $this->started_at->diffInSeconds($end));
    }

    public function durationLabel(): string
    {
        $s = $this->durationSeconds();
        $h = intdiv($s, 3600);
        $m = intdiv($s % 3600, 60);

        return $h > 0 ? sprintf('%d h %02d min', $h, $m) : sprintf('%d min', $m);
    }
}
