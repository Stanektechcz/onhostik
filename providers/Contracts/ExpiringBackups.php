<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * A provider that keeps a backup for us and can remove it for good once the retention it was kept for has passed.
 *
 * Separate from `BackupCapable` on purpose: making and restoring a backup is what a customer does; removing one that is
 * protected against every prune job is what only the retention of a final archive may do — after its date, never on a
 * request.
 */
interface ExpiringBackups
{
    /** Remove the backup, protected or not. A backup that is already gone is not an error. */
    public function expireBackup(string $backupRemoteId): ProviderResult;
}
