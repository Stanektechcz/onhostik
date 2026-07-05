<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property 'investigating'|'identified'|'monitoring'|'resolved' $status
 */
class SlaIncidentUpdate extends Model
{
    protected $fillable = ['incident_id', 'message', 'status', 'created_by'];

    protected $casts = ['incident_id' => 'integer'];

    /** @return BelongsTo<SlaIncident, $this> */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(SlaIncident::class, 'incident_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'investigating' => 'Šetření',
            'identified'    => 'Identifikováno',
            'monitoring'    => 'Monitoring',
            'resolved'      => 'Vyřešeno',
        };
    }
}
