<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations\Models;

use Onhost\Platform\Eloquent\Model;

final class OrganizationInvitation extends Model
{
    protected static string $idPrefix = 'inv';

    protected $table = 'organization_invitations';

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }
}
