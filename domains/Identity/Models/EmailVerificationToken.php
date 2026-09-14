<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Models;

use Onhost\Platform\Eloquent\Model;

final class EmailVerificationToken extends Model
{
    protected static string $idPrefix = 'evt';

    protected $table = 'email_verification_tokens';

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
