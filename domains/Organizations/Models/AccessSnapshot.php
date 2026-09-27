<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * What a person could do in an organization just before a removal or a role change (permission program I10, TASK-0042).
 *
 * @property string $organization_id
 * @property string $user_id
 * @property string $reason
 * @property array{membership: ?array{role: string, state: string, expires_at: ?string}, bindings: list<array{role: string, scope_type: string, scope_id: ?string, expires_at: ?string}>, projects: list<array{project_id: string, role: string, expires_at: ?string}>, shares: list<array{grant_id: string, service_id: string, capabilities: list<string>, expires_at: ?string}>} $access
 * @property ?string $taken_by
 * @property Carbon $expires_at
 * @property ?Carbon $restored_at
 * @property ?string $restored_by
 * @property ?Carbon $created_at
 */
final class AccessSnapshot extends Model
{
    protected static string $idPrefix = 'asn';

    protected $table = 'access_snapshots';

    protected function casts(): array
    {
        return ['access' => 'array', 'expires_at' => 'datetime', 'restored_at' => 'datetime'];
    }
}
