<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Provisioning\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceFirewallRule extends Model
{
    protected $table = 'service_firewall_rules';

    protected $fillable = [
        'service_id',
        'direction',
        'protocol',
        'port_from',
        'port_to',
        'ip_cidr',
        'action',
        'is_active',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'port_from' => 'integer',
            'port_to'   => 'integer',
        ];
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @param  Builder<ServiceFirewallRule>  $query
     * @return Builder<ServiceFirewallRule>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
