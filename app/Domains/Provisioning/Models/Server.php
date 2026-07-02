<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Models;

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

/**
 * Backend server / panel node (AAPanel node, Proxmox node, Pterodactyl panel).
 *
 * API credentials are encrypted at rest (Laravel Crypt, AES-256).
 * Plaintext secrets are NEVER stored, logged or returned via API.
 *
 * @property-read array<string,string> $api_credentials  Decrypted credential map (via Attribute accessor)
 */
class Server extends Model
{
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

    /** @return Attribute<array<string, string>, array<string, string>> */
    protected function apiCredentials(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): array => $value ? json_decode(Crypt::decryptString($value), true) : [],
            set: fn (array $value): string => Crypt::encryptString(json_encode($value)),
        );
    }

    // ---------------------------------------------------------------- relations

    /** @return HasMany<Service, $this> */
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

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Server>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Server>
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'active');
    }
}
