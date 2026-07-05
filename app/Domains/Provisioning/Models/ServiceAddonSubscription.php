<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $cancelled_at
 */
class ServiceAddonSubscription extends Model
{
    protected $fillable = [
        'service_id',
        'service_addon_id',
        'quantity',
        'activated_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<ServiceAddon, $this> */
    public function addon(): BelongsTo
    {
        return $this->belongsTo(ServiceAddon::class, 'service_addon_id');
    }

    public function isActive(): bool
    {
        return $this->cancelled_at === null;
    }
}
