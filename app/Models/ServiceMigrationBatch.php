<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property array<int,int> $service_ids
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 */
class ServiceMigrationBatch extends Model
{
    protected $fillable = [
        'name',
        'source_server_id',
        'target_server_id',
        'service_ids',
        'status',
        'created_by',
        'started_at',
        'completed_at',
        'log',
    ];

    protected function casts(): array
    {
        return [
            'service_ids' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
