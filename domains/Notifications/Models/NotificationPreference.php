<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications\Models;

use Onhost\Platform\Eloquent\Model;

final class NotificationPreference extends Model
{
    protected static string $idPrefix = 'npr';

    protected $table = 'notification_preferences';

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
