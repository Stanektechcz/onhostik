<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Authorization\Models;

use Onhost\Platform\Eloquent\Model;

final class JitElevation extends Model
{
    protected static string $idPrefix = 'jit';

    protected $table = 'jit_elevations';

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime', 'ttl_minutes' => 'integer'];
    }

    public function isActive(): bool
    {
        return $this->state === 'approved' && $this->revoked_at === null && $this->expires_at !== null && $this->expires_at->isFuture();
    }
}
