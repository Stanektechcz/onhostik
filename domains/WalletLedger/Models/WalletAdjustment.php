<?php

declare(strict_types=1);

namespace Onhost\Domain\WalletLedger\Models;

use Onhost\Platform\Eloquent\Model;

final class WalletAdjustment extends Model
{
    protected static string $idPrefix = 'wadj';

    protected $table = 'wallet_adjustments';

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }
}
