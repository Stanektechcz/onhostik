<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments\Models;

use Onhost\Platform\Eloquent\Model;
use Onhost\Platform\Money\Money;

final class PaymentIntent extends Model
{
    protected static string $idPrefix = 'pi';

    protected $table = 'payment_intents';

    protected function casts(): array
    {
        return ['return_urls' => 'array', 'raw' => 'array', 'amount_minor' => 'integer', 'refunded_minor' => 'integer', 'paid_at' => 'datetime'];
    }

    public function amount(): Money
    {
        return Money::minor($this->amount_minor, $this->currency);
    }

    public function isSucceeded(): bool
    {
        return in_array($this->state, [PaymentStateMachineStates::SUCCEEDED, PaymentStateMachineStates::PARTIALLY_REFUNDED, PaymentStateMachineStates::REFUNDED], true);
    }
}
