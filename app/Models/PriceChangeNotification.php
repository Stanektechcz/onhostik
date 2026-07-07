<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $sent_at
 * @property Carbon $effective_from
 */
class PriceChangeNotification extends Model
{
    protected $fillable = [
        'title',
        'body',
        'product_id',
        'effective_from',
        'status',
        'recipients_count',
        'sent_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'sent_at' => 'datetime',
        ];
    }
}
