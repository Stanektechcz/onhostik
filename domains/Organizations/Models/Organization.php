<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Onhost\Domain\Identity\Models\User;
use Onhost\Platform\Eloquent\Model;

/**
 * @property ?Carbon $vat_checked_at TASK-0031: when VIES last answered about vat_checked_number
 * @property ?Carbon $vat_override_until TASK-0031: a staff override of the VAT status counts until then
 */
final class Organization extends Model
{
    use SoftDeletes;

    protected static string $idPrefix = 'org';

    protected $table = 'organizations';

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'feature_flags' => 'array',
            'auto_renew_default' => 'boolean',
            'vat_validated_at' => 'datetime',
            'vat_checked_at' => 'datetime', // TASK-0031: when VIES last answered about vat_checked_number
            'vat_override_until' => 'datetime',
            'closed_at' => 'datetime',
            'domain_renewal_reserve_days' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class, 'organization_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'organization_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(self::class, 'partner_organization_id');
    }

    public function isB2b(): bool
    {
        return $this->customer_class === 'b2b';
    }

    public function isActive(): bool
    {
        return $this->state === 'active';
    }
}
