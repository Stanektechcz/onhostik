<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

/**
 * Audit I132: one place that turns a notification type into a channel list.
 *
 * Every notification was repeating the same four lines, which is why some of
 * them quietly did not repeat them at all — 35 notifications ignored user
 * preferences entirely before this existed.
 *
 * Usage:
 *
 *     public function via(mixed $notifiable): array
 *     {
 *         return $this->channelsFor($notifiable, 'renewal');
 *     }
 */
trait RespectsNotificationPreferences
{
    /**
     * @param  list<string>  $channels  Channels this notification can use at all.
     * @return list<string>
     */
    protected function channelsFor(mixed $notifiable, string $type, array $channels = ['mail', 'database']): array
    {
        // Admin alerts and system notifiables have no preference map; sending
        // is the correct default rather than silently dropping the alert.
        if (! method_exists($notifiable, 'wantsNotification')) {
            return $channels;
        }

        $allowed = [];

        foreach ($channels as $channel) {
            if ($notifiable->wantsNotification($type, $channel)) {
                $allowed[] = $channel;
            }
        }

        return $allowed;
    }
}
