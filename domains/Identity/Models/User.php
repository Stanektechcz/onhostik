<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Platform\Eloquent\HasPrefixedUlid;

/**
 * @property string $id
 * @property string $email
 * @property string $name
 * @property bool $is_staff
 * @property string $state
 */
final class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasPrefixedUlid;
    use Notifiable;
    use SoftDeletes;

    protected static string $idPrefix = 'usr';

    protected $guarded = [];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $hidden = ['password', 'remember_token', 'totp_secret', 'recovery_codes'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'totp_confirmed_at' => 'datetime',
            'mfa_required_from' => 'datetime',
            'password_changed_at' => 'datetime',
            'password' => 'hashed',
            'totp_secret' => 'encrypted',
            'recovery_codes' => 'encrypted:array',
            'preferences' => 'array',
            'is_staff' => 'boolean',
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class, 'user_id');
    }

    public function organizations()
    {
        return $this->belongsToMany(Organization::class, 'organization_memberships', 'user_id', 'organization_id')
            ->withPivot(['role_key', 'state'])
            ->wherePivot('state', 'active');
    }

    public function identities(): HasMany
    {
        return $this->hasMany(Identity::class, 'user_id');
    }

    public function stepUpGrants(): HasMany
    {
        return $this->hasMany(StepUpGrant::class, 'user_id');
    }

    public function webauthnCredentials(): HasMany
    {
        return $this->hasMany(WebAuthnCredential::class, 'user_id');
    }

    public function hasTotp(): bool
    {
        return $this->totp_secret !== null && $this->totp_confirmed_at !== null;
    }

    public function hasWebAuthn(): bool
    {
        return $this->webauthnCredentials()->exists();
    }

    public function hasMfa(): bool
    {
        return $this->hasTotp() || $this->hasWebAuthn();
    }

    public function isActive(): bool
    {
        return $this->state === 'active' && $this->deleted_at === null;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}
