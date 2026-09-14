<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing\Models;

use Onhost\Platform\Eloquent\Model;

final class DunningAction extends Model
{
    protected static string $idPrefix = 'dna';

    protected $table = 'dunning_actions';

    protected function casts(): array
    {
        return ['meta' => 'array', 'performed_at' => 'datetime'];
    }
}
