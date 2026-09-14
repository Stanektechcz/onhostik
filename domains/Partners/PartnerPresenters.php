<?php

declare(strict_types=1);

namespace Onhost\Domain\Partners;

use Onhost\Domain\Partners\Models\Partner;
use Onhost\Domain\Partners\Models\PartnerPayout;
use Onhost\Platform\Money\Money;

final class PartnerPresenters
{
    public static function partner(Partner $p, bool $internal = false): array
    {
        return [
            'id' => $p->id, 'organization_id' => $p->organization_id, 'code' => $p->code, 'model' => $p->model, 'tier' => $p->tier, 'rate' => $p->rate_pct, 'rate_locked_until' => $p->rate_locked_until?->toIso8601String(),
            'volume_3m' => Money::minor((int) $p->volume_3m_minor, $p->currency), 'currency' => $p->currency, 'state' => $p->state, 'whitelabel' => $p->whitelabel, 'iban_masked' => $p->iban ? substr($p->iban, 0, 4).str_repeat('•', max(0, strlen($p->iban) - 8)).substr($p->iban, -4) : null,
            'application' => $internal ? $p->application : null, 'approved_at' => $p->approved_at?->toIso8601String(), 'created_at' => $p->created_at?->toIso8601String(),
            'pending_model' => $p->pending_model, 'model_effective_from' => $p->model_effective_from?->toDateString(), // §5m-1
            'payout_terms' => $p->payout_terms ?: 'on_request', 'whitelabel_scope' => $p->whitelabel_scope ?: 'basic', // §5n-1
        ];
    }

    public static function payout(PartnerPayout $p): array
    {
        return [
            'id' => $p->id, 'number' => $p->number, 'partner_id' => $p->partner_id, 'amount' => Money::minor($p->amount_minor, $p->currency), 'method' => $p->method, 'state' => $p->state,
            'iban_masked' => $p->iban ? substr($p->iban, 0, 4).'…'.substr($p->iban, -4) : null, 'self_billing' => $p->self_billing, 'payment_reference' => $p->payment_reference, 'note' => $p->note,
            'requested_at' => $p->requested_at?->toIso8601String(), 'paid_at' => $p->paid_at?->toIso8601String(),
        ];
    }
}
