<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One web-push subscription (browser/device) for a user (audit 92).
 *
 * @property string $endpoint
 * @property string $public_key
 * @property string $auth_token
 */
class PushSubscription extends Model
{
    protected $fillable = ['user_id', 'endpoint', 'endpoint_hash', 'public_key', 'auth_token'];

    protected static function booted(): void
    {
        // Keep the hash (the uniqueness key) in step with the endpoint.
        static::saving(function (self $sub): void {
            $sub->endpoint_hash = hash('sha256', (string) $sub->endpoint);
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
