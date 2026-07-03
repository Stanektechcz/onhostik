<?php

declare(strict_types=1);

namespace App\Domains\Reseller\Models;

use App\Domains\Customer\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reseller account — a customer who can resell OnHost products under a markup.
 *
 * Status values: pending, active, suspended, rejected.
 * branding: {logo_url, primary_color, company_name}
 * allowed_products: null = all products, otherwise array of product_ids
 *
 * @property string $status
 * @property float  $markup_percent
 */
class ResellerProfile extends Model
{
    /** @use HasFactory<\Database\Factories\ResellerProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_name',
        'custom_domain',
        'markup_percent',
        'status',
        'branding',
        'allowed_products',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'markup_percent'  => 'float',
            'branding'        => 'array',
            'allowed_products' => 'array',
            'approved_at'     => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Customer, $this> */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'reseller_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function approve(): void
    {
        $this->update(['status' => 'active', 'approved_at' => now()]);
    }
}
