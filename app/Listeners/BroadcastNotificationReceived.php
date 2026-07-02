<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserNotificationReceived;
use App\Models\User;
use Illuminate\Notifications\Events\NotificationSent;

class BroadcastNotificationReceived
{
    public function handle(NotificationSent $event): void
    {
        if (! $event->notifiable instanceof User) {
            return;
        }

        if ($event->channel !== 'database') {
            return;
        }

        $unreadCount = $event->notifiable->unreadNotifications()->count();

        UserNotificationReceived::dispatch($event->notifiable->id, $unreadCount);
    }
}
