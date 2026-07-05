<?php

declare(strict_types=1);

namespace App\Domains\Integration\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property 'received'|'processed'|'ignored'|'failed' $status
 * @property string $source
 * @property array<string, mixed> $payload
 * @property array<string, string>|null $headers
 */
class InboundWebhookLog extends Model
{
    protected $fillable = [
        'source',
        'event_type',
        'status',
        'headers',
        'payload',
        'signature_header',
        'signature_valid',
        'error_message',
        'idempotency_key',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'headers'         => 'array',
            'payload'         => 'array',
            'signature_valid' => 'boolean',
        ];
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'received'  => 'Přijato',
            'processed' => 'Zpracováno',
            'ignored'   => 'Ignorováno',
            'failed'    => 'Chyba',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'received'  => 'bg-secondary',
            'processed' => 'bg-success',
            'ignored'   => 'bg-warning',
            'failed'    => 'bg-danger',
        };
    }
}
