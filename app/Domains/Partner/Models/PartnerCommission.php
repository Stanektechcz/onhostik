<?php

declare(strict_types=1);

namespace App\Domains\Partner\Models;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Order;
use App\Domains\Billing\Models\Payment;
use App\Domains\Partner\Enums\CommissionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property CommissionStatus $status
 * @property int $amount  minor units (haléře)
 */
class PartnerCommission extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'partner_profile_id',
        'partner_referral_id',
        'partner_payout_id',
        'order_id',
        'invoice_id',
        'payment_id',
        'amount',
        'currency',
        'rate_percent',
        'status',
        'eligible_at',
        'approved_at',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status'      => CommissionStatus::class,
            'eligible_at' => 'datetime',
            'approved_at' => 'datetime',
            'paid_at'     => 'datetime',
            'rate_percent' => 'float',
            'amount'      => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'amount', 'approved_at', 'paid_at', 'notes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('partner');
    }

    // ────────────────────────────────── relationships

    /** @return BelongsTo<PartnerProfile, $this> */
    public function partnerProfile(): BelongsTo
    {
        return $this->belongsTo(PartnerProfile::class);
    }

    /** @return BelongsTo<PartnerPayout, $this> */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(PartnerPayout::class, 'partner_payout_id');
    }

    /** @return BelongsTo<PartnerReferral, $this> */
    public function referral(): BelongsTo
    {
        return $this->belongsTo(PartnerReferral::class, 'partner_referral_id');
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    // ────────────────────────────────── helpers

    /** Commission as formatted CZK string for display. */
    public function formattedAmount(): string
    {
        return number_format($this->amount / 100, 0, ',', ' ') . ' ' . $this->currency;
    }
}
