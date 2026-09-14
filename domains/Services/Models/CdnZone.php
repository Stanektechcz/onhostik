<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Models;

use Onhost\Platform\Eloquent\Model;

final class CdnZone extends Model
{
    protected static string $idPrefix = 'cdn';

    protected $table = 'cdn_zones';

    protected function casts(): array
    {
        return ['nameservers' => 'array', 'settings' => 'array', 'proxied_records' => 'array', 'activated_at' => 'datetime'];
    }
}
