<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * A provider whose backups the platform may remove when THEIR retention has passed — scheduled backups the platform
 * made itself, nothing else.
 *
 * Separate from `ExpiringBackups` on purpose: that one removes a final archive and unprotects it first, because the
 * platform protected it. A retention delete never unprotects: a volume somebody protected (an operator in the panel,
 * a safety copy) is refused, and so is one of another guest or one that does not carry the platform's marker.
 */
interface RetainedBackups
{
    /** What the notes of a backup the platform made say about it: this prefix followed by the id of its backup row. */
    public const MARKER_PREFIX = 'onhost backup:';

    /**
     * Delete the backup volume `$volid` of guest `$vmid` when — read from the provider first — it is not protected, it
     * belongs to that guest and its notes carry `$marker`. Anything else is refused with a VALIDATION/CONFLICT
     * `ProviderException` and nothing but the read was sent. A volume that is already gone is not an error.
     */
    public function deleteRetainedBackup(string $volid, string $vmid, string $marker): ProviderResult;
}
