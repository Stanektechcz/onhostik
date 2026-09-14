<?php

declare(strict_types=1);

namespace Onhost\Domain\WalletLedger\Models;

use Onhost\Platform\Eloquent\Model;
use Onhost\Platform\Money\Money;

/**
 * Cached balances; the ledger is canonical (§62.3). `available = posted - reserved`
 * (+ approved credit line for postpaid organizations).
 */
final class Wallet extends Model
{
    protected static string $idPrefix = 'wal';

    protected $table = 'wallets';

    protected function casts(): array
    {
        return [
            'posted_balance_minor' => 'integer',
            'reserved_balance_minor' => 'integer',
            'accrued_unbilled_minor' => 'integer',
            'low_balance_threshold_minor' => 'integer',
            'low_balance_notified_at' => 'datetime',
        ];
    }

    public function posted(): Money
    {
        return Money::minor($this->posted_balance_minor, $this->currency);
    }

    public function reserved(): Money
    {
        return Money::minor($this->reserved_balance_minor, $this->currency);
    }

    public function available(): Money
    {
        return Money::minor($this->posted_balance_minor - $this->reserved_balance_minor, $this->currency);
    }

    public function isFrozen(): bool
    {
        return $this->state !== 'active';
    }
}
