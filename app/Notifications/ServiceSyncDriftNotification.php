<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domains\Provisioning\Models\Service;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Notifications\Notification;

/**
 * Warns admins that services drifted from their backend panel — most
 * importantly a service the customer paid for that does not actually exist
 * in aaPanel. In-app only; the drift sweep runs hourly and would otherwise
 * flood mailboxes.
 */
class ServiceSyncDriftNotification extends Notification
{
    use RespectsNotificationPreferences;

    /** @param list<Service>|\Illuminate\Support\Collection<int, Service> $services */
    public function __construct(private readonly iterable $services) {}

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        return $this->channelsFor($notifiable, 'service', ['database']);
    }

    /** @return array<string, mixed> */
    public function toArray(mixed $notifiable): array
    {
        /** @var list<Service> $list */
        $list  = is_array($this->services) ? $this->services : iterator_to_array($this->services);
        $first = $list[0] ?? null;
        $count = count($list);

        $body = $first !== null
            ? sprintf(
                '%s: %s',
                (string) $first->label,
                (string) ($first->sync_message ?? 'nesoulad s panelem'),
            )
            : 'Zjištěn nesoulad služeb s panelem.';

        if ($count > 1) {
            $body .= sprintf(' (+ dalších %d)', $count - 1);
        }

        return [
            'type'         => 'service_sync_drift',
            'icon'         => 'alert-triangle',
            'color'        => 'danger',
            'title'        => $count === 1 ? 'Služba nesouhlasí s panelem' : "Nesoulad u {$count} služeb",
            'body'         => $body,
            'url'          => $first !== null ? route('admin.services.show', $first) : route('admin.services.index'),
            'service_ids'  => array_map(static fn (Service $s): int => $s->id, $list),
        ];
    }
}
