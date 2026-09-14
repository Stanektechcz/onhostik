<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Models;

use Onhost\Platform\Eloquent\Model;

final class TrustedDevice extends Model
{
    protected static string $idPrefix = 'dev';

    protected $table = 'trusted_devices';

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'last_seen_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
