<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailDripStep extends Model
{
    protected $fillable = [
        'drip_sequence_id',
        'sort_order',
        'delay_days',
        'subject',
        'body_html',
        'body_text',
    ];

    protected function casts(): array
    {
        return [
            'sort_order'  => 'integer',
            'delay_days'  => 'integer',
        ];
    }

    /** @return BelongsTo<EmailDripSequence, $this> */
    public function sequence(): BelongsTo
    {
        return $this->belongsTo(EmailDripSequence::class, 'drip_sequence_id');
    }
}
