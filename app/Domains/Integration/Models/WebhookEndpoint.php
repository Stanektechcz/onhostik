<?php

declare(strict_types=1);

namespace App\Domains\Integration\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $secret
 * @property array<int, string>|null $allowed_events
 */
class WebhookEndpoint extends Model
{
    protected $fillable = [
        'name',
        'source',
        'secret',
        'signature_algo',
        'signature_header',
        'is_active',
        'allowed_events',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_active'      => 'boolean',
            'allowed_events' => 'array',
        ];
    }
}
