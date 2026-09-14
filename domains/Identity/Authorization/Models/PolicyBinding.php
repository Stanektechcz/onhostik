<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Authorization\Models;

use Onhost\Platform\Eloquent\Model;

final class PolicyBinding extends Model
{
    protected static string $idPrefix = 'pb';

    protected $table = 'policy_bindings';

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
