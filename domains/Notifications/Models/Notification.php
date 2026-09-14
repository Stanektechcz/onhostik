<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications\Models;

use Onhost\Platform\Eloquent\Model;

/** In-app notification for the customer panel/mobile (`audience=customer`) or the admin shell (`internal`). */
final class Notification extends Model
{
    protected static string $idPrefix = 'ntf';

    protected $table = 'notifications';

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
