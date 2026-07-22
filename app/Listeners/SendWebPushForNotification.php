<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domains\Communication\Services\WebPushService;
use App\Models\User;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Mirrors every in-app (database) notification to the browser as a web push
 * (audit 92).
 *
 * We hook the `database` channel specifically so each notification pushes once,
 * regardless of how many other channels it also uses. The send is entirely
 * inert until an operator configures VAPID keys — WebPushService::send() returns
 * early — so this listener is a safe no-op by default and never throws into the
 * notification pipeline.
 */
final class SendWebPushForNotification
{
    public function __construct(private readonly WebPushService $webPush) {}

    public function handle(NotificationSent $event): void
    {
        if (! $event->notifiable instanceof User) {
            return;
        }

        // Fire once per notification, not once per channel.
        if ($event->channel !== 'database') {
            return;
        }

        if (! $this->webPush->enabled()) {
            return;
        }

        $data = $this->payload($event);

        $title = (string) ($data['title'] ?? config('app.name', 'OnHost'));
        $body  = (string) ($data['body'] ?? $data['message'] ?? '');
        $url   = isset($data['url']) ? (string) $data['url'] : null;

        $this->webPush->send($event->notifiable, $title, $body, $url);
    }

    /**
     * The database channel stores the notification's array data; pull it back
     * out so the push carries the same title/body/url the bell icon shows.
     *
     * @return array<string, mixed>
     */
    private function payload(NotificationSent $event): array
    {
        $notification = $event->notification;

        if (method_exists($notification, 'toDatabase')) {
            /** @var array<string, mixed> $data */
            $data = (array) $notification->toDatabase($event->notifiable);

            return $data;
        }

        if (method_exists($notification, 'toArray')) {
            /** @var array<string, mixed> $data */
            $data = (array) $notification->toArray($event->notifiable);

            return $data;
        }

        return [];
    }
}
