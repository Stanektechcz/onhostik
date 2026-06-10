<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Models;

use App\Domains\Billing\Models\OrderItem;
use App\Domains\Customer\Models\Customer;
use App\Domains\Products\Models\Product;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A provisioned service instance (webhosting, game server, VPS, domain).
 *
 * `external_id` is the backend identifier (AAPanel site id, Proxmox VMID,
 * Pterodactyl server id, WEDOS domain). Idempotency: drivers MUST check
 * for an existing external_id before any remote create.
 */
class Service extends Model
{
    use HasFactory;
    use HasUuid;
    use LogsActivity;
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = [
        'customer_id',
        'order_item_id',
        'product_id',
        'server_id',
        'provisioning_driver',
        'external_id',
        'status',
        'label',            // customer-facing name, e.g. domain or hostname
        'resources',        // resolved resource config snapshot from plan
        'next_due_date',
        'suspended_at',
        'terminated_at',
        'suspension_reason',
    ];

    protected function casts(): array
    {
        return [
            'provisioning_driver' => ProvisioningDriver::class,
            'status'              => ServiceStatus::class,
            'resources'           => 'array',
            'next_due_date'       => 'date',
            'suspended_at'        => 'datetime',
            'terminated_at'       => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'external_id', 'next_due_date', 'suspension_reason'])
            ->logOnlyDirty()
            ->useLogName('service');
    }

    // ---------------------------------------------------------------- relations

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function provisioningTasks(): HasMany
    {
        return $this->hasMany(ProvisioningTask::class);
    }

    public function domainRegistration(): HasOne
    {
        return $this->hasOne(DomainRegistration::class);
    }

    // ---------------------------------------------------------------- helpers

    public function isProvisioned(): bool
    {
        return $this->external_id !== null;
    }
}
