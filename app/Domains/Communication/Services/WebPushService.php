<?php

declare(strict_types=1);

namespace App\Domains\Communication\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Sends browser web-push notifications (audit 92).
 *
 * Encryption (aes128gcm / ECDH) is done by minishlink/web-push — never
 * hand-rolled. Sending is skipped entirely when VAPID keys are not configured,
 * so the feature is inert until an operator sets them; the subscription flow
 * still works in the meantime.
 *
 * A push service that returns 404/410 means the subscription is gone (browser
 * uninstalled, permission revoked) — those rows are pruned so we stop trying.
 * The VAPID private key is a secret and is never logged.
 */
class WebPushService
{
    public function enabled(): bool
    {
        return (bool) config('webpush.enabled', false)
            && config('webpush.vapid.public_key') !== null
            && config('webpush.vapid.private_key') !== null;
    }

    /**
     * Push a notification to every device the user has subscribed.
     *
     * @return int number of subscriptions delivered to
     */
    public function send(User $user, string $title, string $body, ?string $url = null): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $subscriptions = PushSubscription::where('user_id', $user->id)->get();

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $webPush = new WebPush(['VAPID' => [
            'subject'    => (string) config('webpush.vapid.subject'),
            'publicKey'  => (string) config('webpush.vapid.public_key'),
            'privateKey' => (string) config('webpush.vapid.private_key'),
        ]]);

        $payload = json_encode(array_filter([
            'title' => $title,
            'body'  => $body,
            'url'   => $url,
        ]), JSON_THROW_ON_ERROR);

        foreach ($subscriptions as $sub) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'keys'     => ['p256dh' => $sub->public_key, 'auth' => $sub->auth_token],
                ]),
                $payload,
            );
        }

        $delivered = 0;

        try {
            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    $delivered++;

                    continue;
                }

                // 404/410 → the subscription is dead; stop trying it.
                if (in_array($report->getResponse()?->getStatusCode(), [404, 410], true)) {
                    PushSubscription::where('user_id', $user->id)
                        ->where('endpoint_hash', hash('sha256', (string) $report->getEndpoint()))
                        ->delete();
                }
            }
        } catch (Throwable) {
            // A transport failure must never break the calling flow (a push is
            // best-effort); the safe message is that nothing was delivered.
            return $delivered;
        }

        return $delivered;
    }
}
