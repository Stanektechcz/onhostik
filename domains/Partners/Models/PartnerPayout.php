<?php

declare(strict_types=1);

namespace Onhost\Domain\Partners\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;
use Onhost\Platform\Money\Money;

/**
 * Payout request with the self-billed invoice snapshot (`self_billing`).
 *
 * @property array<string,mixed>|null $self_billing cast `array` (the column is JSON text; TASK-0031 review round 3 reads it as the document)
 * @property Carbon|null $requested_at cast `datetime`
 * @property Carbon|null $paid_at cast `datetime`
 * @property Carbon|null $approved_at cast `datetime` (TASK-0040: approved by somebody other than who pays)
 * @property Carbon|null $frozen_at cast `datetime` (TASK-0040: held for a look)
 */
final class PartnerPayout extends Model
{
    protected static string $idPrefix = 'pay';

    protected $table = 'partner_payouts';

    public const STATES = ['requested', 'approved', 'paid', 'rejected'];

    protected function casts(): array
    {
        return ['self_billing' => 'array', 'amount_minor' => 'integer', 'requested_at' => 'datetime', 'paid_at' => 'datetime', 'approved_at' => 'datetime', 'frozen_at' => 'datetime'];
    }

    /** Held for a look (TASK-0040): neither approved nor paid until finance releases it; a rejection is still possible. */
    public function isFrozen(): bool
    {
        return $this->frozen_at !== null;
    }

    /** Who approved it: `approved_by` since TASK-0040, `decided_by` for an approval recorded before (the pay overwrote it only then). */
    public function approver(): ?string
    {
        return $this->approved_by ?? ($this->state === 'approved' ? $this->decided_by : null);
    }

    /** The commission itself (the self-billing document's net). */
    public function net(): Money
    {
        return Money::minor($this->amount_minor, $this->currency);
    }

    /**
     * The VAT the self-billing document states (TASK-0031 review): the document is what is paid, so a VAT-payer partner gets
     * net + VAT. Read from the snapshot written at the request — a later change of the partner's status never changes it.
     * A document in another currency than the payout, or with a negative tax, says nothing payable and counts as no VAT.
     */
    public function documentTax(): Money
    {
        $tax = (array) (($this->self_billing ?? [])['tax'] ?? []);
        $minor = (int) ($tax['minor'] ?? 0);

        return $minor > 0 && strtoupper((string) ($tax['currency'] ?? $this->currency)) === strtoupper((string) $this->currency)
            ? Money::minor($minor, $this->currency) : Money::zero($this->currency);
    }

    /** What the bank transfer (or the offset) must carry: the document's total. */
    public function transferAmount(): Money
    {
        return $this->net()->add($this->documentTax());
    }
}
