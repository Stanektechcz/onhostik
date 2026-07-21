<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Communication\Support\NotificationCatalog;
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
 *
 * Per-channel opt-OUT lists, plus an 'opt_in' key holding per-channel opt-IN
 * lists (audit I132). 'opt_in' cannot collide with a channel name, which is
 * what allows both shapes to share one column without migrating old rows.
 *
 * @property array<string, mixed>|null $notification_preferences
 * @property \Illuminate\Support\Carbon|null   $last_login_at
 * @property \Illuminate\Support\Carbon|null   $two_factor_confirmed_at
 * @property \Illuminate\Support\Carbon|null   $deletion_requested_at
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
        'notification_preferences',
        'last_login_at',
        'last_login_ip',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'deletion_requested_at',
        'remember_token',
        'email_suppressed_at',
        'digest_frequency',
        'dark_mode',
        'changelog_seen_at',
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
            'email_verified_at'          => 'datetime',
            'password'                   => 'hashed',
            'is_active'                  => 'boolean',
            'notification_preferences'   => 'array',
            'last_login_at'              => 'datetime',
            'two_factor_confirmed_at'    => 'datetime',
            'email_suppressed_at'        => 'datetime',
            'changelog_seen_at'          => 'datetime',
            'dark_mode'                  => 'boolean',
        ];
    }

    /**
     * Whether this user should receive a given notification type on a channel.
     *
     * Two rules beyond the stored opt-out map (audit I132):
     *
     *  - Mandatory types always send. "Someone signed in from a new IP" and
     *    "your service was suspended" are not preferences; suppressing them
     *    turns a switch into a security hole.
     *  - An unknown key sends. The alternative — treating anything not in the
     *    catalogue as opted out — means a typo in a new notification silences
     *    it in production with no error anywhere. Failing open is noisy; the
     *    catalogue coverage test is what actually catches the typo.
     *
     * @param 'mail'|'database' $channel
     */
    public function wantsNotification(string $key, string $channel = 'mail'): bool
    {
        if (! NotificationCatalog::exists($key)) {
            return true;
        }

        $prefs = $this->notification_preferences ?? [];

        /*
         | Opt-in channels invert the default. Stored under a separate 'opt_in'
         | key rather than reusing the per-channel lists, because those lists
         | mean "opted OUT" and existing rows already hold that shape — 'opt_in'
         | cannot collide with a channel name.
         */
        if (NotificationCatalog::isOptIn($key, $channel)) {
            $optIn = $prefs['opt_in'] ?? [];
            $optIn = is_array($optIn) ? ($optIn[$channel] ?? []) : [];

            return in_array($key, (array) $optIn, true);
        }

        if (NotificationCatalog::isMandatory($key)) {
            return true;
        }

        $optOut = $prefs[$channel] ?? [];

        return ! in_array($key, (array) $optOut, true);
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
