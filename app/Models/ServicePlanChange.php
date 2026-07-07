<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Products\Models\PricingPlan;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePlanChange extends Model
{
    protected $fillable = [
        'service_id',
        'from_plan_id',
        'to_plan_id',
        'changed_by_user_id',
        'reason',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<PricingPlan, $this> */
    public function fromPlan(): BelongsTo
    {
        return $this->belongsTo(PricingPlan::class, 'from_plan_id');
    }

    /** @return BelongsTo<PricingPlan, $this> */
    public function toPlan(): BelongsTo
    {
        return $this->belongsTo(PricingPlan::class, 'to_plan_id');
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    public function reasonLabel(): string
    {
        return match ($this->reason) {
            'customer_request' => 'Zákazník',
            'admin_override'   => 'Admin',
            'auto'             => 'Automaticky',
            default            => $this->reason ?? 'Neznámý',
        };
    }
}
