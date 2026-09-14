<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Platform\Eloquent\Model;

/** Registered domain (blueprint §46). Public id `dom_…`; `fqdn_ascii` is the canonical key. */
final class Domain extends Model
{
    use SoftDeletes;

    protected static string $idPrefix = 'dom';

    protected $table = 'domains';

    protected $attributes = [
        'registrar_provider' => 'wedos', 'dns_provider' => 'powerdns', 'state' => DomainStateMachine::PENDING_REGISTRATION,
        'auto_renew' => true, 'renewal_period' => 1, 'auto_renew_priority' => 'domain', 'dnssec' => false, 'transfer_lock' => true, 'privacy_mode' => 'registry_default', 'critical' => false,
    ];

    protected function casts(): array
    {
        return [
            'auto_renew' => 'boolean', 'dnssec' => 'boolean', 'transfer_lock' => 'boolean', 'critical' => 'boolean', 'renewal_period' => 'integer',
            'nameservers' => 'array', 'registry_status' => 'array', 'meta' => 'array',
            'registered_at' => 'datetime', 'expires_at' => 'datetime', 'last_reconciled_at' => 'datetime',
        ];
    }

    public function registrant(): BelongsTo
    {
        return $this->belongsTo(RegistrarContact::class, 'registrant_contact_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(RegistrarContact::class, 'admin_contact_id');
    }

    public function nsset(): BelongsTo
    {
        return $this->belongsTo(RegistrarObject::class, 'nsset_id');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(DomainConsent::class, 'domain_id');
    }

    public function renewalJobs(): HasMany
    {
        return $this->hasMany(DomainRenewalJob::class, 'domain_id');
    }

    public function registrarOperations(): HasMany
    {
        return $this->hasMany(RegistrarOperation::class, 'domain_id');
    }

    public function isActive(): bool
    {
        return $this->state === DomainStateMachine::ACTIVE;
    }

    public function daysToExpiry(): ?int
    {
        return $this->expires_at === null ? null : (int) now()->startOfDay()->diffInDays($this->expires_at->startOfDay(), false);
    }

    /** Derived renewal bucket for the UI (blueprint §46.4: RENEW_DUE_60/30/7). */
    public function renewalBucket(): ?string
    {
        $days = $this->daysToExpiry();
        if ($days === null || $this->state !== DomainStateMachine::ACTIVE) {
            return null;
        }

        return match (true) {
            $days <= 7 => 'RENEW_DUE_7',
            $days <= 30 => 'RENEW_DUE_30',
            $days <= 60 => 'RENEW_DUE_60',
            default => null,
        };
    }

    public function usesOnhostDns(): bool
    {
        return $this->dns_provider === 'powerdns' && $this->dns_zone_id !== null;
    }
}
