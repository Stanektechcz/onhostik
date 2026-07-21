<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Products\Models\Product;
use App\Domains\Customer\Models\Customer;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's review of a service they own (admin "Recenze" module).
 *
 * @property int    $rating
 * @property string $status
 */
class ServiceReview extends Model
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'customer_id', 'service_id', 'product_id',
        'rating', 'title', 'body', 'status',
        'moderated_by', 'moderated_at',
    ];

    protected function casts(): array
    {
        return [
            'rating'       => 'integer',
            'moderated_at' => 'datetime',
        ];
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /** @param Builder<ServiceReview> $query */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', self::STATUS_APPROVED);
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

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }
}
