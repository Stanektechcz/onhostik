<?php

declare(strict_types=1);

namespace Onhost\Domain\WalletLedger\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Onhost\Platform\Eloquent\Model;

/** Immutable. Corrections are new reversing transactions (blueprint §62.2). */
final class LedgerTransaction extends Model
{
    protected static string $idPrefix = 'ltx';

    protected $table = 'ledger_transactions';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['meta' => 'array', 'posted_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function postings(): HasMany
    {
        return $this->hasMany(LedgerPosting::class, 'transaction_id');
    }
}
