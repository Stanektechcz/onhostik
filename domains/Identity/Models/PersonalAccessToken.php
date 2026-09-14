<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

/**
 * Sanctum token with ONhost extensions: organization scope, per-token rate limit,
 * revocation, last-used IP. Secret shown once; prefix `onh_live_` (config/sanctum).
 */
final class PersonalAccessToken extends SanctumToken
{
    protected $table = 'personal_access_tokens';

    protected $guarded = [];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'revoked_at' => 'datetime',
            'rate_limit_per_minute' => 'integer',
        ]);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /** Sanctum resolves the token via this method; revoked tokens must not authenticate. */
    public static function findToken($token)
    {
        $instance = parent::findToken($token);
        if ($instance instanceof self && $instance->isRevoked()) {
            return null;
        }

        return $instance;
    }
}
