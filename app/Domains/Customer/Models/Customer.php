<?php

declare(strict_types=1);

namespace App\Domains\Customer\Models;

use App\Domains\Billing\Models\CreditTransaction;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Order;
use App\Domains\Billing\Models\Payment;
use App\Domains\Developer\Models\OAuthApplication;
use App\Domains\Dns\Models\DnsZone;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Models\SshKey;
use App\Domains\Reseller\Models\ResellerProfile;
use App\Domains\Shared\Enums\Currency;
use App\Domains\Shared\Enums\Locale;
use App\Domains\Shared\Traits\HasUuid;
use App\Domains\Support\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Customer entity — billing identity, separate from auth User.
 *
 * One User (login) has exactly one Customer (billing profile).
 * `credit_balance_cache` is a denormalized cache of the append-only
 * credit ledger; the authoritative value is always SUM(credit_transactions.amount).
 *
 * @property Currency $preferred_currency
 * @property Locale $preferred_locale
 * @property string|null $phone
 * @property string|null $company_name
 * @property Carbon|null $vat_validated_at
 * @property int|null $churn_risk_score
 * @property string|null $segment
 * @property Carbon|null $insights_updated_at
 * @property Carbon|null $onboarding_completed_at
 */
class Customer extends Model
{
    /** @use HasFactory<\Database\Factories\CustomerFactory> */
    use HasFactory;
    use HasUuid;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'reseller_id',
        'type',
        'email',
        'phone',
        'company_name',
        'vat_number',          // DIČ
        'registration_number', // IČ
        'preferred_currency',
        'preferred_locale',
        'country_code',
        'vat_validated_at',
        'admin_notes',
        'churn_risk_score',
        'segment',
        'insights_updated_at',
        'onboarding_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'preferred_currency'  => Currency::class,
            'preferred_locale'    => Locale::class,
            'vat_validated_at'         => 'datetime',
            'insights_updated_at'      => 'datetime',
            'onboarding_completed_at'  => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['email', 'phone', 'company_name', 'vat_number', 'registration_number', 'country_code'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('customer');
    }

    // ---------------------------------------------------------------- relations

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ResellerProfile, $this> */
    public function reseller(): BelongsTo
    {
        return $this->belongsTo(ResellerProfile::class, 'reseller_id');
    }

    /** @return HasMany<CustomerAddress, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<CreditTransaction, $this> */
    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    /** @return HasMany<Service, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /** @return HasMany<SupportTicket, $this> */
    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    /** @return HasMany<SshKey, $this> */
    public function sshKeys(): HasMany
    {
        return $this->hasMany(SshKey::class);
    }

    /** @return HasMany<DnsZone, $this> */
    public function dnsZones(): HasMany
    {
        return $this->hasMany(DnsZone::class);
    }

    /** @return HasMany<OAuthApplication, $this> */
    public function oauthApplications(): HasMany
    {
        return $this->hasMany(OAuthApplication::class);
    }

    // ---------------------------------------------------------------- helpers

    public function billingAddress(): ?CustomerAddress
    {
        return $this->addresses->firstWhere('type', 'billing')
            ?? $this->addresses->firstWhere('is_primary', true);
    }

    public function isCompany(): bool
    {
        return $this->type === 'company';
    }

    public function isVatPayer(): bool
    {
        return $this->vat_number !== null && $this->vat_validated_at !== null;
    }

    public function isCzech(): bool
    {
        return $this->country_code === 'CZ';
    }

    public function isEu(): bool
    {
        return in_array($this->country_code, self::EU_COUNTRIES, true);
    }

    /** EU member states (ISO 3166-1 alpha-2), 2026. */
    public const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
    ];
}
