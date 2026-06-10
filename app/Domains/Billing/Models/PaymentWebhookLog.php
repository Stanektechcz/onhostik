<?php

declare(strict_types=1);

namespace App\Domains\Billing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Raw inbound webhook log (sanitized).
 * Used for idempotency checks and manual replay from admin.
 */
class PaymentWebhookLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'provider',          // comgate | gopay
        'event_id',
        'payload',           // sanitized
        'headers',           // sanitized
        'ip_address',
        'signature_valid',
        'processed',
        'processed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'payload'         => 'array',
            'headers'         => 'array',
            'signature_valid' => 'boolean',
            'processed'       => 'boolean',
            'processed_at'    => 'datetime',
        ];
    }
}
