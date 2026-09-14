<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Models;

use Onhost\Platform\Eloquent\Model;

/** Shared/managed web site projection (ISPConfig or aaPanel executor). */
final class Website extends Model
{
    protected static string $idPrefix = 'web';

    protected $table = 'websites';

    protected function casts(): array
    {
        return ['aliases' => 'array', 'https_forced' => 'boolean', 'remote_client_id' => 'integer', 'remote_site_id' => 'integer', 'quota' => 'array'];
    }
}
