<?php

declare(strict_types=1);

namespace App\Domains\Api\Models;

use Illuminate\Database\Eloquent\Model;

class ApiTokenRateLimit extends Model
{
    protected $fillable = [
        'token_id',
        'requests_per_minute',
        'requests_per_hour',
        'requests_per_day',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
