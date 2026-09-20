<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * One service shared with one person (`sag_…`). The permission is carried by resource-scoped policy bindings; this row
 * is what the owner sees and revokes.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $service_id
 * @property string $email
 * @property ?string $user_id
 * @property list<string> $capabilities
 * @property string $state
 * @property ?string $invitation_id
 * @property ?string $granted_by
 * @property ?string $revoked_by
 * @property ?string $note
 * @property ?Carbon $expires_at
 * @property ?Carbon $accepted_at
 * @property ?Carbon $revoked_at
 * @property ?Carbon $created_at
 */
final class ServiceAccessGrant extends Model
{
    public const PENDING = 'pending';

    public const ACTIVE = 'active';

    public const REVOKED = 'revoked';

    public const EXPIRED = 'expired';

    protected static string $idPrefix = 'sag';

    protected $table = 'service_access_grants';

    protected function casts(): array
    {
        return ['capabilities' => 'array', 'expires_at' => 'datetime', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function isOpen(): bool
    {
        return in_array($this->state, [self::PENDING, self::ACTIVE], true) && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
