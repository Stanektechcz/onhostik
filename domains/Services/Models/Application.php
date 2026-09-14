<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Models;

use Onhost\Platform\Eloquent\Model;

/** ONhost Apps projection (Kubernetes executor). */
final class Application extends Model
{
    protected static string $idPrefix = 'app';

    protected $table = 'applications';

    protected function casts(): array
    {
        return ['port' => 'integer', 'env' => 'array', 'secret_refs' => 'array', 'domains' => 'array', 'replicas' => 'integer'];
    }
}
