<?php

declare(strict_types=1);

namespace Onhost\Domain\Advisories\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Where one vulnerability feed stopped (`cursor`, the feed's own resume point) and how its last run went.
 *
 * @property string $key
 * @property ?string $cursor
 * @property ?Carbon $last_run_at
 * @property ?Carbon $last_success_at
 * @property ?string $last_error
 * @property int $fetched
 */
final class AdvisorySource extends Model
{
    protected $table = 'security_advisory_sources';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_run_at' => 'datetime', 'last_success_at' => 'datetime', 'fetched' => 'integer'];
    }
}
