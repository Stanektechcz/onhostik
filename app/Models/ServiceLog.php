<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Provisioning\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $logged_at
 * @property array<string, mixed>|null $context
 */
class ServiceLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'service_id',
        'level',
        'source',
        'message',
        'context',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'context'   => 'array',
            'logged_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
