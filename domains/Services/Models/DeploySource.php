<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Models;

use Onhost\Platform\Eloquent\Model;

final class DeploySource extends Model
{
    protected static string $idPrefix = 'dsr';

    protected $table = 'deploy_sources';

    protected function casts(): array
    {
        return ['env' => 'array', 'hooks' => 'array', 'auto_deploy' => 'boolean', 'last_deployed_at' => 'datetime'];
    }
}
