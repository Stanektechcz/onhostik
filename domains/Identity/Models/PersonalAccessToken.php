<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Models;

use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken as SanctumToken;
use Onhost\Domain\Identity\Authorization\TokenScopes;

/**
 * Sanctum token with ONhost extensions: organization scope, per-token rate limit,
 * revocation, last-used IP. Secret shown once; prefix `onh_live_` (config/sanctum).
 *
 * @property Carbon|null $revoked_at
 * @property string|null $organization_id
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

    /**
     * TASK-0079 (D6): a scope is held directly or through a wider scope that carries it (TokenScopes::IMPLIED_BY — `dns:write`
     * reads zones). One place, so TokenRouteScope, ApiContext and the OpenAPI generator decide alike.
     *
     * @param  string  $ability
     */
    public function can($ability)
    {
        if (parent::can($ability)) {
            return true;
        }
        foreach (TokenScopes::impliedBy((string) $ability) as $wider) {
            if (parent::can($wider)) {
                return true;
            }
        }

        return false;
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
