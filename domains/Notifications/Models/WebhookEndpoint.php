<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * Customer webhook subscription; the signing secret is encrypted at rest and shown once at creation or rotation.
 * States: active · suspended (too many failed attempts in a row; `enable` brings it back) · disabled (removed by the
 * customer; final). `paused` is how a suspension was written before D4 and is read as one.
 *
 * @property ?Carbon $last_delivered_at
 * @property ?string $previous_secret the secret the last rotation replaced (encrypted at rest; G7)
 * @property ?Carbon $previous_secret_expires_at until when it still signs
 * @property ?Carbon $created_at
 */
final class WebhookEndpoint extends Model
{
    public const ACTIVE = 'active';

    public const SUSPENDED = 'suspended';

    public const DISABLED = 'disabled';

    private const LEGACY_SUSPENDED = 'paused';

    protected static string $idPrefix = 'whk';

    protected $table = 'webhook_endpoints';

    protected $hidden = ['secret', 'previous_secret'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'previous_secret' => 'encrypted', 'previous_secret_expires_at' => 'datetime', 'events' => 'array', 'failures' => 'integer', 'last_delivered_at' => 'datetime'];
    }

    /** G7: the secret a rotation replaced, while its overlap window lasts (it still signs X-ONhost-Signature-Previous); null after. */
    public function previousSecret(): ?string
    {
        $secret = $this->previous_secret;
        $until = $this->previous_secret_expires_at;

        return is_string($secret) && $secret !== '' && $until instanceof Carbon && $until->isFuture() ? $secret : null;
    }

    public function isSuspended(): bool
    {
        return in_array($this->state, [self::SUSPENDED, self::LEGACY_SUSPENDED], true);
    }

    public function subscribedTo(string $event): bool
    {
        foreach ((array) $this->events as $pattern) {
            if ($pattern === '*' || $pattern === $event || (str_ends_with((string) $pattern, '.*') && str_starts_with($event, substr((string) $pattern, 0, -1)))) {
                return true;
            }
        }

        return false;
    }
}
