<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing\Models;

use Onhost\Platform\Eloquent\Model;
use Onhost\Platform\Money\Money;

/** Priced usage event; `charged_transaction_id` is set once the wallet ledger transaction exists. */
final class RatedUsage extends Model
{
    protected static string $idPrefix = 'ru';

    protected $table = 'rated_usage';

    protected function casts(): array
    {
        return ['unit_price_minor' => 'integer', 'amount_minor' => 'integer'];
    }

    public function amount(): Money
    {
        return Money::minor($this->amount_minor, $this->currency);
    }
}
