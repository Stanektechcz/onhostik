<?php

declare(strict_types=1);

namespace App\Domains\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskWebhookDelivery extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'helpdesk_webhook_id',
        'event',
        'payload',
        'status_code',
        'response_body',
        'success',
        'duration_ms',
        'fired_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'   => 'array',
            'success'   => 'boolean',
            'fired_at'  => 'datetime',
        ];
    }

    /** @return BelongsTo<HelpdeskWebhook, $this> */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(HelpdeskWebhook::class, 'helpdesk_webhook_id');
    }
}
