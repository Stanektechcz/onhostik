<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\CustomIso;

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Services\Models\CustomIso;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\RescueMode;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\ComputeProvider;
use Onhost\Providers\Contracts\CustomIsoCapable;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\ResourceRef;
use Throwable;

/**
 * A customer's image on the server as a CD-ROM (TASK-0110): copied to the hypervisor's custom storage, attached as the server's
 * CD drive with the boot order the customer asked for, detached with exactly the boot order and drive that were there before,
 * and deleted — detached first — from the hypervisor and the platform's disk.
 *
 * It shares the CD drive with the rescue mode (`ide2`, RescueMode): one at a time. An image is not attached or detached while a
 * rescue session holds the drive (the session would put back what it found, over the image); a rescue started while an image is
 * attached records the image as what it found and puts it back at its end.
 */
final class CustomIsoDrive
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly CustomIsoLibrary $library,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    /**
     * The image on the node the server runs on: nothing when the storage already holds it (a shared storage, an earlier attach),
     * else an upload the hypervisor verifies against the image's SHA-256. The copy is written down before it is made, so a delete
     * finds it even when the upload never finished (a volume that is not there counts as deleted).
     */
    public function ensureOnNode(Service $service, CustomIso $iso): ProviderResult
    {
        [$adapter, $ref, $instance] = $this->adapter($service);
        $node = (string) $ref->node;
        $volume = $adapter->customIsoVolume($iso->remoteFilename());
        $this->rememberCopy($iso, $instance->id, $node, $volume);
        if ($adapter->hasCustomIso($node, $volume)) {
            return ProviderResult::completed($ref, ['volume_ready' => true, 'uploaded' => false]);
        }
        $stream = $this->library->disk()->readStream($iso->path);
        if (! is_resource($stream)) {
            throw new DomainError('iso_file_missing', 'Soubor obrazu na úložišti platformy chybí; nahrajte ho znovu.', 409, ['iso_id' => $iso->id]);
        }
        try {
            return $adapter->uploadCustomIso($node, $iso->remoteFilename(), $stream, $iso->sha256);
        } finally {
            self::close($stream);
        }
    }

    /** The HTTP client may have closed the stream it was handed (Guzzle closes what it wraps): only an open one is closed here. */
    private static function close(mixed $stream): void
    {
        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    /**
     * Attaches the image as the server's CD drive. The boot order found before the first image is kept: swapping one image for
     * another keeps the original, so a detach always goes back to the server's own order.
     *
     * @return array{iso_id:string, boot:string|null, rebooted:bool}
     */
    public function attach(Service $service, CustomIso $iso, bool $bootFirst, bool $reboot, CommandContext $actor): array
    {
        return DB::transaction(fn () => $this->attachLocked($service, $iso, $bootFirst, $reboot, $actor));
    }

    /**
     * Asked again when the step runs, not only when the request was queued (review M3, M6): the plan may have changed, the server
     * stopped, and the image been attached elsewhere in between. The server and the image are locked while the drive changes, so two
     * attaches cannot both take one image or both write the server's drive.
     *
     * @return array{iso_id:string, boot:string|null, rebooted:bool}
     */
    private function attachLocked(Service $service, CustomIso $iso, bool $bootFirst, bool $reboot, CommandContext $actor): array
    {
        $service = Service::query()->whereKey($service->id)->lockForUpdate()->first() ?? throw DomainError::notFound('service');
        CustomIsoPolicy::assertInPlan($service);
        CustomIsoPolicy::assertActive($service);
        CustomIsoPolicy::assertNoRescue($service);
        $iso = CustomIso::query()->whereKey($iso->id)->where('state', CustomIso::READY)->lockForUpdate()->first()
            ?? throw new DomainError('iso_gone', 'Toto ISO už v knihovně organizace není.', 409);
        if ($iso->attached_service_id !== null && $iso->attached_service_id !== $service->id) {
            throw new DomainError('iso_attached_elsewhere', 'Toto ISO je připojené k jinému serveru; nejdřív ho odpojte tam.', 409, ['service_id' => $iso->attached_service_id]);
        }
        [$adapter, $ref] = $this->adapter($service);
        $current = CustomIsoPolicy::attachedTo($service);
        $previous = $current !== null && is_array($current->previous_boot) ? $current->previous_boot : $adapter->bootMedia($ref);
        $volume = $adapter->customIsoVolume($iso->remoteFilename());
        $boot = $bootFirst ? RescueMode::bootFirst((string) ($previous['boot'] ?? '')) : null;
        $adapter->setBootMedia($ref, $volume, $boot);
        if ($reboot) {
            $adapter->power($ref, 'reboot'); // the drive reaches a running guest only at its next start
        }
        if ($current !== null && $current->id !== $iso->id) {
            $current->forceFill(['attached_service_id' => null, 'attached_at' => null, 'previous_boot' => null])->save();
        }
        $iso->forceFill(['attached_service_id' => $service->id, 'attached_at' => now(), 'previous_boot' => ['iso' => $previous['iso'] ?? null, 'boot' => (string) ($previous['boot'] ?? '')]])->save();
        app(ServiceFeatures::class)->forget($service);
        $this->audit->record($actor->withScope($service->organization_id), 'service.iso.attach', 'succeeded', ['iso_id' => $iso->id, 'boot_first' => $bootFirst, 'reboot' => $reboot, 'previous_boot' => $previous['boot'] ?? null], 'service', $service->id);
        $this->outbox->publish(GenericEvent::of('service.iso.attached', 'service', $service->id, ['iso_id' => $iso->id, 'name' => $iso->name, 'boot_first' => $bootFirst], $service->organization_id));

        return ['iso_id' => $iso->id, 'boot' => $boot, 'rebooted' => $reboot];
    }

    /**
     * Puts back exactly the drive and boot order found before the image. Safe to call when nothing is attached.
     *
     * @return array{detached:bool, boot:string|null, rebooted:bool}
     */
    public function detach(Service $service, bool $reboot, CommandContext $actor, string $reason = 'the customer detached it'): array
    {
        $iso = CustomIsoPolicy::attachedTo($service);
        if ($iso === null) {
            return ['detached' => false, 'boot' => null, 'rebooted' => false];
        }
        CustomIsoPolicy::assertNoRescue($service);
        [$adapter, $ref] = $this->adapter($service);
        $previous = (array) ($iso->previous_boot ?? []);
        $boot = (string) ($previous['boot'] ?? '');
        $adapter->setBootMedia($ref, ($previous['iso'] ?? null) === null ? null : (string) $previous['iso'], $boot);
        if ($reboot) {
            $adapter->power($ref, 'reboot');
        }
        $iso->forceFill(['attached_service_id' => null, 'attached_at' => null, 'previous_boot' => null])->save();
        app(ServiceFeatures::class)->forget($service);
        $this->audit->record($actor->withScope($service->organization_id), 'service.iso.detach', 'succeeded', ['iso_id' => $iso->id, 'reason' => $reason, 'boot' => $boot, 'reboot' => $reboot], 'service', $service->id);
        $this->outbox->publish(GenericEvent::of('service.iso.detached', 'service', $service->id, ['iso_id' => $iso->id, 'reason' => $reason], $service->organization_id));

        return ['detached' => true, 'boot' => $boot, 'rebooted' => $reboot];
    }

    /**
     * Deletes the image: every copy on a hypervisor storage, then the platform's file and the row. Called with the image already
     * detached (CustomIsoWorkflow detaches first). A copy whose instance cannot be reached keeps the delete from finishing: the
     * operation fails and is retried, never reported done with a customer's image left on a node.
     *
     * @return array{iso_id:string, copies:int}
     */
    public function delete(CustomIso $iso, CommandContext $actor, ?Service $through = null): array
    {
        if ($iso->attached_service_id !== null) {
            throw new DomainError('iso_attached_elsewhere', 'Toto ISO je připojené k serveru; nejdřív ho odpojte.', 409, ['service_id' => $iso->attached_service_id]);
        }
        $copies = array_values(array_filter((array) ($iso->node_copies ?? []), fn ($c) => is_array($c)));
        foreach ($copies as $copy) {
            $instance = ProviderInstance::query()->find((string) ($copy['instance'] ?? ''));
            $adapter = $instance === null ? null : $this->providers->forInstance($instance);
            if ($adapter instanceof CustomIsoCapable && $adapter instanceof ComputeProvider) {
                // the hypervisor's task has to say it is gone before the copy leaves the record (review M2): a failed or unfinished
                // delete fails the step with the copy still listed, so a retry deletes it — never a node copy nobody remembers
                $this->settled($adapter, $adapter->deleteCustomIso((string) $copy['node'], (string) $copy['volume']));
            } // else the instance is gone from the platform: there is nothing left to call
            $left = array_values(array_filter((array) ($iso->node_copies ?? []), fn ($c) => $c !== $copy));
            $iso->forceFill(['node_copies' => $left])->save();
        }
        $disk = $this->library->disk();
        if ($disk->exists($iso->path)) {
            $disk->delete($iso->path);
        }
        $iso->forceFill(['state' => CustomIso::DELETED, 'deleted_at' => now(), 'node_copies' => []])->save();
        $organizationId = (string) $iso->organization_id;
        $this->audit->record($actor->withScope($organizationId), 'service.iso.delete', 'succeeded', ['iso_id' => $iso->id, 'name' => $iso->name, 'copies' => count($copies)], $through === null ? 'custom_iso' : 'service', $through->id ?? $iso->id);
        $this->outbox->publish(GenericEvent::of('service.iso.deleted', $through === null ? 'custom_iso' : 'service', $through->id ?? $iso->id, ['iso_id' => $iso->id, 'name' => $iso->name], $organizationId));

        return ['iso_id' => $iso->id, 'copies' => count($copies)];
    }

    /** Waits for the hypervisor's task of a result (up to `onhost.custom_iso.delete_wait_seconds`); a failure or no answer in time throws. */
    private function settled(ComputeProvider $adapter, ProviderResult $result): void
    {
        if (! $result->isAsync() || $result->async === null) {
            return;
        }
        $deadline = microtime(true) + max(1, (int) config('onhost.custom_iso.delete_wait_seconds', 120));
        while (true) {
            $status = $adapter->awaitStatus($result->async);
            if ($status->state === AsyncStatus::SUCCEEDED) {
                return;
            }
            if ($status->state === AsyncStatus::FAILED || $status->state === AsyncStatus::UNKNOWN) {
                throw new DomainError('iso_node_delete_failed', 'Hypervizor obraz nesmazal ('.mb_substr((string) $status->message, 0, 160).'); smazání se zopakuje.', 502, ['retryable' => true]);
            }
            if (microtime(true) >= $deadline) {
                throw new ProviderException('proxmox', ProviderErrorCode::TRANSIENT, 'The hypervisor has not finished deleting the image yet');
            }
            usleep(max(200_000, min(5, $result->async->pollIntervalSeconds) * 1_000_000));
        }
    }

    private function rememberCopy(CustomIso $iso, string $instanceId, string $node, string $volume): void
    {
        $copies = array_values(array_filter((array) ($iso->node_copies ?? []), fn ($c) => is_array($c)));
        foreach ($copies as $copy) {
            if (($copy['instance'] ?? null) === $instanceId && ($copy['node'] ?? null) === $node && ($copy['volume'] ?? null) === $volume) {
                return;
            }
        }
        $copies[] = ['instance' => $instanceId, 'node' => $node, 'volume' => $volume];
        $iso->forceFill(['node_copies' => $copies])->save();
    }

    /** @return array{0:ComputeProvider&CustomIsoCapable, 1:ResourceRef, 2:ProviderInstance} */
    private function adapter(Service $service): array
    {
        $binding = ProviderBinding::query()->where('service_id', $service->id)->orderBy('created_at')->first();
        $instance = $binding === null ? null : ProviderInstance::query()->find($binding->provider_instance_id);
        try {
            $adapter = $instance === null ? null : $this->providers->forInstance($instance);
        } catch (Throwable) {
            $adapter = null;
        }
        if ($binding === null || $instance === null || ! CustomIsoPolicy::nodeReady($adapter) || ! $adapter instanceof ComputeProvider || ! $adapter instanceof CustomIsoCapable) {
            throw new DomainError('custom_iso_unavailable', 'Server, na kterém služba běží, vlastní ISO nenabízí.', 409);
        }

        return [$adapter, $binding->ref(), $instance];
    }
}
