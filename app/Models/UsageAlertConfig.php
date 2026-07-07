<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Provisioning\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $last_alerted_at
 */
class UsageAlertConfig extends Model
{
    protected $fillable = [
        'service_id',
        'user_id',
        'metric',
        'threshold_percent',
        'is_active',
        'last_alerted_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_alerted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
