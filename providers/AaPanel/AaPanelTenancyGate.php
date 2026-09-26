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

    public static function closed(ProviderInstance $instance): bool
    {
        try {
            $options = ProviderInstance::query()->whereKey($instance->id)->value('options');
        } catch (Throwable) {
            $options = $instance->options; // the database away: the copy the adapter was built with is the best answer left
        }

        return (bool) data_get(is_array($options) ? $options : [], self::OPTION.'.closed', false);
    }

    public static function assertOpen(ProviderInstance $instance): void
    {
        if (self::closed($instance)) {
            throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, self::REASON);
        }
    }
}
