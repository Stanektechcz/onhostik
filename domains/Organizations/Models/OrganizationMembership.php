<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Onhost\Domain\Identity\Models\User;
use Onhost\Platform\Eloquent\Model;

/**
 * @property string $organization_id
 * @property string $user_id
 * @property string $role_key
 * @property ?Carbon $expires_at
 */
final class OrganizationMembership extends Model
{
    protected static string $idPrefix = 'mem';

    protected $table = 'organization_memberships';

    protected function casts(): array
    {
        return ['joined_at' => 'datetime', 'expires_at' => 'datetime']; // expires_at: access that ends on a date (H343)
    }

    /**
     * A membership that counts right now: active, and not past the date its access ends (H343). Everything that decides
     * access by membership alone — which organization a request speaks for, the panel's data, the console relay — asks
     * this, so an access that ended stops there at the same second as its permissions, swept or not.
     *
     * @param  Builder<OrganizationMembership>  $query
     * @return Builder<OrganizationMembership>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('state', 'active')->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
