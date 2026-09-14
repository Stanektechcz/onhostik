<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Models;

use Onhost\Platform\Eloquent\Model;

final class MfaChallenge extends Model
{
    protected static string $idPrefix = 'mfa';

    protected $table = 'mfa_challenges';

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'expires_at' => 'datetime', 'consumed_at' => 'datetime'];
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null && $this->expires_at->isFuture();
    }
}
