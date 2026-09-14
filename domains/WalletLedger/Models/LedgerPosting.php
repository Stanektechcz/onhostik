<?php

declare(strict_types=1);

namespace Onhost\Domain\WalletLedger\Models;

use Onhost\Platform\Eloquent\Model;

final class LedgerPosting extends Model
{
    protected static string $idPrefix = 'lpo';

    protected $table = 'ledger_postings';

    public const UPDATED_AT = null;

    public const DEBIT = 'debit';

    public const CREDIT = 'credit';
}
