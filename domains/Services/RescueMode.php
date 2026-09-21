<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\ComputeProvider;
use Onhost\Providers\Contracts\ResourceRef;
use Throwable;

/**
 * Booting a server into a rescue system (Brain card H233, audit §5af).
 *
 * A server whose own system will not start is unreachable for its owner: there was no way to boot anything else, so
 * every broken VPS was a support ticket and a manual change in the hypervisor. The rescue image is a normal part of
 * the panel now, under the rules the card asks for:
 *
 *  • **only what the operator put there** — the image comes from the node's ISO storage, never from a path the
 *    customer names, so nothing can be booted that the operator has not offered;
 *  • **a window, not a state** — a session ends by itself after `onhost.rescue.hours` (8 by default);
 *    `onhost:services:rescue-expire` runs every ten minutes and puts the server back;
 *  • **exactly what was found goes back** — the boot order and the drive as they were before the session are written
 *    down (`tags.rescue.previous`) and restored on the way out, never a guess at a default;
 *  • booting a rescue system means root on the server's disks, so starting one asks for a fresh step-up and the
 *    assistant never proposes it; ending one does not, because getting out must always be possible.
 */
final class RescueMode
{
    public const TAG = 'rescue';

    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    public static function hours(): int
    {
        return max(1, min(72, (int) config('onhost.rescue.hours', 8)));
    }

    /** The session a service is in, or null. @return array<string,mixed>|null */
    public static function session(Service $service): ?array
    {
        $tag = (array) data_get($service->tags, self::TAG, []);

        return ($tag['until'] ?? null) === null ? null : $tag;
    }

    /** The images this service could boot. @return list<array{volume:string,name:string,size_bytes:int}> */
    public function images(Service $service): array
    {
        [$adapter, $ref] = $this->adapter($service);
        try {
            return $adapter->listIsoImages((string) ($ref->node ?? ''));
        } catch (Throwable) {
            return []; // a node that offers no ISO storage simply offers no rescue
        }
    }

    /**
     * Boots the service from a rescue image. Returns what was recorded, for the operation result.
     *
     * @return array{iso:string, until:string, previous:array{iso:string|null,boot:string}}
     */
    public function start(Service $service, CommandContext $actor, ?string $volume, ?int $hours = null): array
    {
        [$adapter, $ref] = $this->adapter($service);
        $images = $adapter->listIsoImages((string) ($ref->node ?? ''));
        if ($images === []) {
            throw new DomainError('rescue_no_image', 'Tento uzel nenabízí žádný záchranný obraz.', 409);
        }
        $volume = $volume === null || $volume === '' ? (string) $images[0]['volume'] : $volume;
        if (! in_array($volume, array_map(fn (array $i) => (string) $i['volume'], $images), true)) {
            // the customer picks from the list; a path of their own is not an image (H233)
            throw new DomainError('rescue_image_unknown', 'Takový záchranný obraz uzel nenabízí.', 422, ['field' => 'image']);
        }
        $previous = $adapter->bootMedia($ref);
        $until = now()->addHours($hours === null ? self::hours() : max(1, min(72, $hours)));
        $adapter->setBootMedia($ref, $volume, self::bootFirst($previous['boot']));
        $adapter->power($ref, 'reboot'); // the change reaches the guest only at its next start
        $session = ['iso' => $volume, 'started_at' => now()->toIso8601String(), 'until' => $until->toIso8601String(), 'previous' => $previous, 'by' => $actor->actorId];
        $this->remember($service, $session);
        $this->audit->record($actor->withScope($service->organization_id), 'service.rescue.start', 'succeeded', ['image' => $volume, 'until' => $until->toIso8601String(), 'previous_boot' => $previous['boot']], 'service', $service->id);
        $this->outbox->publish(GenericEvent::of('service.rescue.started', 'service', $service->id, ['image' => basename($volume), 'until' => $until->toIso8601String(), 'hours' => (int) now()->diffInHours($until)], $service->organization_id));

        return ['iso' => $volume, 'until' => $until->toIso8601String(), 'previous' => $previous];
    }

    /**
     * Puts the server back the way it was found. Safe to call when no session is open.
     *
     * @return array{restored:bool, boot:string|null}
     */
    public function stop(Service $service, CommandContext $actor, string $reason = 'rescue ended'): array
    {
        $session = self::session($service);
        if ($session === null) {
            return ['restored' => false, 'boot' => null];
        }
        [$adapter, $ref] = $this->adapter($service);
        $previous = (array) ($session['previous'] ?? []);
        // exactly what was there before, never a guess at a default: the drive too, in case the server booted from one
        $adapter->setBootMedia($ref, ($previous['iso'] ?? null) === null ? null : (string) $previous['iso'], (string) ($previous['boot'] ?? ''));
        $adapter->power($ref, 'reboot'); // the change reaches the guest only at its next start
        $this->remember($service, null);
        $this->audit->record($actor->withScope($service->organization_id), 'service.rescue.stop', 'succeeded', ['reason' => $reason, 'boot' => $previous['boot'] ?? null], 'service', $service->id);
        $this->outbox->publish(GenericEvent::of('service.rescue.ended', 'service', $service->id, ['reason' => $reason], $service->organization_id));

        return ['restored' => true, 'boot' => (string) ($previous['boot'] ?? '')];
    }

    /**
     * Ends the sessions whose window has passed (onhost:services:rescue-expire, every ten minutes). A rescue session
     * nobody closed is a server booted from somebody else's image for as long as nobody looks.
     *
     * @return array{checked:int, ended:int, errors:list<string>}
     */
    public function expire(int $limit = 50): array
    {
        $stats = ['checked' => 0, 'ended' => 0, 'errors' => []];
        $services = Service::query()->where('family', 'cloud')->whereNotNull('tags->'.self::TAG.'->until')
            ->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])->limit(max(1, $limit))->get();
        foreach ($services as $service) {
            $session = self::session($service);
            if ($session === null || now()->lessThan($session['until'])) {
                continue;
            }
            $stats['checked']++;
            try {
                $this->stop($service, CommandContext::system('rescue expiry'), 'the rescue window ended');
                $stats['ended']++;
            } catch (Throwable $e) {
                $stats['errors'][] = $service->id.': '.mb_substr($e->getMessage(), 0, 160);
            }
        }

        return $stats;
    }

    /** The boot order with the rescue drive first, keeping whatever was there behind it. */
    public static function bootFirst(string $boot): string
    {
        $order = str_starts_with($boot, 'order=') ? substr($boot, 6) : '';
        $rest = array_values(array_filter(array_map('trim', explode(';', $order)), fn (string $d) => $d !== '' && $d !== 'ide2'));

        return 'order='.implode(';', array_merge(['ide2'], $rest ?: ['scsi0']));
    }

    /** @param array<string,mixed>|null $session */
    private function remember(Service $service, ?array $session): void
    {
        $tags = (array) $service->tags;
        if ($session === null) {
            unset($tags[self::TAG]);
        } else {
            $tags[self::TAG] = $session;
        }
        $service->forceFill(['tags' => $tags])->save();
        app(ServiceFeatures::class)->forget($service);
    }

    /** @return array{0:ComputeProvider, 1:ResourceRef} */
    private function adapter(Service $service): array
    {
        $binding = ProviderBinding::query()->where('service_id', $service->id)->first();
        $instance = $binding === null ? null : ProviderInstance::query()->find($binding->provider_instance_id);
        $adapter = $instance === null ? null : $this->providers->forInstance($instance);
        if (! $adapter instanceof ComputeProvider || $binding === null) {
            throw new DomainError('rescue_unavailable', 'Záchranný režim tato služba nenabízí.', 409);
        }

        return [$adapter, $binding->ref()];
    }
}
