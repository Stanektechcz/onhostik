<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Models;

use Onhost\Platform\Eloquent\Model;

/** AUTH-ID handling: encrypted at rest, short-lived, wiped after use, never logged (blueprint §46.5). */
final class DomainTransferSecret extends Model
{
    protected static string $idPrefix = 'dts';

    protected $table = 'domain_transfer_secrets';

    protected $hidden = ['auth_info'];

    protected function casts(): array
    {
        return ['auth_info' => 'encrypted', 'expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    public function consume(): string
    {
        $value = (string) $this->auth_info;
        $this->forceFill(['auth_info' => str_repeat('*', 8), 'used_at' => now()])->save();

        return $value;
    }
}
