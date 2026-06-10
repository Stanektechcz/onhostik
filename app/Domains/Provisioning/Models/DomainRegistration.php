<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class DomainRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'domain',
        'tld',
        'registrar',        // wedos
        'registered_at',
        'expires_at',
        'auto_renew',
        'nameservers',      // JSON array
        'auth_code',        // encrypted
        'wedos_domain_id',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'expires_at'    => 'datetime',
            'auto_renew'    => 'boolean',
            'nameservers'   => 'array',
        ];
    }

    protected $hidden = ['auth_code'];

    protected function authCode(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value ? Crypt::decryptString($value) : null,
            set: fn (?string $value): ?string => $value ? Crypt::encryptString($value) : null,
        );
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function fqdn(): string
    {
        return "{$this->domain}.{$this->tld}";
    }
}
