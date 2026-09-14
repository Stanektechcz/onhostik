<?php

declare(strict_types=1);

namespace Onhost\Domain\Compliance\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Onhost\Platform\Eloquent\Model;

/** Security incident with regulatory obligations (NIS2 / GDPR / DSA Art. 18) — blueprint §23.2, §25. */
final class CyberIncident extends Model
{
    protected static string $idPrefix = 'sec';

    protected $table = 'cyber_incidents';

    public const STATES = ['OPEN', 'CONTAINED', 'REPORTED', 'CLOSED'];

    protected function casts(): array
    {
        return [
            'affected_services' => 'array', 'jurisdictions' => 'array', 'evidence' => 'array',
            'personal_data_breach' => 'boolean', 'life_safety_crime_suspicion' => 'boolean', 'nis2_scope' => 'boolean', 'detected_at' => 'datetime',
        ];
    }

    public function timers(): HasMany
    {
        return $this->hasMany(ComplianceTimer::class, 'case_id')->where('case_type', 'cyber_incident')->orderBy('deadline_at');
    }
}
