<?php

declare(strict_types=1);

namespace Onhost\Domain\WalletLedger\Models;

use Onhost\Platform\Eloquent\Model;

final class WalletTopup extends Model
{
    protected static string $idPrefix = 'top';

    protected $table = 'wallet_topups';

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'refundable' => 'boolean'];
    }
}
