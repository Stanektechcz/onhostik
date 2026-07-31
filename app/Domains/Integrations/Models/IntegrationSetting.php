<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Credential vault entry for one external provider.
 *
 * Security model:
 *  - credentials are encrypted at rest (Laravel Crypt, AES-256),
 *  - full secrets are NEVER returned to the UI after save — only the
 *    masked form via maskedCredentials(),
 *  - credential values never enter the activity log (only key names),
 *  - mock_mode/dry_run default to TRUE: a freshly created provider can
 *    never perform real calls until an admin explicitly flips the flags.
 *
 * @property array<string, string> $credentials
 * @property Carbon|null $last_success_at
 * @property Carbon|null $last_error_at
 */
class IntegrationSetting extends Model
{
    use LogsActivity;

    protected $fillable = [
        'provider',
        'label',
        'credentials',
        'is_active',
        'mock_mode',
        'dry_run',
        'last_success_at',
        'last_error_at',
        'last_error_message',
        'meta',
    ];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'is_active'       => 'boolean',
            'mock_mode'       => 'boolean',
            'dry_run'         => 'boolean',
            'last_success_at' => 'datetime',
            'last_error_at'   => 'datetime',
            'meta'            => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Secrets must never reach the audit log — flags + health only.
        return LogOptions::defaults()
            ->logOnly(['provider', 'is_active', 'mock_mode', 'dry_run'])
            ->logOnlyDirty()
            ->useLogName('integration');
    }

    // ---------------------------------------------------------------- encryption

    /** @return Attribute<array<string, string>, string> */
    protected function credentials(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): array {
                if (!is_string($value) || $value === '') {
                    return [];
                }

                try {
                    $decoded = json_decode(Crypt::decryptString($value), true);
                } catch (\Illuminate\Contracts\Encryption\DecryptException) {
                    // Legacy rows seeded with plain-text '{}' (or an APP_KEY
                    // rotation) — treat as "no credentials" instead of crashing.
                    // Never log the raw value.
                    \Illuminate\Support\Facades\Log::warning('integration_settings.credentials undecryptable', [
                        'provider' => $this->getAttribute('provider'),
                    ]);

                    return [];
                }

                return is_array($decoded) ? $decoded : [];
            },
            set: fn (mixed $value): string => Crypt::encryptString((string) json_encode(is_array($value) ? $value : [])),
        );
    }

    /**
     * Masked credentials for display: keys visible, values reduced to the
     * last 4 characters (or fully hidden when short).
     *
     * @return array<string, string>
     */
    public function maskedCredentials(): array
    {
        $masked = [];

        foreach ($this->credentials as $key => $value) {
            $masked[$key] = mb_strlen($value) > 8
                ? str_repeat('•', 8) . mb_substr($value, -4)
                : str_repeat('•', max(4, mb_strlen($value)));
        }

        return $masked;
    }

    // ---------------------------------------------------------------- helpers

    /**
     * The admin-managed credentials for a provider (decrypted), or an empty
     * array when the provider has no row / no credentials. One query; callers
     * pick the keys they need and fall back to config/env for any that are
     * blank — so moving a credential into the admin never breaks a .env deploy.
     *
     * @return array<string, string>
     */
    public static function credentialsFor(string $provider): array
    {
        $row = static::query()->where('provider', $provider)->first();

        return $row === null ? [] : $row->credentials;
    }

    public function healthStatus(): string
    {
        if (!$this->is_active) {
            return 'inactive';
        }

        if ($this->last_error_at !== null
            && ($this->last_success_at === null || $this->last_error_at->gt($this->last_success_at))) {
            return 'error';
        }

        return $this->last_success_at !== null ? 'healthy' : 'untested';
    }

    public function allowsRealCalls(): bool
    {
        return $this->is_active && !$this->mock_mode && !$this->dry_run;
    }
}
