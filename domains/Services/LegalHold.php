<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;

/**
 * A legal hold suspends deletion (Brain card H18): while it lasts nothing that holds the customer's data may go away —
 * not the service, and not its copies either. Retention keeps counting, it just does not act: an archive whose sixty
 * days ran out during a hold is removed by the first pass after the hold is lifted, not before.
 *
 * The hold is set on the organization (`settings.legal_hold`) and copied to its services; a service created after the
 * hold was applied carries no flag of its own, so both are asked.
 */
final class LegalHold
{
    /** Actions that destroy a copy of the data or the data itself without leaving one behind. */
    public const DESTRUCTIVE_ACTIONS = ['backup.delete', 'gbackup.delete', 'snapshot.delete', 'reinstall'];

    public static function coversService(?Service $service): bool
    {
        return $service !== null && ((bool) $service->legal_hold || self::coversOrganization((string) $service->organization_id));
    }

    public static function coversBackup(Backup $backup): bool
    {
        $service = $backup->service_id === null ? null : Service::query()->withTrashed()->find($backup->service_id);

        return ($service !== null && (bool) $service->legal_hold) || self::coversOrganization((string) $backup->organization_id);
    }

    public static function coversOrganization(string $organizationId): bool
    {
        // asked fresh every time: a worker lives for an hour, and a hold applied or lifted meanwhile has to count at once
        return $organizationId !== '' && (bool) data_get(Organization::query()->find($organizationId)?->settings, 'legal_hold', false);
    }
}
