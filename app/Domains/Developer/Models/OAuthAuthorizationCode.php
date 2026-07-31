<?php

declare(strict_types=1);

namespace App\Domains\Developer\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single-use, short-lived OAuth2 authorization code (audit: OAuth2 grant).
 *
 * @property int $id
 * @property int $oauth_application_id
 * @property int $user_id
 * @property string $code_hash
 * @property string $redirect_uri
 * @property list<string> $scopes
 * @property string|null $code_challenge
 * @property string|null $code_challenge_method
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 */
class OAuthAuthorizationCode extends Model
{
    protected $table = 'oauth_authorization_codes';

    protected $fillable = [
        'oauth_application_id',
        'user_id',
        'code_hash',
        'redirect_uri',
        'scopes',
        'code_challenge',
        'code_challenge_method',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'scopes'     => 'array',
            'expires_at' => 'datetime',
            'used_at'    => 'datetime',
        ];
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
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
