<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Models;

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

/**
 * Backend server / panel node (AAPanel node, Proxmox node, Pterodactyl panel).
 *
 * API credentials are encrypted at rest (Laravel Crypt, AES-256).
 * Plaintext secrets are NEVER stored, logged or returned via API.
 */
class Server extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'name',
        'driver',
        'api_url',
        'api_credentials',  // encrypted JSON: {key: ..., token_id: ..., ...}
        'status',           // active | maintenance | offline
        'max_services',
        'current_services',
        'capacity_meta',    // disk totals, node info...
        'is_default',
        'mock_mode',
        'last_health_check_at',
        'last_health_ok',
    ];

    protected function casts(): array
    {
        return [
            'driver'               => ProvisioningDriver::class,
            'capacity_meta'        => 'array',
            'is_default'           => 'boolean',
            'mock_mode'            => 'boolean',
            'last_health_check_at' => 'datetime',
            'last_health_ok'       => 'boolean',
        ];
    }

    protected $hidden = ['api_credentials'];

    // ---------------------------------------------------------------- encryption

    protected function apiCredentials(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): array => $value ? json_decode(Crypt::decryptString($value), true) : [],
            set: fn (array $value): string => Crypt::encryptString(json_encode($value)),
        );
    }

    // ---------------------------------------------------------------- relations

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    // ---------------------------------------------------------------- helpers

    public function hasCapacity(): bool
    {
        return $this->max_services === null
            || $this->current_services < $this->max_services;
    }

    public function scopeActive($query): mixed
    {
        return $query->where('status', 'active');
    }
}
