<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Models;

use Onhost\Platform\Eloquent\Model;

/** Managed database projection (single-tenant KVM built by the VPS saga). */
final class DatabaseInstance extends Model
{
    protected static string $idPrefix = 'dbi';

    protected $table = 'database_instances';

    protected function casts(): array
    {
        return ['port' => 'integer', 'pitr' => 'boolean', 'external_access' => 'boolean', 'allowlist' => 'array'];
    }
}
