<?php

declare(strict_types=1);

namespace App\Domains\Billing\Models;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\InvoiceType;
use App\Domains\Billing\Enums\VatScenario;
use App\Domains\Customer\Models\Customer;
use App\Domains\Shared\Casts\MoneyCast;
use App\Domains\Shared\Enums\Currency;
use App\Domains\Shared\Traits\HasUuid;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Invoice with an immutable billing snapshot.
 *
 * The customer's billing details are COPIED into snapshot columns at issue
 * time. Later changes to the Customer record never affect issued invoices
 * (legal requirement for tax documents).
 *
 * @property InvoiceType $type
 * @property InvoiceStatus $status
 * @property VatScenario $vat_scenario
 * @property Currency $currency
 * @property Money $subtotal
 * @property Money $tax_amount
 * @property Money $total
 * @property Carbon|null $issue_date
 * @property Carbon|null $due_date
 * @property Carbon|null $paid_at
 * @property Carbon|null $renewal_applied_at
 */
class Invoice extends Model
{
    use HasUuid;
    use LogsActivity;

    protected $fillable = [
        'customer_id',
        'order_id',
        'parent_invoice_id', // proforma → tax doc / invoice → credit note linkage
        'renewal_service_id', // set only on renewal invoices — idempotency key with due_date
        'type',
        'purpose',           // order | credit_topup — what a paid invoice triggers
        'series',
        'number',
        'status',
        'vat_scenario',
        'currency',
        'subtotal',
        'tax_amount',
        'total',
        'variable_symbol',
        'issue_date',
        'taxable_supply_date',
        'due_date',
        'paid_at',
        'renewal_applied_at', // set once a renewal invoice's payment has extended Service.next_due_date
        'pdf_path',
        'notes',
        // --- billing snapshot ---
        'snapshot_name',
        'snapshot_company',
        'snapshot_street',
        'snapshot_city',
        'snapshot_zip',
        'snapshot_country_code',
        'snapshot_vat_number',
        'snapshot_registration_number',
    ];

    protected function casts(): array
    {
        return [
            'type'                => InvoiceType::class,
            'status'              => InvoiceStatus::class,
            'vat_scenario'        => VatScenario::class,
            'currency'            => Currency::class,
            'subtotal'            => MoneyCast::class . ':currency',
            'tax_amount'          => MoneyCast::class . ':currency',
            'total'               => MoneyCast::class . ':currency',
            'issue_date'          => 'date',
            'taxable_supply_date' => 'date',
            'due_date'            => 'date',
            'paid_at'             => 'datetime',
            'renewal_applied_at'  => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'number', 'total', 'paid_at'])
            ->logOnlyDirty()
            ->useLogName('invoice');
    }

    // ---------------------------------------------------------------- relations

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<\App\Domains\Provisioning\Models\Service, $this> */
    public function renewalService(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Provisioning\Models\Service::class, 'renewal_service_id');
    }

    /** @return HasMany<InvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function parentInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_invoice_id');
    }

    // ---------------------------------------------------------------- helpers

    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->due_date?->isPast();
    }

    public function isTaxDocument(): bool
    {
        return $this->type->isTaxDocument();
    }
}
