<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WinbackCampaign extends Model
{
    protected $fillable = ['name', 'target_segment', 'message', 'created_by', 'sent_at', 'sent_count'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
