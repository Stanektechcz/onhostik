<?php

declare(strict_types=1);

namespace App\Domains\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Stores the CZK conversion rate for a foreign currency on a given day.
 *
 * @property string     $currency
 * @property float      $rate
 * @property string     $source    'manual' | 'cnb'
 * @property Carbon     $valid_from
 */
class ExchangeRate extends Model
{
    protected $fillable = [
        'currency',
        'rate',
        'source',
        'valid_from',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'float',
            // valid_from is stored and compared as a plain Y-m-d string to avoid
            // SQLite datetime ambiguity in tests (no DATE vs DATETIME native type).
        ];
    }
}
