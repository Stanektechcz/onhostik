<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders\Models;

use Onhost\Platform\Eloquent\Model;

final class Cart extends Model
{
    protected static string $idPrefix = 'cart';

    protected $table = 'carts';

    protected function casts(): array
    {
        return ['items' => 'array', 'expires_at' => 'datetime', 'commit_months' => 'integer'];
    }
}
