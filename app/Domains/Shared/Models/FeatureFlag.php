<?php

declare(strict_types=1);

namespace App\Domains\Shared\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string $label
 * @property string|null $description
 * @property bool $is_enabled
 * @property int $rollout_percent
 * @property list<int>|null $customer_ids
 */
class FeatureFlag extends Model
{
    protected $fillable = [
        'key',
        'label',
        'description',
        'is_enabled',
        'rollout_percent',
        'customer_ids',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled'      => 'boolean',
            'rollout_percent' => 'integer',
            'customer_ids'    => 'array',
        ];
    }
}
