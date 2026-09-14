<?php

declare(strict_types=1);

namespace Onhost\Domain\Catalog\Models;

use Onhost\Platform\Eloquent\Model;

final class ProductOption extends Model
{
    protected static string $idPrefix = 'opt';

    protected $table = 'product_options';

    protected function casts(): array
    {
        return ['label' => 'array', 'price_per_unit_minor' => 'array', 'choices' => 'array', 'meta' => 'array', 'min' => 'float', 'max' => 'float', 'step' => 'float', 'default_value' => 'float', 'sort' => 'integer'];
    }
}
