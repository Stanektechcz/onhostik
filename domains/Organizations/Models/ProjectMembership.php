<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * @property string $project_id
 * @property string $user_id
 * @property string $role_key
 * @property ?Carbon $expires_at
 */
final class ProjectMembership extends Model
{
    protected static string $idPrefix = 'pmem';

    protected $table = 'project_memberships';

    protected function casts(): array
    {
        return ['expires_at' => 'datetime']; // a project role that ends on a date (H343)
    }
}
