<?php

declare(strict_types=1);

namespace Onhost\Providers\Payments\Comgate;

use Onhost\Platform\Settings\SettingsStore;

/**
 * Whether Comgate payments are created in test mode (owner decision H-R8, 2026-10-07). The deployment's `COMGATE_TEST` is the
 * default; staff may override it in the administration (`ComgateCheckCommand test_mode.set`: a step-up and a second person,
 * audited), because a gateway in the wrong mode either takes real money in a rehearsal or hands out services for test cards.
 * The doctor names a production installation in test mode.
 */
final class ComgateMode
{
    public const SETTING = 'payments.comgate.test_mode';

    public static function test(): bool
    {
        $override = self::override();

        return $override ?? (bool) config('onhost.payments.comgate.test', true);
    }

    /** The administration's override, or null when the deployment's `COMGATE_TEST` decides. */
    public static function override(): ?bool
    {
        try {
            $value = app(SettingsStore::class)->get(self::SETTING);
        } catch (\Throwable) {
            return null; // no settings table (a fresh install before its migrations): the deployment decides
        }

        return is_bool($value) ? $value : null;
    }

    public static function source(): string
    {
        return self::override() === null ? 'env' : 'administration';
    }
}
