<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Access;

use Onhost\Domain\Identity\Authorization\CapabilityMatrix;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
use Onhost\Domain\Services\Models\Service;

/**
 * The capability matrix (CapabilityMatrix, permission program S1-03; TASK-0043) seen from one service: which share ticks a level
 * is, and which service actions it reaches. A level of a service family is nothing but a share of that service
 * (ServiceAccessService::share with these ticks) — decided by GrantPolicy like every share (I1: nobody hands out a tick they do
 * not hold themselves there), carried by the `svc_*` bindings every endpoint and every long run already asks.
 */
final class AccessLevels
{
    /**
     * The share ticks (ServiceAccessService::CAPABILITIES keys) of a service-family cell, in the catalogue order; null for a cell
     * not offered and for the organization-wide dns family (O7: a domain is not shared on its own).
     *
     * @return list<string>|null
     */
    public static function capabilities(string $family, string $level): ?array
    {
        $grant = CapabilityMatrix::grant($family, $level);
        if ($grant === null || $grant['scope'] !== 'resource') {
            return null;
        }

        return array_values(array_filter(array_keys(ServiceAccessService::CAPABILITIES), fn (string $tick) => in_array(ServiceAccessService::CAPABILITIES[$tick], $grant['roles'], true)));
    }

    /**
     * The service actions (ServiceActionCommand::PERMISSIONS, asked as the bus asks them) a cell reaches. Which of them a given
     * service offers is its panel's (ServiceFeatures); this is the ceiling the level sets.
     *
     * @return list<string>
     */
    public static function actions(string $family, string $level): array
    {
        if ($family === CapabilityMatrix::ORGANIZATION_FAMILY) {
            return [];
        }
        $permissions = CapabilityMatrix::permissions($family, $level);

        return array_values(array_filter(array_keys(ServiceActionCommand::PERMISSIONS), fn (string $action) => in_array(ServiceActionCommand::permissionFor($action), $permissions, true)));
    }

    /**
     * What the owner is offered for this service: every level of its family — the ticks it is, whether it is offered, and in
     * words what the person will be able to do (or why the level is not there). Empty for a service the matrix does not cover.
     *
     * @return array{family: ?string, levels: list<array<string, mixed>>}
     */
    public static function forService(Service $service, string $locale): array
    {
        $family = CapabilityMatrix::familyOf($service->family);
        if ($family === null || $family === CapabilityMatrix::ORGANIZATION_FAMILY) {
            return ['family' => $family, 'levels' => []];
        }
        $levels = [];
        foreach (CapabilityMatrix::LEVELS as $level) {
            $capabilities = self::capabilities($family, $level);
            $levels[] = ['level' => $level, 'offered' => $capabilities !== null, 'capabilities' => $capabilities ?? []] + CapabilityMatrix::describe($family, $level, $locale);
        }

        return ['family' => $family, 'levels' => $levels];
    }
}
