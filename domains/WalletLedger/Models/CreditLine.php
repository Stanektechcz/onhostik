<?php

declare(strict_types=1);

namespace Onhost\Domain\WalletLedger\Models;

use Onhost\Platform\Eloquent\Model;

final class CreditLine extends Model
{
    protected static string $idPrefix = 'cl';

    protected $table = 'credit_lines';

    protected function casts(): array
    {
        return ['limit_minor' => 'integer', 'risk_hold_minor' => 'integer', 'approved_at' => 'datetime', 'review_at' => 'datetime'];
    }

    public function isApproved(): bool
    {
        return $this->state === 'approved';
    }
}
