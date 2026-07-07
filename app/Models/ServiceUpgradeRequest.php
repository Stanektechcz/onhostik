<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Provisioning\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceUpgradeRequest extends Model
{
    protected $fillable = [
        'service_id',
        'user_id',
        'requested_plan_id',
        'requested_resources',
        'status',
        'customer_note',
        'admin_note',
        'handled_by',
    ];

    protected function casts(): array
    {
        return [
            'requested_resources' => 'array',
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
