<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications\Models;

use Onhost\Platform\Eloquent\Model;

/** Customer webhook subscription; the signing secret is encrypted at rest and shown once at creation. */
final class WebhookEndpoint extends Model
{
    protected static string $idPrefix = 'whk';

    protected $table = 'webhook_endpoints';

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'events' => 'array', 'failures' => 'integer', 'last_delivered_at' => 'datetime'];
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
