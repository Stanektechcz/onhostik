<?php

declare(strict_types=1);

namespace Onhost\Domain\WalletLedger\Models;

use Onhost\Platform\Eloquent\Model;

final class LedgerAccount extends Model
{
    protected static string $idPrefix = 'lac';

    protected $table = 'ledger_accounts';

    public const TYPE_ASSET = 'asset';

    public const TYPE_LIABILITY = 'liability';

    public const TYPE_REVENUE = 'revenue';

    public const TYPE_EXPENSE = 'expense';

    public const TYPE_EQUITY = 'equity';

    /** Debit increases asset/expense; credit increases liability/revenue/equity. */
    public function debitIncreases(): bool
    {
        return in_array($this->type, [self::TYPE_ASSET, self::TYPE_EXPENSE], true);
    }
}
