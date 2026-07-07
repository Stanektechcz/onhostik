<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerMerge extends Model
{
    protected $fillable = [
        'primary_customer_id',
        'merged_customer_id',
        'status',
        'transferred_entities',
        'performed_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'transferred_entities' => 'array',
        ];
    }
}
