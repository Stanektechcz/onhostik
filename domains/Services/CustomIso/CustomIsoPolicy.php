<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\CustomIso;

use Onhost\Domain\Services\Models\CustomIso;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\RescueMode;
use Onhost\Platform\Errors\DomainError;
use Onhost\Providers\Contracts\ComputeProvider;
use Onhost\Providers\Contracts\CustomIsoCapable;
use Onhost\Providers\Contracts\ProviderAdapter;

/**
 * Who may have a custom ISO, how big, how many, and what a name may be (TASK-0110, owner decision G-R5).
 *
 * **Only when the ordered VPS plan has it.** The switch is the plan's entitlement `custom_iso` (and `custom_iso_max_mb`, the
 * size of one image), copied into the service when it is ordered, changed by a plan change or an add-on that patches it —
 * nothing else writes a service's entitlements, and there is deliberately no per-service override for staff. Without it the
 * feature is not offered (`reason: plan`, the panel says „není v tarifu") and an attach or an upload is refused with 403.
 *
 * **The ways out stay open.** A plan changed to one without the feature does not lock an image onto the server: detaching and
 * deleting stay possible (`custom_iso_exit`) for as long as the organization has an image.
 */
final class CustomIsoPolicy
{
    public const FEATURE = 'custom_iso';

    public const MAX_MB = 'custom_iso_max_mb';

    /** The plan does not sell it: the panel says „není v tarifu". */
    public const REASON_PLAN = 'plan';

    /** The plan sells it, the server it runs on has no storage for customers' images (the operator's configuration). */
    public const REASON_NODE = 'node';

    /** ISO 9660: the primary volume descriptor starts at sector 16 (byte 32768) with its type, then the identifier `CD001`. */
    public const MAGIC_OFFSET = 32769;

    public const MAGIC = 'CD001';

    public const ID_PATTERN = '/^iso_[0-9a-z]{26}$/';

    private const MIB = 1048576;

    public static function inPlan(Service $service): bool
    {
        return $service->family === 'cloud'
            && filter_var(((array) $service->entitlements)[self::FEATURE] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
    }

    /** The largest image this service may upload: what the plan sells, never more than the scanner reads in full. */
    public static function maxBytes(Service $service): int
    {
        $plan = (int) (((array) $service->entitlements)[self::MAX_MB] ?? 0);
        $mb = $plan > 0 ? $plan : max(1, (int) config('onhost.custom_iso.default_max_mb', 4096));

        return min($mb, max(1, (int) config('onhost.custom_iso.scan_max_mb', 4096))) * self::MIB;
    }

    public static function quotaBytes(): int
    {
        return max(1, (int) config('onhost.custom_iso.org_quota_mb', 20480)) * self::MIB;
    }

    public static function maxImages(): int
    {
        return max(1, (int) config('onhost.custom_iso.org_max_images', 5));
    }

    /** The server can hold customers' images: a hypervisor with a custom storage the operator configured. */
    public static function nodeReady(?ProviderAdapter $adapter): bool
    {
        return $adapter instanceof ComputeProvider && $adapter instanceof CustomIsoCapable && $adapter->customIsoStorage() !== null;
    }

    public static function assertInPlan(Service $service): void
    {
        if (! self::inPlan($service)) {
            throw new DomainError('custom_iso_not_in_plan', 'Vlastní ISO není v tarifu této služby; získáte ho změnou tarifu.', 403, ['reason' => self::REASON_PLAN, 'feature' => self::FEATURE]);
        }
    }

    /** An upload or an attach on a server that does not run (suspended, being cancelled) is refused (review M6). */
    public static function assertActive(Service $service): void
    {
        if (! in_array($service->state, [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED], true)) {
            throw new DomainError('service_not_active', 'Služba teď neběží; vlastní ISO lze nahrát a připojit jen k běžícímu serveru.', 409, ['state' => $service->state]);
        }
    }

    public static function assertNoRescue(Service $service): void
    {
        if (RescueMode::session($service) !== null) {
            throw new DomainError('iso_rescue_active', 'Server je v záchranném režimu; ukončete ho, než připojíte nebo odpojíte ISO.', 409);
        }
    }

    /** The image a request names, of this server's organization and project, or 404 — another one's id is answered like a missing one. */
    public static function imageOf(Service $service, mixed $id): CustomIso
    {
        $id = is_string($id) ? trim($id) : '';
        $iso = preg_match(self::ID_PATTERN, $id) === 1
            ? CustomIso::reachableFrom($service)->find($id)
            : null;

        return $iso ?? throw DomainError::notFound('custom ISO');
    }

    /** The image attached to this server, if any. */
    public static function attachedTo(Service $service): ?CustomIso
    {
        return CustomIso::query()->where('attached_service_id', $service->id)->where('state', CustomIso::READY)->first();
    }

    /** Whether the organization still has something to detach or delete through this server (the way out, `custom_iso_exit`). */
    public static function hasWayOut(Service $service): bool
    {
        return $service->family === 'cloud' && CustomIso::reachableFrom($service)->exists();
    }

    /**
     * The validated parameters of an image action (ServiceService::featureParams).
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    public static function params(Service $service, string $action, array $params): array
    {
        $flag = fn (string $key, bool $default) => filter_var($params[$key] ?? $default, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;

        return match ($action) {
            'iso.attach' => (function () use ($service, $params, $flag) {
                self::assertInPlan($service);
                self::assertNoRescue($service);
                $iso = self::imageOf($service, $params['iso_id'] ?? null);
                self::assertNotElsewhere($iso, $service);

                return ['iso_id' => $iso->id, 'boot_first' => $flag('boot_first', true), 'reboot' => $flag('reboot', false)];
            })(),
            'iso.detach' => (function () use ($service, $flag) {
                self::assertNoRescue($service);
                $iso = self::attachedTo($service) ?? throw new DomainError('iso_not_attached', 'K tomuto serveru není připojené žádné vlastní ISO.', 409);

                return ['iso_id' => $iso->id, 'reboot' => $flag('reboot', false)];
            })(),
            'iso.delete' => (function () use ($service, $params) {
                $iso = self::imageOf($service, $params['iso_id'] ?? null);
                self::assertNotElsewhere($iso, $service);
                if ($iso->attached_service_id === $service->id) {
                    self::assertNoRescue($service); // it is detached first, and a rescue session holds the drive
                }

                return ['iso_id' => $iso->id];
            })(),
            default => throw new DomainError('service_action_unknown', "Unknown service action {$action}.", 422, ['action' => $action]),
        };
    }

    /**
     * An image attached to ANOTHER server is detached there: through this server's operation the platform would act on a server
     * the person was never asked about (another project, another person's share).
     */
    private static function assertNotElsewhere(CustomIso $iso, Service $service): void
    {
        if ($iso->attached_service_id !== null && $iso->attached_service_id !== $service->id) {
            throw new DomainError('iso_attached_elsewhere', 'Toto ISO je připojené k jinému serveru; nejdřív ho odpojte tam.', 409, ['service_id' => $iso->attached_service_id]);
        }
    }

    /**
     * The name the customer sees: the last part of what their browser sent, letters, digits and a few signs only, ending in .iso.
     * Never a path: the file lives under the platform's own name (`<organization>/<id>.iso`, `CustomIso::remoteFilename()`).
     */
    public static function displayName(string $clientName): string
    {
        $base = basename(str_replace('\\', '/', $clientName));
        $base = (string) preg_replace('/\.iso\s*$/i', '', $base);
        $base = (string) preg_replace('/[^A-Za-z0-9._+()-]+/', '-', $base);
        $base = trim((string) preg_replace('/-{2,}/', '-', $base), '.-');
        $base = trim(mb_substr($base, 0, 100), '.-');

        return ($base === '' ? 'image' : $base).'.iso';
    }

    /**
     * Whether the file is an ISO 9660 image (the identifier `CD001` of its first volume descriptor). Hybrid and UDF-bridge images
     * (Linux installers, Windows) carry it too; anything else is not attached as a CD-ROM.
     *
     * @param  resource  $stream
     */
    public static function looksLikeIso($stream): bool
    {
        if (fseek($stream, self::MAGIC_OFFSET) !== 0) {
            return false;
        }

        return fread($stream, strlen(self::MAGIC)) === self::MAGIC;
    }

    /** What the feature map says about it on a `cloud` service. @return array{enabled:bool, reason?:string, options?:array<string,mixed>} */
    public static function feature(Service $service, ?ProviderAdapter $adapter): array
    {
        if (! self::inPlan($service)) {
            return ['enabled' => false, 'reason' => self::REASON_PLAN];
        }
        if (! self::nodeReady($adapter)) { // strict: an instance nobody can reach right now offers no upload either
            return ['enabled' => false, 'reason' => self::REASON_NODE];
        }

        return ['enabled' => true, 'options' => ['max_bytes' => self::maxBytes($service), 'quota_bytes' => self::quotaBytes(), 'max_images' => self::maxImages(), 'attached' => self::attachedTo($service)?->id]];
    }
}
