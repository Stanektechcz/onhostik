<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Onhost\Domain\Identity\Models\User;
use Onhost\Platform\Eloquent\Model;

final class OrganizationMembership extends Model
{
    protected static string $idPrefix = 'mem';

    protected $table = 'organization_memberships';

    protected function casts(): array
    {
        return ['joined_at' => 'datetime'];
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
