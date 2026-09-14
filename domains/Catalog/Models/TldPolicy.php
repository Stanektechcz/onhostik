<?php

declare(strict_types=1);

namespace Onhost\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Model;

final class TldPolicy extends Model
{
    protected $table = 'tld_policies';

    protected $primaryKey = 'tld';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'periods' => 'array', 'registrable' => 'boolean', 'nsset_required' => 'boolean', 'dnssec_supported' => 'boolean',
            'idn' => 'boolean', 'meta' => 'array', 'default_period' => 'integer', 'grace_days' => 'integer', 'redemption_days' => 'integer', 'async_sla_hours' => 'integer',
        ];
    }

    public function allowsPeriod(int $years): bool
    {
        return in_array($years, $this->periods ?? [1], true);
    }
}
