<?php

declare(strict_types=1);

namespace Onhost\Domain\WalletLedger\Models;

use Onhost\Platform\Eloquent\Model;

final class WalletRefund extends Model
{
    protected static string $idPrefix = 'wrf';

    protected $table = 'wallet_refunds';

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }
}
