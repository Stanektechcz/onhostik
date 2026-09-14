<?php

declare(strict_types=1);

namespace Onhost\Domain\WalletLedger\Models;

use Onhost\Platform\Eloquent\Model;
use Onhost\Platform\Money\Money;

final class WalletHold extends Model
{
    protected static string $idPrefix = 'hold';

    protected $table = 'wallet_holds';

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'expires_at' => 'datetime'];
    }

    public function amount(): Money
    {
        return Money::minor($this->amount_minor, $this->currency);
    }

    public function isActive(): bool
    {
        return $this->state === 'active';
    }
}
