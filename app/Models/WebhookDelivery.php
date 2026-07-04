<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $status
 * @property array<string, mixed> $payload
 * @property Carbon|null $delivered_at
 */
class WebhookDelivery extends Model
{
    protected $fillable = [
        'outgoing_webhook_id',
        'event',
        'payload',
        'status',
        'response_code',
        'response_body',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'      => 'array',
            'delivered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<OutgoingWebhook, $this> */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(OutgoingWebhook::class, 'outgoing_webhook_id');
    }
}
