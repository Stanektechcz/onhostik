<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * One public key on one shell account, and whose it is (Brain card H185).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $service_id
 * @property string $target_remote_id
 * @property ?string $target_label
 * @property ?string $owner_user_id
 * @property string $fingerprint
 * @property string $state
 * @property ?string $revoke_operation_id
 * @property int $revoke_attempts
 * @property ?string $last_error
 * @property ?Carbon $installed_at
 * @property ?Carbon $revoke_requested_at
 * @property ?Carbon $revoked_at
 */
final class SshKeyGrant extends Model
{
    public const ACTIVE = 'active';

    public const REVOKING = 'revoking'; // asked for, not confirmed by the panel yet: the key may still open a session

    public const REVOKED = 'revoked';

    public const REPLACED = 'replaced'; // another key took its place on the same account

    protected static string $idPrefix = 'skg';

    protected $table = 'ssh_key_grants';

    protected function casts(): array
    {
        return ['revoke_attempts' => 'integer', 'installed_at' => 'datetime', 'revoke_requested_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
