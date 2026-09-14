<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents\Models;

use Onhost\Platform\Eloquent\Model;

/** One shift of the on-call rota (audit §5r-1): a staff member carries the pager from `starts_at` until `ends_at`. */
final class OnCallShift extends Model
{
    protected static string $idPrefix = 'ocs';

    protected $table = 'oncall_shifts';

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }
}
