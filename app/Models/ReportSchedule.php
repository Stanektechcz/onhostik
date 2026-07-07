<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property array<int,string> $recipients
 * @property Carbon|null $last_run_at
 * @property Carbon|null $next_run_at
 */
class ReportSchedule extends Model
{
    protected $fillable = [
        'name',
        'report_type',
        'frequency',
        'recipients',
        'format',
        'is_active',
        'last_run_at',
        'next_run_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'recipients' => 'array',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }
}
