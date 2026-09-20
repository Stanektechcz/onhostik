<?php

declare(strict_types=1);

namespace Onhost\Domain\Invoicing\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Onhost\Platform\Eloquent\Model;
use Onhost\Platform\Money\Money;

/** Issued invoices are immutable: number and business content never change (§64.2). */
final class Invoice extends Model
{
    protected static string $idPrefix = 'inv';

    protected $table = 'invoices';

    public const DRAFT = 'DRAFT';

    public const ISSUED = 'ISSUED';

    public const PAID = 'PAID';

    public const OVERDUE = 'OVERDUE';

    public const CANCELLED = 'CANCELLED';

    public const CREDITED = 'CREDITED';

    protected function casts(): array
    {
        return [
            'buyer' => 'array', 'seller' => 'array', 'tax_summary' => 'array', 'structured' => 'array', 'meta' => 'array',
            'subtotal_minor' => 'integer', 'discount_minor' => 'integer', 'tax_minor' => 'integer', 'total_minor' => 'integer', 'paid_minor' => 'integer',
            'issued_at' => 'datetime', 'supply_date' => 'date', 'due_at' => 'datetime', 'paid_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class, 'invoice_id')->orderBy('position');
    }

    public function total(): Money
    {
        return Money::minor($this->total_minor, $this->currency);
    }

    public function tax(): Money
    {
        return Money::minor($this->tax_minor, $this->currency);
    }

    /** Postpaid invoices post DR receivable / CR revenue / CR VAT when they are issued; paying one settles the receivable. */
    public function bookedAtIssue(): bool
    {
        return $this->type === 'invoice' && (bool) ($this->meta['postpaid'] ?? false);
    }

    public function outstanding(): Money
    {
        return Money::minor(max(0, $this->total_minor - $this->paid_minor), $this->currency);
    }

    public function isIssued(): bool
    {
        return $this->state !== self::DRAFT;
    }

    public function isCreditNote(): bool
    {
        return $this->type === 'credit_note';
    }

    /** Prototype slug for the API-backed store. */
    public function uiState(): string
    {
        return match ($this->state) {
            self::PAID => 'zaplacena',
            self::OVERDUE => 'po_splatnosti',
            self::CANCELLED, self::CREDITED => 'stornovana',
            default => 'vystavena',
        };
    }
}
