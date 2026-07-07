<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Provisioning\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $expires_at
 * @property Carbon|null $checked_at
 */
class SslCertificateCheck extends Model
{
    protected $fillable = [
        'service_id',
        'domain',
        'status',
        'expires_at',
        'issuer',
        'checked_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'checked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function isExpiringSoon(): bool
    {
        return $this->status === 'expiring_soon';
    }
}
