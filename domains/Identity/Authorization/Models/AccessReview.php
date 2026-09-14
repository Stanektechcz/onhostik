<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Authorization\Models;

use Onhost\Platform\Eloquent\Model;

final class AccessReview extends Model
{
    protected static string $idPrefix = 'acr';

    protected $table = 'access_reviews';

    protected function casts(): array
    {
        return ['findings' => 'array', 'completed_at' => 'datetime'];
    }
}
