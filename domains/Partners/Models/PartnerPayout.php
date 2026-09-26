<?php

declare(strict_types=1);

namespace Onhost\Domain\Partners\Models;

use Onhost\Platform\Eloquent\Model;
use Onhost\Platform\Money\Money;

/**
 * Payout request with the self-billed invoice snapshot (`self_billing`).
 *
 * @property array<string,mixed>|null $self_billing cast `array` (the column is JSON text; TASK-0031 review round 3 reads it as the document)
 */
final class PartnerPayout extends Model
{
    protected static string $idPrefix = 'pay';

    protected $table = 'partner_payouts';

    public const STATES = ['requested', 'approved', 'paid', 'rejected'];

    protected function casts(): array
    {
        return ['self_billing' => 'array', 'amount_minor' => 'integer', 'requested_at' => 'datetime', 'paid_at' => 'datetime'];
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
