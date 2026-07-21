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
 * branding: {logo_url, primary_color, company_name, invoice_*}
 * allowed_products: null = all products, otherwise array of product_ids
 *
 * @property string $status
 * @property float  $markup_percent
 * @property array<string, string|null>|null $branding
 * @property array<int, int>|null            $allowed_products
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
        'support_email',
        'support_phone',
        'panel_title',
        'max_customers',
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

    /** @return HasMany<ResellerPricingOverride, $this> */
    public function pricingOverrides(): HasMany
    {
        return $this->hasMany(ResellerPricingOverride::class, 'reseller_id');
    }

    /**
     * Sub-customer cap (audit K109).
     *
     * A NULL cap means unlimited — that is the pre-existing behaviour and the
     * default for every reseller created before the column existed, so adding
     * the limit never retroactively locks anyone out.
     */
    public function hasCustomerLimit(): bool
    {
        return $this->max_customers !== null;
    }

    public function customerSlotsRemaining(): ?int
    {
        if (! $this->hasCustomerLimit()) {
            return null;
        }

        return max(0, (int) $this->max_customers - $this->customers()->count());
    }

    public function canAddCustomer(): bool
    {
        $remaining = $this->customerSlotsRemaining();

        return $remaining === null || $remaining > 0;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function approve(): void
    {
        $this->update(['status' => 'active', 'approved_at' => now()]);
    }

    /**
     * Optional own invoice-series prefix (audit 111).
     *
     * When set, invoices for this reseller's customers are numbered under this
     * prefix instead of the global series — the reseller acting as its own
     * invoicing entity. Sanitised to A–Z0–9 (max 8) to stay a valid series key;
     * empty/unset means "use the global series" (the default behaviour).
     */
    public function invoiceSeriesPrefix(): ?string
    {
        $raw = $this->branding['invoice_series'] ?? null;

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $key = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '');

        return $key === '' ? null : substr($key, 0, 8);
    }
}
