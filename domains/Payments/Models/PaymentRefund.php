<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments\Models;

use Onhost\Platform\Eloquent\Model;

final class PaymentRefund extends Model
{
    protected static string $idPrefix = 'prf';

    protected $table = 'payment_refunds';

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }
}
