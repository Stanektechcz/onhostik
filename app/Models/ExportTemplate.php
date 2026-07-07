<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<int, string> $columns
 * @property array<string, mixed>|null $filters
 */
class ExportTemplate extends Model
{
    protected $fillable = [
        'name',
        'entity_type',
        'columns',
        'filters',
        'format',
        'created_by',
        'is_shared',
    ];

    protected function casts(): array
    {
        return [
            'columns'   => 'array',
            'filters'   => 'array',
            'is_shared' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
