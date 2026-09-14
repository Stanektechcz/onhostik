<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Models;

use Illuminate\Database\Eloquent\Model;

/** One check of an uptime monitor (auto-increment id, pruned after the retention window). */
final class UptimeSample extends Model
{
    protected $table = 'uptime_samples';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime', 'ok' => 'boolean'];
    }
}
