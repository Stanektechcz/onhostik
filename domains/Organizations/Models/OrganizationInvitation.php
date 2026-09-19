<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * @property ?Carbon $expires_at
 * @property ?Carbon $accepted_at
 * @property ?Carbon $access_expires_at
 */
final class OrganizationInvitation extends Model
{
    protected static string $idPrefix = 'inv';

    protected $table = 'organization_invitations';

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime', 'access_expires_at' => 'datetime']; // access_expires_at: when the membership ends, not the invitation (H343)
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }
}
