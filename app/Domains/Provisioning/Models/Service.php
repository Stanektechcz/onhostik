<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Models;

use App\Domains\Billing\Models\OrderItem;
use App\Domains\Customer\Models\Customer;
use App\Domains\Monitoring\Models\Monitor;
use App\Domains\Products\Models\Product;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\ServiceMaintenanceWindow;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A provisioned service instance (webhosting, game server, VPS, domain).
 *
 * `external_id` is the backend identifier (AAPanel site id, Proxmox VMID,
 * Pterodactyl server id, WEDOS domain). Idempotency: drivers MUST check
 * for an existing external_id before any remote create.
 *
 * @property ProvisioningDriver|null $provisioning_driver
 * @property ServiceStatus $status
 * @property array<string, mixed>|null $resources
 * @property Carbon|null $next_due_date
 * @property Carbon|null $suspended_at
 * @property Carbon|null $terminated_at
 * @property bool $auto_renew
 * @property string|null $customer_note
 */
class Service extends Model
{
    /** @use HasFactory<\Database\Factories\ServiceFactory> */
    use HasFactory;
    use HasUuid;
    use LogsActivity;
    use SoftDeletes;

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
        'cancel_at_period_end',
        'cancellation_reason',
        'paused_at',
        'paused_until',
        'usage_snapshot',
        'auto_renew',
        'customer_note',
    ];

    protected function casts(): array
    {
        return [
            'provisioning_driver'  => ProvisioningDriver::class,
            'status'               => ServiceStatus::class,
            'resources'            => 'array',
            'next_due_date'        => 'date',
            'suspended_at'         => 'datetime',
            'terminated_at'        => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'paused_at'            => 'datetime',
            'paused_until'         => 'date',
            'usage_snapshot'       => 'array',
            'auto_renew'           => 'boolean',
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

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /** @return HasMany<ProvisioningTask, $this> */
    public function provisioningTasks(): HasMany
    {
        return $this->hasMany(ProvisioningTask::class);
    }

    /** @return HasOne<DomainRegistration, $this> */
    public function domainRegistration(): HasOne
    {
        return $this->hasOne(DomainRegistration::class);
    }

    /** @return HasMany<Monitor, $this> */
    public function monitors(): HasMany
    {
        return $this->hasMany(Monitor::class);
    }

    /** @return HasMany<ServiceAddonSubscription, $this> */
    public function addonSubscriptions(): HasMany
    {
        return $this->hasMany(ServiceAddonSubscription::class);
    }

    /** @return HasMany<ServiceMaintenanceWindow, $this> */
    public function maintenanceWindows(): HasMany
    {
        return $this->hasMany(ServiceMaintenanceWindow::class);
    }

    // ---------------------------------------------------------------- helpers

    public function isProvisioned(): bool
    {
        return $this->external_id !== null;
    }

    public function isPaused(): bool
    {
        return $this->paused_at !== null && $this->status === ServiceStatus::Suspended;
    }

    public function isCancelledAtPeriodEnd(): bool
    {
        return $this->cancel_at_period_end === true;
    }
}
