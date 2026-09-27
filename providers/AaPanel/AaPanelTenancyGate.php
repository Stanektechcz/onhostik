<?php

declare(strict_types=1);

namespace Onhost\Providers\AaPanel;

use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Throwable;

/**
 * Whether an aaPanel node several customers share has been closed to in-panel file writing and shell cron
 * (TASK-0034, permission program IF-7 / D11, owner default §10 O1).
 *
 * aaPanel's file API and its scheduler run as root, and every site runs as the same `www`: a tenant who plants a link
 * in its own site steers the next root write — through the file manager, an upload, an unpack, a job — into another
 * customer's site or the node itself. A lexical or realpath check before the root call is still a race against the
 * tenant's own cron, so it is not engineered around: on a node that serves more than one organization the operator
 * closes those tools (`operator:aapanel:tenancy --apply`, recorded as the instance option `tenancy.closed`). SFTP/FTP
 * stays, jobs that already run keep running as the site's own user, and the ways out (pause, delete) stay open.
 *
 * The flag is read from the row every time, not from the adapter's copy of the instance: a queue worker that built its
 * adapter before `--apply` must not keep writing for hours after it.
 */
final class AaPanelTenancyGate
{
    public const OPTION = 'tenancy';

    /** Customer-facing words; the panel's name is never in them. */
    public const REASON = 'This server is shared with other customers, so files and scheduled shell commands can no longer be changed from the control panel. Upload files over SFTP/FTP; jobs that already run keep running.';

    /** A row that cannot be read counts as closed: the features stay off rather than trusting an old copy (below). */
    public static function closed(ProviderInstance $instance): bool
    {
        return self::state($instance) ?? true;
    }

    public static function assertOpen(ProviderInstance $instance): void
    {
        $closed = self::state($instance);
        if ($closed === null) {
            throw new ProviderException('aapanel', ProviderErrorCode::TRANSIENT, 'Whether this server is shared could not be checked just now; nothing was changed. Try again shortly.');
        }
        if ($closed) {
            throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, self::REASON);
        }
    }

    /**
     * Null when the row cannot be read. It used to fall back to the adapter's in-memory copy of the instance — the very
     * copy this gate exists not to trust — so a database hiccup in a worker built before `--apply` failed OPEN and let a
     * root write through (TASK-0034 review round 1). Now it fails closed: refused (and retried), never allowed.
     */
    private static function state(ProviderInstance $instance): ?bool
    {
        try {
            $options = ProviderInstance::query()->whereKey($instance->id)->value('options');
        } catch (Throwable) {
            return null;
        }

        return (bool) data_get(is_array($options) ? $options : [], self::OPTION.'.closed', false);
    }
}
