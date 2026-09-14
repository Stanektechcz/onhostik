<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * Names the control plane creates on shared executors (database names/users, FTP accounts, cron labels) carry a
 * per-service prefix, so that a panel shared by many customers can be listed per service and nothing collides.
 */
final class Naming
{
    /** e.g. `oh1yz8n6` for service `srv_01m1wf8e4c2pqbnw69b2vtrde9` */
    public static function prefix(?string $serviceId): string
    {
        return 'oh'.substr(preg_replace('/[^a-z0-9]/', '', strtolower((string) $serviceId)) ?? '', -6);
    }

    /** Customer-chosen suffix + service prefix, lower-case, safe for MySQL/FTP identifiers. */
    public static function scoped(?string $serviceId, string $suffix, int $max = 32): string
    {
        $clean = trim(preg_replace('/[^a-z0-9_]/', '_', strtolower($suffix)) ?? '', '_');

        return substr(self::prefix($serviceId).'_'.($clean === '' ? 'x' : $clean), 0, $max);
    }

    /** Cron/job labels on executors without per-site scoping (aaPanel): `onhost:<service>:<label>`. */
    public static function cronLabel(?string $serviceId, ?string $label): string
    {
        return 'onhost:'.(string) $serviceId.':'.substr(trim(preg_replace('/[^\w .-]/u', '', (string) $label) ?? ''), 0, 40) ?: 'cron';
    }

    public static function ownsCron(?string $serviceId, string $name): bool
    {
        return str_starts_with($name, 'onhost:'.(string) $serviceId.':');
    }
}
