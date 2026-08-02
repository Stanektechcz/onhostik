<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One rollup row per (date, currency): gross/net paid revenue and invoice count.
 *
 * `date` is kept as a plain 'Y-m-d' string (not a datetime cast) so it matches
 * the `DATE(paid_at)` grouping key exactly across MySQL/SQLite.
 *
 * @property string $date
 * @property string $currency
 * @property int $gross_minor
 * @property int $net_minor
 * @property int $invoices_count
 */
class RevenueDaily extends Model
{
    protected $table = 'revenue_daily';

    protected $fillable = [
        'date',
        'currency',
        'gross_minor',
        'net_minor',
        'invoices_count',
    ];

    protected function casts(): array
    {
        return [
            'gross_minor'    => 'integer',
            'net_minor'      => 'integer',
            'invoices_count' => 'integer',
        ];
    }
}
