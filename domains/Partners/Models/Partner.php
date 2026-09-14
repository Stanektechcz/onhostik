<?php

declare(strict_types=1);

namespace Onhost\Domain\Partners\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Onhost\Platform\Eloquent\Model;

/** Reseller/affiliate partner record attached to the partner's own organization. */
final class Partner extends Model
{
    protected static string $idPrefix = 'ptn';

    protected $table = 'partners';

    public const MODELS = ['share', 'oneoff'];

    public const STATES = ['applied', 'active', 'suspended', 'closed'];

    protected function casts(): array
    {
        return [
            'whitelabel' => 'array', 'application' => 'array', 'rate_pct' => 'integer', 'volume_3m_minor' => 'integer', 'model_effective_from' => 'date',
            'rate_locked_until' => 'datetime', 'approved_at' => 'datetime', 'tier_recomputed_at' => 'datetime',
        ];
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(PartnerCommission::class, 'partner_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(PartnerPayout::class, 'partner_id');
    }

    public function isActive(): bool
    {
        return $this->state === 'active';
    }
}
