<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains;

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\DomainRenewalJob;
use Onhost\Domain\Domains\Models\RegistrarNotification;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Throwable;

/**
 * WAPI poll-req/poll-ack consumer (blueprint §45.3). Every notification is stored
 * first (unique remote id), processed idempotently, and acked ONLY after the local
 * transaction committed — a crash between the two just re-delivers it.
 */
final class RegistrarPollWorker
{
    public function __construct(private readonly RegistrarClient $registrar, private readonly DomainService $domains, private readonly OutboxPublisher $outbox) {}

    /** @return array{received:int, processed:int, acked:int, dead:int} */
    public function drain(?int $max = null, ?CommandContext $context = null): array
    {
        $max ??= (int) config('onhost.domains.poll_batch', 50);
        $context ??= CommandContext::system('registrar poll worker');
        $stats = ['received' => 0, 'processed' => 0, 'acked' => 0, 'dead' => 0];
        foreach ($this->registrar->instances() as $instance) {
            $one = $this->drainInstance($instance, $max, $context);
            foreach ($stats as $k => $v) {
                $stats[$k] = $v + $one[$k];
            }
        }

        return $stats;
    }

    /** @return array{received:int, processed:int, acked:int, dead:int} */
    public function drainInstance(ProviderInstance $instance, int $max, CommandContext $context): array
    {
        $adapter = $this->registrar->adapterFor($instance);
        $provider = $instance->provider;
        $stats = ['received' => 0, 'processed' => 0, 'acked' => 0, 'dead' => 0];
        for ($i = 0; $i < $max; $i++) {
            $event = $adapter->pollRequest();
            if ($event === null) {
                break;
            }
            $notification = RegistrarNotification::query()->firstOrCreate(['remote_id' => $provider.':'.$event['id']], ['registrar_provider' => $provider, 'kind' => $event['kind'], 'fqdn' => $event['fqdn'], 'payload' => $event['payload'], 'state' => 'received', 'received_at' => now()]);
            $stats['received']++;
            if ($notification->state === 'acked') {
                $adapter->pollAck($event['id']); // ack was lost previously; the notification was already processed
                $stats['acked']++;

                continue;
            }
            try {
                DB::transaction(fn () => $this->process($notification, $context));
                $notification->forceFill(['state' => 'processed', 'processed_at' => now()])->save();
                $stats['processed']++;
            } catch (Throwable $e) {
                $attempts = $notification->attempts + 1;
                $dead = $attempts >= (int) config('onhost.domains.poll_max_attempts', 5);
                $notification->forceFill(['attempts' => $attempts, 'last_error' => mb_substr($e->getMessage(), 0, 250), 'state' => $dead ? 'dead' : 'received'])->save();
                if ($dead) {
                    $stats['dead']++;
                    $this->outbox->publish(GenericEvent::of('registrar.notification.dead', 'registrar_notification', $notification->id, ['remote_id' => $notification->remote_id, 'kind' => $notification->kind, 'fqdn' => $notification->fqdn, 'error' => $e->getMessage()]));
                    $adapter->pollAck($event['id']); // stop the queue from blocking on a poison message; operators get the alert
                    $notification->forceFill(['acked_at' => now()])->save();
                    $stats['acked']++;
                }
                break; // WAPI delivers the same head-of-queue event until acked; retry on the next run
            }
            try {
                $adapter->pollAck($event['id']);
                $notification->forceFill(['state' => 'acked', 'acked_at' => now()])->save();
                $stats['acked']++;
            } catch (ProviderException $e) {
                $notification->forceFill(['last_error' => 'ack failed: '.mb_substr($e->getMessage(), 0, 200)])->save();
                break;
            }
        }

        return $stats;
    }

    private function process(RegistrarNotification $notification, CommandContext $context): void
    {
        $fqdn = $notification->fqdn ? strtolower($notification->fqdn) : null;
        $domain = $fqdn ? Domain::query()->where('fqdn_ascii', $fqdn)->first() : null;
        $kind = strtolower((string) $notification->kind);
        $payload = (array) $notification->payload;

        if ($domain !== null) {
            // Registry truth wins: refresh from domain-info for every domain-related event.
            $this->domains->resolveUnknown($domain);
            $domain->refresh();
            if (str_contains($kind, 'transfer') && (str_contains($kind, 'out') || str_contains($kind, 'away') || ($payload['result'] ?? null) === 'transferred_out')) {
                $domain->forceFill(['state' => DomainStateMachine::TRANSFERRED_OUT])->save();
                DomainRenewalJob::query()->where('domain_id', $domain->id)->where('state', DomainRenewalJob::SCHEDULED)->update(['state' => DomainRenewalJob::SKIPPED, 'last_error' => 'transferred out']);
                $this->outbox->publish(GenericEvent::of('domain.transferred_out', 'domain', $domain->id, ['fqdn' => $domain->fqdn_ascii, 'notification' => $notification->remote_id], $domain->organization_id));
            } elseif (str_contains($kind, 'delete') || str_contains($kind, 'expire')) {
                $this->outbox->publish(GenericEvent::of('domain.registry_notice', 'domain', $domain->id, ['fqdn' => $domain->fqdn_ascii, 'kind' => $kind, 'state' => $domain->state], $domain->organization_id));
            } else {
                $this->outbox->publish(GenericEvent::of('domain.registry_notice', 'domain', $domain->id, ['fqdn' => $domain->fqdn_ascii, 'kind' => $kind, 'state' => $domain->state], $domain->organization_id));
            }

            return;
        }
        $this->outbox->publish(GenericEvent::of('registrar.notification', 'registrar_notification', $notification->id, ['kind' => $kind, 'fqdn' => $fqdn, 'payload' => $payload]));
    }
}
