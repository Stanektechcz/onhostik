<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Customer\Models\Customer;
use App\Domains\Shared\Traits\HasUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * Authentication identity. Billing identity lives on the related
 * Customer model (1:1) — see App\Domains\Customer\Models\Customer.
 */
class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use TwoFactorAuthenticatable;

    use HasRoles;
    use HasUuid;
    use LogsActivity;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'locale',
        'last_login_at',
        'last_login_ip',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'      => 'datetime',
            'password'               => 'hashed',
            'is_active'              => 'boolean',
            'last_login_at'          => 'datetime',
            'two_factor_confirmed_at'=> 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('user');
    }

    // ---------------------------------------------------------------- relations

    /** @return HasOne<Customer, $this> */
    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    // ---------------------------------------------------------------- helpers

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }
}
