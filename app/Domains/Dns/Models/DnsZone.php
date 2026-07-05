<?php

declare(strict_types=1);

namespace App\Domains\Dns\Models;

use App\Domains\Customer\Models\Customer;
use App\Domains\Dns\Enums\DnsZoneStatus;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $customer_id
 * @property int|null $service_id
 * @property string $domain
 * @property DnsZoneStatus $status
 * @property string $provider
 * @property array<string>|null $nameservers
 * @property Carbon|null $ns_updated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class DnsZone extends Model
{
    use HasUuid;

    protected $fillable = [
        'customer_id',
        'service_id',
        'domain',
        'status',
        'provider',
        'nameservers',
        'ns_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'status'       => DnsZoneStatus::class,
            'nameservers'  => 'array',
            'ns_updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return HasMany<DnsRecord, $this> */
    public function records(): HasMany
    {
        return $this->hasMany(DnsRecord::class);
    }
}
