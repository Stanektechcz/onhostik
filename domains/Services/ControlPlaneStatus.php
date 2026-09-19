<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Onhost\Domain\Provisioning\Models\IntegrationHealth;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Services\Models\Service;

/**
 * Two layers, reported apart (Brain card H324). The customer's service — the site, the game server, the VM — and the
 * panel API we manage it through fail independently: a panel under maintenance stops *changes*, not the web site,
 * and a site that is down is not explained by a healthy panel. This class speaks only about the second layer: can
 * the service be managed right now, and if not, why and until when. Whether the service itself runs is the business
 * of the uptime monitor and the reconciler (`ServiceFreshness`), never inferred from here.
 */
final class ControlPlaneStatus
{
    /**
     * @return array{available:bool, state:'available'|'maintenance'|'unreachable'|'disabled'|'unassigned', since:?string, until:?string, message:?string}
     */
    public static function of(Service $service): array
    {
        if ($service->provider_instance_id === null) {
            return self::row(true, 'unassigned');
        }
        $instance = ProviderInstance::query()->find($service->provider_instance_id);
        $health = $instance === null ? null : IntegrationHealth::query()->where('provider_instance_id', $instance->id)->first();

        return self::forInstance($instance, $health);
    }

    /** @return array{available:bool, state:'available'|'maintenance'|'unreachable'|'disabled'|'unassigned', since:?string, until:?string, message:?string} */
    public static function forInstance(?ProviderInstance $instance, ?IntegrationHealth $health): array
    {
        if ($instance === null) {
            return self::row(false, 'disabled', null, null, 'Správa služby není dostupná; služba sama tím není dotčena.');
        }
        if ($instance->state === 'maintenance') {
            return self::row(false, 'maintenance', null, $instance->maintenance_until?->toIso8601String(), 'Na panelu probíhá údržba. Služba běží dál, jen její změny jsou dočasně pozastavené.');
        }
        if ($instance->state === 'disabled') {
            return self::row(false, 'disabled', null, null, 'Správa služby je dočasně vypnutá. Služba sama tím není dotčena.');
        }
        if ($health !== null && $health->up === false) {
            return self::row(false, 'unreachable', $health->last_failure_at?->toIso8601String(), null, 'Panel právě neodpovídá. Služba může běžet normálně; změny zařadíme a provedeme, jakmile se panel ozve.');
        }

        return self::row(true, 'available');
    }

    /** @return array{available:bool, state:'available'|'maintenance'|'unreachable'|'disabled'|'unassigned', since:?string, until:?string, message:?string} */
    private static function row(bool $available, string $state, ?string $since = null, ?string $until = null, ?string $message = null): array
    {
        return ['available' => $available, 'state' => $state, 'since' => $since, 'until' => $until, 'message' => $message];
    }
}
