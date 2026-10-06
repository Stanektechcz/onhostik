<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * @property Carbon|null $confirmed_at G6: when finance confirmed the bank payout of a pending refund
 */
final class PaymentRefund extends Model
{
    protected static string $idPrefix = 'prf';

    protected $table = 'payment_refunds';

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'confirmed_at' => 'datetime'];
    }
}
