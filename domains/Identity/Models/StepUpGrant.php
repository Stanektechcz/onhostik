<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Models;

use Onhost\Platform\Eloquent\Model;

final class StepUpGrant extends Model
{
    protected static string $idPrefix = 'sug';

    protected $table = 'step_up_grants';

    protected function casts(): array
    {
        return ['granted_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function isValid(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
