<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Provisioning\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceUptimeCheck extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'service_id',
        'check_type',
        'target',
        'is_up',
        'response_ms',
        'error_message',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_up'      => 'boolean',
            'checked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
