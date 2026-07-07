<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerSegmentTag extends Model
{
    protected $fillable = [
        'name',
        'color',
        'description',
    ];

    protected function casts(): array
    {
        return [];
    }
}
