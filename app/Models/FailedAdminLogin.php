<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedAdminLogin extends Model
{
    public $timestamps = false;

    protected $fillable = ['email', 'ip_address', 'user_agent', 'attempted_at'];

    protected function casts(): array
    {
        return ['attempted_at' => 'datetime'];
    }
}
