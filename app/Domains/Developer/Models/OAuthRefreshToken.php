<?php

declare(strict_types=1);

namespace App\Domains\Developer\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A long-lived, revocable OAuth2 refresh token (audit: OAuth2 grant). Only the
 * SHA-256 hash of the token is stored; the raw value is returned once.
 *
 * @property int $id
 * @property int $oauth_application_id
 * @property int $user_id
 * @property string $token_hash
 * @property list<string> $scopes
 * @property int|null $access_token_id
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 */
class OAuthRefreshToken extends Model
{
    protected $table = 'oauth_refresh_tokens';

    protected $fillable = [
        'oauth_application_id',
        'user_id',
        'token_hash',
        'scopes',
        'access_token_id',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'scopes'     => 'array',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /** @return BelongsTo<OAuthApplication, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(OAuthApplication::class, 'oauth_application_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
