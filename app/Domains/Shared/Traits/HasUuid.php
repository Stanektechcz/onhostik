<?php

declare(strict_types=1);

namespace App\Domains\Shared\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Adds a secondary `uuid` column (public identifier) while keeping
 * the auto-increment integer primary key for join performance.
 */
trait HasUuid
{
    public static function bootHasUuid(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->getAttribute('uuid'))) {
                $model->setAttribute('uuid', (string) Str::uuid7());
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeWhereUuid($query, string $uuid): mixed
    {
        return $query->where('uuid', $uuid);
    }
}
