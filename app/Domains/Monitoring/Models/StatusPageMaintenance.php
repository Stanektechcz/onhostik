<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property Carbon $scheduled_start_at
 * @property Carbon $scheduled_end_at
 * @property string $status
 */
class StatusPageMaintenance extends Model
{
    protected $fillable = [
        'title',
        'description',
        'scheduled_start_at',
        'scheduled_end_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at'   => 'datetime',
        ];
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'in_progress' => 'Probíhá',
            'completed'   => 'Dokončeno',
            default       => 'Naplánováno',
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'in_progress' => 'warning',
            'completed'   => 'success',
            default       => 'info',
        };
    }

    public function isUpcoming(): bool
    {
        return $this->status === 'scheduled' && $this->scheduled_start_at->isFuture();
    }
}
