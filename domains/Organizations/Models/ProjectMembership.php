<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations\Models;

use Onhost\Platform\Eloquent\Model;

final class ProjectMembership extends Model
{
    protected static string $idPrefix = 'pmem';

    protected $table = 'project_memberships';
}
